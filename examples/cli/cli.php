<?php

use UK\CO\UKMaker\ProcessWrapper\ProcessWrapper;
use UK\CO\UKMaker\ProcessWrapper\ChildProcess;
use UK\CO\UKMaker\ProcessWrapper\IProcessWrapperEventHandler;
use UK\CO\UKMaker\ProcessWrapper\RespawningEventHandler;
use UK\CO\UKMaker\ProcessWrapper\CLI;

$sRoot = __DIR__.'/../../lib/UK/CO/UKMaker/ProcessWrapper';

require_once $sRoot."/ProcessWrapper.php";
require_once $sRoot."/ChildProcess.php";
require_once $sRoot."/IProcessWrapperEventHandler.php";
require_once $sRoot."/RespawningEventHandler.php";
require_once $sRoot."/CLI.php";

class DemoProcess extends ChildProcess {

	public function run() {
	
		for($i=0; $i<20000; $i++) {
			echo "Child ".$this->getPid()." loop $i\n";
			for($j=0;$j<100000; $j++) {}
			sleep(10);
		}
		
		return 1;
	}
}
	


$fnCreate = function($iChildId) {

	return new DemoProcess($iChildId);
	
};

$iInitialChildren = 4;
$iMaxChildren = 10;

$oWrapper = new ProcessWrapper($iInitialChildren, $iMaxChildren, $fnCreate);

$oCLI = new CLI($oWrapper);

$oCLI->run();