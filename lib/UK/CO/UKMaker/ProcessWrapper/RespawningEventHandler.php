<?php
namespace UK\CO\UKMaker\ProcessWrapper;
/**
* Monitors births and deaths and attempts to keep the number of running processes
* within defined limits
**/
class RespawningEventHandler implements IProcessWrapperEventHandler {

	/**
	* Never respawn a child
	**/
	const RESPAWN_NEVER = 0;
	
	/**
	* Respawn a new child to keep the number of children fixed
	**/
	const RESPAWN_FIXED = 1;
	
	private $iFixedChildLimit;

	private $iRespawn = self::RESPAWN_NEVER;
	
	private $iRespawnDelayMs;
	private $fLastSpawnTime;
	private $fLastScheduledDelivery = 0;
	
	public function __construct($iRespawn, $iRespawnDelayMs = 1000, $iFixedChildLimit = 1) {
		$this->setRespawn($iRespawn);
		$this->iRespawnDelayMs = $iRespawnDelayMs;
		$this->iFixedChildLimit = $iFixedChildLimit;
	}
	
	public function setRespawn($iRespawn) {
		if(!in_array($iRespawn, array(self::RESPAWN_NEVER, self::RESPAWN_FIXED))) {
			throw new \Exception('Illegal argument to setRespawn: '.$iRespawn);
		}
		
		$this->iRespawn = $iRespawn;
	}
	
	public function getRespawn() {
		return $this->iRespawn;
	}
	
	public function setRespawnDelayMs($iDelayMs) {
		$this->iRespawnDelayMs = $iDelayMs;
	}
	
	public function getRespawnDelayMs() {
		return $this->iRespawnDelayMs;
	}
	
	public function setFixedChildLimit($iLimit) {
		$this->iFixedChildLimit = $iLimit;
	}
	
	public function getFixedChildLimit() {
		return $this->iFixedChildLimit;
	}

	public function handleWrapperStartup(ProcessWrapper $oWrapper) {
	}
	
	public function handleWrapperShutdown(ProcessWrapper $oWrapper) {
	}
	
	public function handleChildBirth(ProcessWrapper $oWrapper, ChildProcess $oChild) {
		
		$this->fLastSpawnTime = microtime(true);
		
		if($this->fLastSpawnTime > $this->fLastScheduledDelivery) {
			$this->fLastScheduledDelivery = $this->fLastSpawnTime;
		}
	}
	
	public function handleChildDeath(ProcessWrapper $oWrapper, ChildProcess $oChild) {
	}
	
	public function handleWrapperTick(ProcessWrapper $oWrapper) {
	
		if($this->iRespawn == self::RESPAWN_NEVER) {
			return;
		}
		
		if($this->iRespawn == self::RESPAWN_FIXED && $oWrapper->getLiveChildCount() < $this->iFixedChildLimit) {
			$this->incubateChild($oWrapper);
		}
	}
	
	public function handleChildLife(ProcessWrapper $oWrapper, ChildProcess $oChild) {
	}
		
	protected function incubateChild($oWrapper) {
	
		$fDelivery = $this->fLastScheduledDelivery + (((float)$this->iRespawnDelayMs) / 1000);
		$oWrapper->info("Scheduling one child for delivery at $fDelivery");
		$oWrapper->incubate($fDelivery);
		$this->fLastScheduledDelivery = $fDelivery;
	}
}