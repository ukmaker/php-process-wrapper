<?php
namespace UK\CO\UKMaker\ProcessWrapper;
/**
* Event handlers can be registered with the ProcessWrapper to handle events in the
* lifecycle of the wrapper and child processes.
**/
class StatisticsGatheringEventHandler implements IProcessWrapperEventHandler {

	private $fStartTime;
	private $iTotalBirths = 0;
	private $iTotalDeaths = 0;
	
	private $iPrintTicks = 0;
	private $iTicks = 0;

    /**
     * @param int $iPrintTicks
     */
    public function __construct($iPrintTicks = 0) {
		$this->iPrintTicks = $iPrintTicks;
	}

    /**
     * @return mixed
     */
    public function getStartTime() {
		return $this->fStartTime;
	}

    /**
     * @return int
     */
    public function getTotalBirths() {
		return $this->iTotalBirths;
	}

    /**
     * @return int
     */
    public function getTotalDeaths() {
		return $this->iTotalDeaths;
	}

    /**
     * @param ProcessWrapper $oWrapper
     */
    public function handleWrapperStartup(ProcessWrapper $oWrapper) {
		$this->fStartTime = microtime(true);
	}

    /**
     * @param ProcessWrapper $oWrapper
     */
    public function handleWrapperShutdown(ProcessWrapper $oWrapper) {
	}

    /**
     * @param ProcessWrapper $oWrapper
     */
    public function handleWrapperTick(ProcessWrapper $oWrapper) {
		if($this->iPrintTicks == 0) {
			return;
		}
		
		$this->iTicks++;
		if($this->iTicks >= $this->iPrintTicks) {
			$this->iTicks = 0;
			echo "Stats: Births = ".$this->iTotalBirths.", Deaths = ".$this->iTotalDeaths."\n";
		}
	}

    /**
     * @param ProcessWrapper $oWrapper
     * @param ChildProcess $oChild
     */
    public function handleChildBirth(ProcessWrapper $oWrapper, ChildProcess $oChild) {
		$this->iTotalBirths++;
	}

    /**
     * @param ProcessWrapper $oWrapper
     * @param ChildProcess $oChild
     */
    public function handleChildDeath(ProcessWrapper $oWrapper, ChildProcess $oChild) {
		$this->iTotalDeaths++;
	}

    /**
     * @param ProcessWrapper $oWrapper
     * @param ChildProcess $oChild
     */
    public function handleChildLife(ProcessWrapper $oWrapper, ChildProcess $oChild) {
	}
}