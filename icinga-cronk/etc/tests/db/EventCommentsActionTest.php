<?php

class EventCommentsActionTest extends PHPUnit_Framework_TestCase {
	public static function setupBeforeClass() {
		self::setupFixture();
		$context = AgaviContext::getInstance();
		$context->getUser()->addCredential('icinga.user');
		$context->getUser()->setAuthenticated(true);
		$context->getUser()->user_name = "test!";
	}

	protected static function setupFixture() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		$fixturePath = dirname(__FILE__);
		$fixture = $fixturePath."/fixture.ini";
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

	public function route($module,$route,array $args = array(), $type =null,$action=null) {
		$rd = null;
		if(!empty($args))
			$rd = new AgaviRequestDataHolder();

		foreach($args as $key=>$val) {
			$rd->setParameter($key,$val);	
		}
		return AgaviContext::getInstance()->getController()->createExecutionContainer($module,$route,$rd,$type,$action);
	}
	
	public function testCommentEvent() {
		$q = Doctrine_Query::create()
			->select('id')
			->from('EventDbEvent')
			->where('program LIKE ?','test%')->execute();
		$comments = array(
			array(
				'ids' => array($q[1]->id,$q[4]->id),
				'message' => 'testcomment',
				'type' => 0
			),	
			array(
				'ids' => array($q[3]->id,$q[5]->id),
				'message' => 'testcomment 2',
				'type' => 0
			),
			array(
				'ids' => array($q[8]->id,$q[1]->id),
				'message' => 'testcomment 4',
				'type' => 0
			),
			array(
				'ids' => array($q[7]->id,$q[0]->id),
				'message' => 'testcomment 6',
				'type' => 0
			),
		);
		
		$json_in = json_encode($comments);
		$container = $this->route(
			"Cronks",
			"EventDB.Events.Comments.Add",
			array("comments" => $json_in),
			"json",
			"write"
		);
		$result = $container->execute();
		$result = json_decode($result->getContent());
		$this->assertTrue($result->success);	
	}
	
	public function testFetchComments() {
		$q = Doctrine_Query::create()
			->select('id')
			->from('EventDbEvent')
			->where('program LIKE ?','test%')->execute();
		$hostsToTest = array();
		foreach($q as $elem) {
			$hostsToTest[] = $elem->id;	
		}
		$hostsToTest = array_unique($hostsToTest);
		$comments = EventDbEvent::getCommentsForEventIds($hostsToTest);
		$this->assertEquals(8,$comments->count());
	}
	
	public function testAcknowledgeEvents() {
		$q = Doctrine_Query::create()
			->select('id')
			->from('EventDbEvent')
			->where('program LIKE ?','test%')->execute();
		$comments = array(
			array(
				'ids' => array($q[0]->id,$q[1]->id,$q[5]->id),
				'message' => 'test ack &4633€ comment',
				'type' => 1
			)
		);
		
		$json_in = json_encode($comments);
		$container = $this->route(
			"Cronks",
			"EventDB.Events.Comments.Add",
			array("comments" => $json_in),
			"json",
			"write"
		);
		$result = $container->execute();
		$result = json_decode($result->getContent());
		$this->assertTrue($result->success);		
	}
	
	public function testFetchAcknowledged() {
		EventDbEvent::prepareRead();
		$q = Doctrine_Query::create()
			->select('id')
			->from('EventDbEvent')
			->where('program LIKE ?','test%')
			->andWhere('ack = ?',true)
			->execute();
 		$this->assertEquals(3,$q->count());
		foreach($q as $acked) {
			$this->assertEquals($acked->comments[count($acked->comments)-1]->message,'[ACK] test ack &4633€ comment');	
		}
	}
	
	public function testRevokeEvents() {
		$q = Doctrine_Query::create()
			->select('id')
			->from('EventDbEvent')
			->where('program LIKE ?','test%')->execute();
		$comments = array(
			array(
				'ids' => array($q[0]->id,$q[1]->id,$q[5]->id),
				'message' => 'test revoke',
				'type' => 2
			)
		);
		
		$json_in = json_encode($comments);
		$container = $this->route(
			"Cronks",
			"EventDB.Events.Comments.Add",
			array("comments" => $json_in),
			"json",
			"write"
		);
		$result = $container->execute();
		$result = json_decode($result->getContent());
		$this->assertTrue($result->success);
	}
	
	public function testFetchRevoked() {
		EventDbEvent::prepareRead();
		$q = Doctrine_Query::create()
			->select('id')
			->from('EventDbEvent')
			->where('program LIKE ?','test%')
			->andWhere('ack = ?',true)
			->execute();
 		$this->assertEquals(0,$q->count());
		foreach($q as $revoked) {
			$this->assertEquals($revoked->comments[count($revoked->comments)-1]->message,'[REVOKE]test revoke');	
		}	
	}

	public function testCommentFetchAction() {
		$q = Doctrine_Query::create()
			->select('id')
			->from('EventDbEvent')
			->where('program LIKE ?','test%')->execute();
		$container = $this->route("Cronks","EventDB.Events.Event.Comments.List",array("event"=>$q[0]->id),'json');
		$result = $container->execute();	
		$json = json_decode($result->getContent());
		$this->assertFalse(is_null($json),"Invalid JSON returned");
		$this->assertTrue(count($json->comments) > 0);
	}

	public static function tearDownAfterClass() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		$q = Doctrine_Query::create($conn)
			->delete('EventDbEvent e')->where("program LIKE ?","test%")->execute();
	}

}
