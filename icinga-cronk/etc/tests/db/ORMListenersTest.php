<?php

class ORMListenersTest extends PHPUnit_Framework_TestCase {

	public static function setUpBeforeClass() {
	
//		AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_w')->getConnection()->beginTransaction();
	}

	public function testGetReadConnection() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		
		$this->assertFalse(is_null($conn));
	}

	public function testAddressResolving() {
	
		$record = new EventDbEvent();
		$address1 = $record->resolveAddress("127.0.0.1");
	//	$address2 = $record->resolveAddress("::ffff:127.0.0.1");
		
//		$this->assertEquals($address1, $address2);
//		$this->assertEquals(pack("v*",0,0,0,0xffff,0x7f,0,0,1),$address2);
	
		//..and the other way round:
		
		$address1 = $record->getAddressFromBinary($address1);
//		$address2 = $record->getAddressFromBinary($address2);
		$this->assertEquals("127.0.0.1",$address1);
	//	$this->assertEquals("::ffff:127.0.0.1",$address2);
	}

	/**
	* @depends testGetReadConnection
	*
	**/
	public function testDataInsertion() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_w')->getConnection();
		$event = new EventDbEvent();
			$event->ip_address = "192.168.178.1";	
			$event->priority = "NOTICE";
			$event->facility = 5;
			$event->host_name = 'localhost_TEST';
			$event->type = 0;
			$event->program = "ping";
			$event->message = "Just a test event";
		$event->prepareWrite();	
		$event->save();
		$event->delete($conn);			
		$this->setupFixture($conn);
	}

	protected function setupFixture(Doctrine_Connection $conn) {
		$fixturePath = dirname(__FILE__);
		$fixture = $fixturePath."/fixture.ini";
		$this->assertTrue(is_readable($fixture));
		$ini = parse_ini_file($fixture,true);
		foreach($ini as $host) {
			$event = new EventDbEvent();
			foreach($host as $field=>$value) {
				if($field)
					$event->{$field} = $value; 
			}	
			$event->save($conn);
		}	
	}
	
	/**
	* @depends testDataInsertion
	*
	**/
	public function testDataRetrieval() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_w')->getConnection();
		$q = Doctrine_Query::create($conn)->select('*')->from('EventDbEvent e')->where("program LIKE ?","test%");
		$e = $q->execute();	
		$this->assertEquals($q->count(),10);
	}

	/**
	* @depends testDataRetrieval
	*
	**/
	public function testDataSearch() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_w')->getConnection();
		$q = Doctrine_Query::create($conn)
			->select('*')
			->from('EventDbEvent e')
			->where("host_address = ?", EventDbEvent::resolveAddress("127.0.0.1"))
			->andWhere("program LIKE ?","test%");
		$e = $q->execute()->getFirst();	
		$this->assertTrue(is_object($e));
		
	}

	public function testAddComments() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_w')->getConnection();
		$q = Doctrine_Query::create($conn)
			->select('*')
			->from('EventDbEvent e')
			->where("host_address = ?", EventDbEvent::resolveAddress("127.0.0.1"))
			->andWhere("program LIKE ?","test%");

		$e = $q->execute()->getFirst();
		$e->addComment("Testcomment", "user1");
	//	print_r($q->execute()->getFirst()->toArray());	
		
	}
	
	public function testReadComments() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		$q = Doctrine_Query::create($conn)
			->select('*')
			->from('EventDbEvent e')
			->where("host_address = ?", EventDbEvent::resolveAddress("127.0.0.1"))
			->andWhere("program LIKE ?","test%");

		$e = $q->execute()->getFirst();
		$this->assertTrue(is_object($e->comments->getFirst()));

		$this->assertTrue($e->comments->getFirst()->message == "Testcomment");
	}

	public function testDeleteComments() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		$q = Doctrine_Query::create($conn)
			->select('*')
			->from('EventDbEvent e')
			->where("host_address = ?", EventDbEvent::resolveAddress("127.0.0.1"))
			->andWhere("program LIKE ?","test%");

		$e = $q->execute()->getFirst();
		$this->assertTrue(is_object($e->comments->getFirst()));
		$this->assertTrue($e->comments->getFirst()->message == "Testcomment");
		$e->deleteComment($e->comments->getFirst());
			
		$this->assertEquals($e->comments->count(),0);
	}

	public function testAcknowledgeEvent() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		$q = Doctrine_Query::create($conn)
			->select('*')
			->from('EventDbEvent e')
			->where("host_address = ?", EventDbEvent::resolveAddress("127.0.0.1"))
			->andWhere("program LIKE ?","test%");

		$e = $q->execute()->getFirst();
		$e->acknowledge('test','Test comment');		
	
		
	}

	public static function tearDownAfterClass() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		$q = Doctrine_Query::create($conn)
			->delete('EventDbEvent e')->where("program LIKE ?","test%")->execute();
//		AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_w')->getConnection()->rollback();
	}	
}
