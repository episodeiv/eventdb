<?php


class EventDBModelTest extends PHPUnit_Framework_TestCase {
	
	public static function setupBeforeClass() {
		self::setupFixture();
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
	
	public function testGetEventsUnfiltered() {
		$model = AgaviContext::getInstance()->getModel('EventDB.EventDB',"Cronks");
		$events = $model->getEvents();

		$this->assertTrue(is_array($events));
		$this->assertFalse(empty($events));
	}

	public function testGetEventParameters() {	
		$model = AgaviContext::getInstance()->getModel('EventDB.EventDB',"Cronks");
	
	}

	public static function tearDownAfterClass() {
		$conn = AgaviContext::getInstance()->getDatabaseManager()->getDatabase('eventdb_r')->getConnection();
		$q = Doctrine_Query::create($conn)
			->delete('EventDbEvent e')->where("program LIKE ?","test%")->execute();
	}
}
