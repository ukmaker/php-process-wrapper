<?php
namespace UK\CO\UKMaker\ProcessWrapper;

/**
* Class to manage processes by interpreting commands issued on an input stream
**/
class CLI {

	const CMD_PS = 'ps';
	const CMD_KILL = 'kill';
	const CMD_FORK = 'fork';

	private $oWrapper;
	private $sLine;
	
	public function __construct(ProcessWrapper $oWrapper) {
		$this->oWrapper = $oWrapper;
	}
	
	
	public function run() {
	
		$this->initStreams();
		
		$this->oWrapper->startAll();
		$this->sLine = "";
		
		while(true) {
			$this->dispatch();
			$this->oWrapper->dispatch();
			usleep(500);
		}
	}
	
	public function initStreams() {
		stream_set_blocking(STDIN, false);
	}
	
	public function dispatch() {
	
		$aRead  = array(STDIN);
		$aWrite = array();
		$aError = array();
	
		while(stream_select($aRead, $aWrite, $aErr, 0, 10000)) {
		
			$sChar = fgetc(STDIN);
			if($sChar == "\n") {
				$this->handleLine($this->sLine);
				$this->sLine = "";
			} else {
				$this->sLine .= $sChar;
			}
		}
	}
	
	public function handleLine($sLine) {
	
		$sLine = trim($sLine);
	
		if(strpos($sLine, ' ') !== false) {
			list($sCmd, $sArg) = explode(' ', $sLine);
		} else {
			$sCmd = $sLine;
		}
		
		switch($sCmd) {
		
			case self::CMD_PS:
			
				$aStatus = $this->oWrapper->getChildren();
				foreach($aStatus as $oChild) {
					echo $oChild->getPid()." ".$oChild->getRuntime().' '.$oChild->getState()."\n";
				}
				
				break;
			
			case self::CMD_KILL:
				
				$this->oWrapper->stopChild($sArg);
				break;
			
			case self::CMD_FORK:
				$this->oWrapper->startChild();
				break;
		}
	}
}