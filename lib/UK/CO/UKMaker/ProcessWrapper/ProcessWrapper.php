<?php

namespace UK\CO\UKMaker\ProcessWrapper;

class ProcessWrapper {

	private $iInitialChildren;
	private $iMaxChildren;
	
	private $fnCreateChild;

	private $aChildren = array();
	private $aBirths = array();
	private $aIncubating = array();
	
	/**
	* Maintain a queue of signalled pids so we handle race conditions properly
	**/
	private $aSignalledPidQueue = array();
	
	/**
	* List oof event handlers to be called in order
	**/
	private $aEventHandlers = array();
	
	/**
	 * Construct a wrapper to handle creating new children using the given function
	 * An unlimited number of children can be started by setting $iMaxChildren to zero
     *
     * @param int $iInitialChildren
     * @param int $iMaxChildren
     * @param $fnCreateChild
	**/
	public function __construct($iInitialChildren, $iMaxChildren = 0, $fnCreateChild) {
		$this->iInitialChildren = $iInitialChildren;
		$this->iMaxChildren = $iMaxChildren;
		$this->fnCreateChild = $fnCreateChild;
		
		// Install the signal handler
		pcntl_signal(SIGCHLD, array($this, "systemSignalHandler"));
	}

    /**
     * @param IProcessWrapperEventHandler $oHandler
     */
    public function addEventHandler(IProcessWrapperEventHandler $oHandler) {
		foreach($this->aEventHandlers as $oInstalledHandler) {
			// don't install the same handler twice
			if($oHandler === $oInstalledHandler) {
				return;
			}
		}
		$this->aEventHandlers[] = $oHandler;
	}

    /**
     * @param IProcessWrapperEventHandler $oHandler
     */
    public function removeEventHandler(IProcessWrapperEventHandler $oHandler) {
		foreach(array_keys($this->aEventHandlers) as $iIndex) {
			$oInstalledHandler = $this->aEventHandlers[$iIndex];
			if($oHandler === $oInstalledHandler) {
				unset($this->aEventHandlers[$iIndex]);
				return;
			}
		}
	}

    /**
     * @param string $sMessage
     */
    public function info($sMessage) {
		echo "INFO: $sMessage\n";
	}

    /**
     * @param string $sMessage
     */
    public function warn($sMessage) {
		echo "WARNING: $sMessage\n";
	}
	
	public function run() {
	
		$this->dispatchStartup();
		$this->startAll();
		$this->monitor();
		$this->dispatchShutdown();
	}
	
	public function startAll() {
		for($i=1; $i<=$this->iInitialChildren; $i++) {
		
			$this->startChild();
		}
	}

	public function monitor() {
		while($this->dispatch()) {
			usleep(100);
		}
	}

    /**
     * @return bool
     */
    public function dispatch() {
		
		// Allow any event handlers to queue births and deaths
		$this->dispatchTick();
		
		// Deal with any deliveries
		$this->handleDeliveries();

		// Take note of any births
		foreach($this->aBirths as $iPid => $oChild) {
			$this->aChildren[$iPid] = $oChild;
		}
		$this->aBirths = array();
		
		if(count($this->aChildren) || count($this->aIncubating)) {
			$this->handleSignals();
			$this->dispatchLife();
			return true;
		}
		
		return false;
	}
	
	/**
	* Handle signals from the OS
	**/
	public function systemSignalHandler() {
		while(($iPid = pcntl_waitpid(-1, $iStatus, WNOHANG)) > 0) {
			if(!pcntl_wifexited($iStatus)) {
				// Child died in some abnormal way. No exit code available
				$this->aSignalledPidQueue[$iPid] = array(false, $iStatus);
			} else {
				$iExitCode = pcntl_wexitstatus($iStatus);
				$this->aSignalledPidQueue[$iPid] = array(true, $iExitCode);
			}
		}
	}
	
	/**
	* Handle any signals in the queue
	**/
	public function handleSignals() {
		pcntl_signal_dispatch();
		// Dispatch any signals in the queue
		$aKeys = array_keys($this->aSignalledPidQueue);
		foreach($aKeys as $iPid) {
			$aStatus = $this->aSignalledPidQueue[$iPid];
			unset($this->aSignalledPidQueue[$iPid]);	
			if(!isset($this->aChildren[$iPid])) {
				// Hmm. got a signal for a dead child
				$this->warn("Cannot get exit status of child $iPid, it has disappeared");
			} else {

                /** @var ChildProcess $oChild */
				$oChild = $this->aChildren[$iPid];
				unset($this->aChildren[$iPid]);
				
				$iExitCode = $aStatus[1];
				$oChild->setExitCode($iExitCode);
				
				// Did the child die nastily?
				if(!$aStatus[0]) {
					$this->warn("Child $iPid died abnormally, code was $iExitCode");
					$oChild->setState(ChildProcess::STATE_EXITED_ABNORMALLY);
				} else {
					$this->info("Child $iPid exited with code $iExitCode");
					$oChild->setState(ChildProcess::STATE_EXITED_NORMALLY);
				}
				
				$this->dispatchChildDeath($oChild);
			}
		}
	}
	
