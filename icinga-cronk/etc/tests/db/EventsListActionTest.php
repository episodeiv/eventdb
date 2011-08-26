<?php

class EventsListActionTest extends PHPUnit_Framework_TestCase {
	public static function setupBeforeClass() {
		self::setupFixture();
		$context = AgaviContext::getInstance();
		$context->getUser()->addCredential('icinga.user');
		$context->getUser()->setAuthenticated(true);
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
	
	public function testListAction() {
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array(),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);	
		$this->assertFalse(is_null($json));
		$this->assertTrue(is_array($json["events"]),"Listing failed");
		$this->assertTrue(count($json["events"]) > 0, "Listing failed");
	}

	public function testLimitAndOffset() {
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('offset' => 0,'limit'=> 5),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);	
		$this->assertFalse(is_null($json));
		$this->assertEquals(5,count($json["events"]),"Limit filter failed"); 
	
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('offset' => 2,'limit'=> 3),
			"json",
			"write"
		);
		$result = $container->execute();
		$json_wOffset = json_decode($result->getContent(),true);
		$this->assertFalse(is_null($json_wOffset));
		$this->assertTrue($json["events"][2] == $json_wOffset["events"][0]);
	}

	public function testGroupBy() {
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('columns' =>array('count(id) as nr'),'group_by'=>'host_address'),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);	
		foreach($json["events"] as $ev) {
			$this->assertTrue(is_numeric($ev["nr"]));	
		}		
	}

	public function testExactFilter() {
		// = filter
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('filter' => array(
				array(
					"target"=>"host_name",
					"operator"=>50,
					"value"=>"localhost"
				)
				)
			),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);
		$this->assertFalse(is_null($json));
		foreach($json['events'] as $ev) {
			$this->assertEquals($ev['host_name'],'localhost',"Localhost filter didn't return localhost-only");
		}
	}

	public function textLikeFilter() {
		// 'like' filter 
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('filter' => 
				array(
					array(
						"target"=>"host_name",
						"operator"=>60,
						"value"=>"%ocal%"
					)
				)
			),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);
		foreach($json['events'] as $ev) {
			$this->assertTrue(preg_match('/.*ocal.*/',$ev['host_name'])>0,"Localhost filter didn't return localhost-only");
		}
	}

	public function testRegExpFilter() {
		// Regexp filter
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('filter' => 
				array(
					array(
						"target"=>"host_name",
						"operator"=>'REGEXP',
						"value"=>"^host\w{2,} \d{1,}"
					)
				),
			),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);
	
		$this->assertTrue(count($json['events']) > 0);
		foreach($json['events'] as $ev) {
			$this->assertTrue(preg_match("/^host\w{2,} \d{1,}/",$ev['host_name'])>0,"Localhost filter didn't return localhost-only");
		}
	}	

	public function testInFilter() {
	
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('filter' => 
				array(
					array(
						"target"=>"host_name",
						"operator"=>'IN',
						"value"=>"host 1|host 2"
					)
				),
			),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);
		$this->assertTrue(count($json['events']) > 0);
		foreach($json['events'] as $ev) {
			$this->assertTrue(in_array($ev['host_name'],array('host 1','host 2')));
		}
	} 
	
	public function testNotInFilter() {
	
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('filter' => 
				array(
					array(
						"target"=>"host_name",
						"operator"=>'NOT IN',
						"value"=>"host 1|host 2"
					)
				),
			),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);
		$this->assertTrue(count($json['events']) > 0);
		foreach($json['events'] as $ev) {
			$this->assertFalse(in_array($ev['host_name'],array('host 1','host 2')));
		}
	}

	public function testChainedFilter() {
		$container = $this->route(
			"Cronks",
			"EventDB.Events.List",
			array('filter' => 
				array(
					array(
						"target"=>"host_name",
						"operator"=>'NOT IN',
						"value"=>"host 1|host 2"
					),
					array(
						"target"=>"host_name",
						"operator"=>'REGEXP',
						"value"=>"local\w{1,} \d{1,}"
					),
					array(
						"target"=>"host_name",
						"operator"=> 60,
						"value"=>"%host%"
					)
				),
			),
			"json",
			"write"
		);
		$result = $container->execute();
		$json = json_decode($result->getContent(),true);
		$this->assertTrue(count($json["events"]) > 0);	
	}
	public static function tearDownAfterClass() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		$q = Doctrine_Query::create($conn)
			->delete('EventDbEvent e')->where("program LIKE ?","test%")->execute();
	}
}
