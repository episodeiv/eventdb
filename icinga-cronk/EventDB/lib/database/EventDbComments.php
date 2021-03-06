<?php
try {
	
	Doctrine_Manager::getInstance()->getConnection('eventdb_r');
	Doctrine_Manager::getInstance()->bindComponent('EventDbComments','eventdb_r');
} catch(Exception $e) {
}
/**
 * EventDbComments
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    ##PACKAGE##
 * @subpackage ##SUBPACKAGE##
 * @author     ##NAME## <##EMAIL##>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */

class EventDbCommentListener extends Doctrine_Record_Listener {

	public function preInsert(Doctrine_Event $event) {		
		$record = $event->getInvoker();
		$this->preUpdate($event);
	}

	public function preSave(Doctrine_Event $event) {
		$this->preInsert($event);
	}	
	
	public function preDelete(Doctrine_Event $event) {
		$record = $event->getInvoker();
		$record->prepareWrite();
	}
	
	public function preUpdate(Doctrine_Event $event) {
		$record = $event->getInvoker();
		$record->prepareWrite();
	}
	public function finalize(Doctrine_Event $event) {	
		$record = $event->getInvoker();
		$record->prepareRead();
	}
	public function postSave(Doctrine_Event $event) {
		$this->finalize($event);
}
	public function postDelete(Doctrine_Event $event) {
		$this->finalize($event);	
	}
	public function postUpdate(Doctrine_Event $event) {
		$this->finalize($event);	
	}
	public function postInsert(Doctrine_Event $event) {
		$this->finalize($event);	
	}

}


class EventDbComments extends BaseEventDbComments
{
	static public $TYPES = array(
		'COMMENT' => 0,
		'ACK'	  => 1,
		'REVOKE'  => 2
	);
		
	public function prepareWrite() {	
		Doctrine_Manager::getInstance()->bindComponent('EventDbComment','eventdb_w');
	}
	
	public function prepareRead() {
		Doctrine_Manager::getInstance()->bindComponent('EventDbCommennt','eventdb_r');
	}

	public function setUp()
    {
		$this->prepareRead();	
		parent::setUp();
		$this->addListener(new EventDbCommentListener());
    }
}