	public function dispatchStartup() {
        /** @var IProcessWrapperEventHandler $oHandler */
		foreach($this->aEventHandlers as $oHandler) {
			$oHandler->handleWrapperStartup($this);
		}
	}
	
	public function dispatchTick() {
        /** @var IProcessWrapperEventHandler $oHandler */
		foreach($this->aEventHandlers as $oHandler) {
			$oHandler->handleWrapperTick($this);
		}
	}
	
	public function dispatchShutdown() {
        /** @var IProcessWrapperEventHandler $oHandler */
		foreach($this->aEventHandlers as $oHandler) {
			$oHandler->handleWrapperShutdown($this);
		}
	}

    /**
     * @param ChildProcess $oChild
     */
    public function dispatchChildBirth(ChildProcess $oChild) {
        /** @var IProcessWrapperEventHandler $oHandler */
		foreach($this->aEventHandlers as $oHandler) {
			$oHandler->handleChildBirth($this, $oChild);
		}
	}

    /**
     * @param ChildProcess $oChild
     */
    public function dispatchChildDeath(ChildProcess $oChild) {
        /** @var IProcessWrapperEventHandler $oHandler*/
		foreach($this->aEventHandlers as $oHandler) {
			$oHandler->handleChildDeath($this, $oChild);
		}
	}
	
	public function dispatchLife() {
        /** @var ChildProcess $oChild */
		foreach($this->aChildren as $oChild) {
            /** @var IProcessWrapperEventHandler $oHandler */
			foreach($this->aEventHandlers as $oHandler) {
				$oHandler->handleChildLife($this, $oChild);
			}
		}
	}

    /**
     * @return array
     */
    public function getChildren() {
		return $this->aChildren;
	}

    /**
     * @return int
     */
    public function getChildCount() {
		return count($this->aChildren);
	}

    /**
     * @return int
     */
    public function getLiveChildCount() {
		$iLiving = 0;
		/** @var ChildProcess $oChild */
		foreach($this->aChildren as $oChild) {
			if($oChild->isAlive()) {
				$iLiving++;
			}
		}
		
		return $iLiving + count($this->aBirths) + count($this->aIncubating);
	}

    /**
     * @return null|ChildProcess
     */
    public function startChild() {
	
		if($this->iMaxChildren > 0 && (count($this->aChildren) + count($this->aBirths)) >= $this->iMaxChildren) {
			$this->warn('Cannot start a new child, '.(count($this->aChildren) + count($this->aBirths)).' children are already running');
			return null;
		}
		
		$iPid = pcntl_fork();
		
		if($iPid) {
			// We are the parent
			$oChild = new ChildProcess($iPid);
			$this->aBirths[$iPid] = $oChild;
			$this->dispatchChildBirth($oChild);
			return $oChild;
		} else {
			// we are the child
			$fn = $this->fnCreateChild;
			$oChild = $fn(posix_getpid());
			$iExitCode = $oChild->run();
			
			exit($iExitCode);
		}
	}

    /**
     * @param $fBirthDate
     */
    public function incubate($fBirthDate) {
		$this->aIncubating[] = $fBirthDate;
	}
	
	public function handleDeliveries() {
		$aNotDone = array();
		$fNow = microtime(true);
		foreach($this->aIncubating as $fDeliveryTime) {
			if($fDeliveryTime < $fNow) {
				$this->startChild();
			} else {
				$aNotDone[] = $fDeliveryTime;
			}
		}
		
		$this->aIncubating = $aNotDone;
	}

    /**
     * @param int $iPid
     */
    public function stopChild($iPid) {
		$this->signalChild($iPid, SIGQUIT);
	}

    /**
     * @param int $iPid
     * @param int $iSignal
     * @return bool
     */
    public function signalChild($iPid, $iSignal) {
		if($iSignal > 0 && $iPid > 0) {
			return posix_kill($iPid, $iSignal) && pcntl_signal_dispatch();
		} else {
			return false;
		}
	}
}
