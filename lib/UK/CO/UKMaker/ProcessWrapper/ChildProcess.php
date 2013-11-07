<?php
namespace UK\CO\UKMaker\ProcessWrapper;
class ChildProcess implements \JsonSerializable {

	const STATE_RUNNING = 0;
	const STATE_STOPPED = 1;
	const STATE_EXITED_NORMALLY = 2;
	const STATE_EXITED_ABNORMALLY = 3;

	private $fStartTime;
	private $fStopTime = null;
	private $iPid;
	
	private $iState = self::STATE_RUNNING;
	private $iExitCode = 0;
	
	public function __construct($iPid) {
		$this->iPid = $iPid;
		$this->fStartTime = microtime(true);
		$this->iState = self::STATE_RUNNING;
	}
	
	public function getStartTime() {
		return $this->fStartTime;
	}
	
	/**
	* @return float The number of real seconds this process has been running
	**/
	public function getRuntime() {
		return microtime(true) - $this->fStartTime;
	}
	
	public function getPid() {
		return $this->iPid;
	}
	
	public function setState($iState) {
		$this->iState = $iState;
	}
	
	public function exitedNormally() {
		return $this->iState == self::STATE_EXITED_NORMALLY;
	}
	
	public function getState() {
		return $this->iState;
	}
	
	public function isAlive() {
		return in_array($this->iState, array(self::STATE_RUNNING, self::STATE_STOPPED));
	}
	
	public function setExitCode($iExitCode) {
		$this->iExitCode = $iExitCode;
	}
	
	public function getExitCode() {
		return $this->iExitCode;
	}
	
	public function jsonSerialize() {
		return array(
			'startTime' => $this->fStartTime,
			'state' => $this->iState,
			'exitCode' => $this->iExitCode
		);
	}
}