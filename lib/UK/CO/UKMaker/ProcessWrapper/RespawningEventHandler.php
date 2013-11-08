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

    /**
     * @param int $iRespawn
     * @param int $iRespawnDelayMs
     * @param int $iFixedChildLimit
     */
    public function __construct($iRespawn, $iRespawnDelayMs = 1000, $iFixedChildLimit = 1) {
		$this->setRespawn($iRespawn);
		$this->iRespawnDelayMs = $iRespawnDelayMs;
		$this->iFixedChildLimit = $iFixedChildLimit;
	}

    /**
     * @param int $iRespawn
     * @throws \Exception
     */
    public function setRespawn($iRespawn) {
		if(!in_array($iRespawn, array(self::RESPAWN_NEVER, self::RESPAWN_FIXED))) {
			throw new \Exception('Illegal argument to setRespawn: '.$iRespawn);
		}
		
		$this->iRespawn = $iRespawn;
	}

    /**
     * @return int
     */
    public function getRespawn() {
		return $this->iRespawn;
	}

    /**
     * @param int $iDelayMs
     */
    public function setRespawnDelayMs($iDelayMs) {
		$this->iRespawnDelayMs = $iDelayMs;
	}

    public function getRespawnDelayMs() {
		return $this->iRespawnDelayMs;
	}

    /**
     * @param int $iLimit
     */
    public function setFixedChildLimit($iLimit) {
		$this->iFixedChildLimit = $iLimit;
	}
	
	public function getFixedChildLimit() {
		return $this->iFixedChildLimit;
	}

    /**
     * @param ProcessWrapper $oWrapper
     */
    public function handleWrapperStartup(ProcessWrapper $oWrapper) {
	}

    /**
     * @param ProcessWrapper $oWrapper
     */
    public function handleWrapperShutdown(ProcessWrapper $oWrapper) {
	}

    /**
     * @param ProcessWrapper $oWrapper
     * @param ChildProcess $oChild
     */
    public function handleChildBirth(ProcessWrapper $oWrapper, ChildProcess $oChild) {
		
		$this->fLastSpawnTime = microtime(true);
		
		if($this->fLastSpawnTime > $this->fLastScheduledDelivery) {
			$this->fLastScheduledDelivery = $this->fLastSpawnTime;
		}
	}

    /**
     * @param ProcessWrapper $oWrapper
     * @param ChildProcess $oChild
     */
    public function handleChildDeath(ProcessWrapper $oWrapper, ChildProcess $oChild) {
	}

    /**
     * @param ProcessWrapper $oWrapper
     */
    public function handleWrapperTick(ProcessWrapper $oWrapper) {
	
		if($this->iRespawn == self::RESPAWN_NEVER) {
			return;
		}
		
		if($this->iRespawn == self::RESPAWN_FIXED && $oWrapper->getLiveChildCount() < $this->iFixedChildLimit) {
			$this->incubateChild($oWrapper);
		}
	}

    /**
     * @param ProcessWrapper $oWrapper
     * @param ChildProcess $oChild
     */
    public function handleChildLife(ProcessWrapper $oWrapper, ChildProcess $oChild) {
	}

    /**
     * @param ProcessWrapper $oWrapper
     */
    protected function incubateChild($oWrapper) {
	
		$fDelivery = $this->fLastScheduledDelivery + (((float)$this->iRespawnDelayMs) / 1000);
		$oWrapper->info("Scheduling one child for delivery at $fDelivery");
		$oWrapper->incubate($fDelivery);
		$this->fLastScheduledDelivery = $fDelivery;
	}
}