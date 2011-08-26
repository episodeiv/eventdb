<?php
/*require_once '/usr/local/icinga-web/lib/doctrine/lib/Doctrine.php';

spl_autoload_register("Doctrine::autoload");*/

putenv("ORACLE_HOME=/usr/lib/oracle/xe/app/oracle/product/10.2.0/server/");
putenv("ORACLE_SID=XE");
require_once('/usr/local/icinga-web/lib/agavi/src/agavi.php');

$includes = array(
		
		'/app/modules/AppKit/lib/AppKit.class.php'
	);



if(isset($arguments['environment'])) {
	$env = $arguments['environment'];
	unset($arguments['environment']);
} else {
	$env = 'testing';
}
include('config.php');
Agavi::bootstrap('test');
AgaviConfig::set('core.default_context', $env);

foreach($includes as $include)
	require_once('/usr/local/icinga-web/'.$include);


// Initialize the appkit framework
AgaviController::initializeModule('Web');
AgaviController::initializeModule('AppKit');
AgaviConfig::set('core.context_implementation', 'AppKitAgaviContext');

$ctx = AgaviContext::getInstance();
$ctx->getDatabaseManager()->getDatabase()->connect();

$nr = stdin("Nr of entries to generate (*10)");
if(!is_numeric($nr)) {
	throw new Exception("Invalid nr provided");	
}
$nr *= 10;
$event;
$conn_r = $ctx->getDatabaseManager()->getDatabase("eventdb_r")->getConnection();

$dql = "SELECT DISTINCT * FROM EventDbEvent e WHERE (NOT (e.prival & 16)) AND (host_name IN ('host 1') OR host_name LIKE '%oracle%') ORDER BY e.prionr desc LIMIT 200 OFFSET 200)"; 
$count = "SELECT COUNT(id) as __c FROM EventDbEvent";
for($i = 0;$i<$nr;$i++) {
  
	$event = new EventDbEvent();
		$event->ip_address = getRandomIP();
		$event->priority = rand(0,7);
		$event->facility = rand(0,23);
		$event->host_name =getRandomHost();;
		$event->type = rand(0,2);
		$event->program = getRandomProgram();
		$event->message = getRandomMessage();
		$event->prepareWrite();	
		$event->save();
	$event->free();
	unset($event);
/*
	if($i%1000 == 0) {
		$start = microtime();
		$conn_r->query($dql);
		$c = $conn_r->query($count)->getFirst()->__c;
		
		$dur = microtime()-$start;
		file_put_contents("result.txt",$c.";".$dur."\n",FILE_APPEND);
	}*/
}

function getRandomIP() {
	$t = rand(0,1); // IPv4 or IPv6
	if($t) {
		$b = rand(0,255).".".rand(0,255).".".rand(0,255).".".rand(0,255);
	} else {
		$b = dechex(rand(0,0xffff));
		for($i=0;$i<7;$i++) {
			$b .=":".dechex(rand(0,0xffff));
		}	
	}
	
	
	return $b;
}

function getRandomHost() {
	$types = array('cluster','mysql','oracle','storage','share','workstation','firewall','www','mail');
	$post = array('int','ext','bak','test');
	return $types[rand(0,count($types)-1)]."-".$post[rand(0,count($post)-1)];
	
}

function getRandomProgram() {
	$programs = array('mail','ping','load','test','accessCheck','cms');
	return $programs[rand(0,count($programs)-1)];
}

function getRandomMessage() {
	$templates = array('Random test Event '.rand(0,0xffff),'Its a test, nr '.rand(0,0xfffff),'I am random '.rand(0,0xfffff));;
	$template =  $templates[rand(0,count($templates)-1)];
//	for($i =0;$i<10;$i++)
//		$template .= $template;
	return $template; 
}


function stdin($prompt = "", $args = array(),$default=null) {
	$inp = fopen("php://stdin","r");
	$result;
	$argsString = (!empty($args) ? '['.implode("/",$args).']' : '');
	$defString = ($default ? "($default)" : '');
	$error = false;
	do {
		$error = false;
		// get input
		echo $prompt." ".$argsString." ".$defString;
		$result = fscanf($inp,"%s\n");	
		
		if(!empty($args) && !in_array($result[0],$args,true))
			$error = true;
	} while($error);
	
	return $result[0];
}
