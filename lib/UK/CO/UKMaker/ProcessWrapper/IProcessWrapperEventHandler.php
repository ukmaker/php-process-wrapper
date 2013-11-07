<?php
namespace UK\CO\UKMaker\ProcessWrapper;
/**
* Event handlers can be registered with the ProcessWrapper to handle events in the
* lifecycle of the wrapper and child processes.
**/
interface IProcessWrapperEventHandler {

	public function handleWrapperStartup(ProcessWrapper $oWrapper);
	public function handleWrapperShutdown(ProcessWrapper $oWrapper);
	
	public function handleWrapperTick(ProcessWrapper $oWrapper);

	public function handleChildBirth(ProcessWrapper $oWrapper, ChildProcess $oChild);
	public function handleChildDeath(ProcessWrapper $oWrapper, ChildProcess $oChild);
	public function handleChildLife(ProcessWrapper $oWrapper, ChildProcess $oChild);
	
}