<?php
/**
* Example script to demonstrate use of the ProcessWrapper
**/
use UK\CO\UKMaker\ProcessWrapper\ProcessWrapper;
use UK\CO\UKMaker\ProcessWrapper\ChildProcess;
use UK\CO\UKMaker\ProcessWrapper\IProcessWrapperEventHandler;
use UK\CO\UKMaker\ProcessWrapper\RespawningEventHandler;

$sRoot = __DIR__.'/../../lib/UK/CO/UKMaker/ProcessWrapper';

require_once $sRoot."/ProcessWrapper.php";
require_once $sRoot."/ChildProcess.php";
require_once $sRoot."/IProcessWrapperEventHandler.php";
require_once $sRoot."/RespawningEventHandler.php";
require_once $sRoot."/CLI.php";

$aParams = array(
	'host' => 'ubuntu-G',
	'port' => 5672,
	'vhost' => '/',
	'login' => 'guest',
	'password' =>'guest'
);

$sExchangeBase = "ex-ctl-test";
$sQueueBase = "q-ctl-test";
$sStatusExchange = "ex-ctl-status";
$sStatusQueue = "q-ctl-status";

class ControlMessage {

	const REPORT_STATUS = "reportStatus";
	const CREATE_CHILD = "createChild";
	const KILL_CHILD = "killChild";
	const SET_CHILD_COUNT = "setChildCount";
}

$oConnection = new \AMQPConnection($aParams);
$oConnection->connect();

$oChannel = new \AMQPChannel($oConnection);

$oExchange = new \AMQPExchange($oChannel);
$oExchange->setName($sExchangeBase);
$oExchange->setType(AMQP_EX_TYPE_FANOUT);
$oExchange->declareExchange();

$oQueue = new \AMQPQueue($oChannel);
$oQueue->setName($sQueueBase);
$oQueue->setFlags(0);
$oQueue->declareQueue();
$oQueue->bind($oExchange->getName(), null);
	
$oStatusExchange = new \AMQPExchange($oChannel);
$oStatusExchange->setName($sStatusExchange);
$oStatusExchange->setType(AMQP_EX_TYPE_FANOUT);
$oStatusExchange->declareExchange();

$oStatusQueue = new \AMQPQueue($oChannel);
$oStatusQueue->setName($sStatusQueue);
$oStatusQueue->setFlags(0);
$oStatusQueue->declareQueue();
$oStatusQueue->bind($oStatusExchange->getName(), null);
	
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

$oRespawner = new RespawningEventHandler(RespawningEventHandler::RESPAWN_FIXED, 10, $iInitialChildren);
$oWrapper->addEventHandler($oRespawner);

$fnParentControlHandler = function(\AMQPEnvelope $oEnvelope, \AMQPQueue $oQueue) use ($oWrapper, $oRespawner, $oStatusExchange) {

	/**
	* The Parent handles the following messages
	**/
	if(strpos($oEnvelope->getBody(), ':') !== false) {
		list($sMethod, $sArg) = explode(':', $oEnvelope->getBody());
	} else {
		$sMethod = $oEnvelope->getBody();
	}
	
	switch($sMethod) {
		case ControlMessage::REPORT_STATUS:
			
			$aStatus = $oWrapper->getChildren();
			$oStatusExchange->publish(json_encode($aStatus), null);
			break;
			
		case ControlMessage::CREATE_CHILD:
		
			$oChild = $oWrapper->startChild();
			break;
			
		case ControlMessage::KILL_CHILD:
		
			$aStatus = $oWrapper->stopChild($sArg);
			break;
			
		case ControlMessage::SET_CHILD_COUNT:
			$oRespawner->setFixedChildLimit($sArg);
			break;
			
	}
	
	/**
	* We never want the consumer to stop, so return true
	**/
	return true;
};




$oWrapper->startAll();
$oConsumer = new \AMQPConsumer($oQueue, $fnParentControlHandler);
$oConsumer->basicConsume(AMQP_AUTOACK);

$aConsumers = array($oConsumer);

$oDispatcher = new \AMQPConsumerDispatcher($aConsumers);

while($oDispatcher->hasConsumers()) {
	$oConsumer = $oDispatcher->select(1);
	if($oConsumer !== null) {
		if(!$oConsumer->consumeOne()) {
			$oDispatcher->removeConsumer($oConsumer);
		}
	}
	
	if(!$oWrapper->dispatch()) {
		// Time to die
		exit;
	}
}