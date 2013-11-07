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
	
	public function __construct($iPrintTicks = 0) {
		$this->iPrintTicks = $iPrintTicks;
	}
	
	public function getStartTime() {
		return $this->fStartTime;
	}
	
	public function getTotalBirths() {
		return $this->iTotalBirths;
	}
	
	public function getTotalDeaths() {
		return $this->iTotalDeaths;
	}

	public function handleWrapperStartup(ProcessWrapper $oWrapper) {
		$this->fStartTime = microtime(true);
	}
	
	public function handleWrapperShutdown(ProcessWrapper $oWrapper) {
	}
	
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

	public function handleChildBirth(ProcessWrapper $oWrapper, ChildProcess $oChild) {
		$this->iTotalBirths++;
	}
	
	public function handleChildDeath(ProcessWrapper $oWrapper, ChildProcess $oChild) {
		$this->iTotalDeaths++;
	}
	
	public function handleChildLife(ProcessWrapper $oWrapper, ChildProcess $oChild) {
	}
}