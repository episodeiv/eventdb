<?php

class Cronks_EventDB_EventDBModel extends CronksBaseModel {
	
	const COMMENT = 'comment';
	const ACKNOLEDGE = 'ack';
	const REVOKE = 'revoke';
	private $__conn_r = NULL;
	private $__conn_w = NULL;

    protected function getReadConnection() {
		if(is_null($this->__conn_r)) 
			$this->__conn_r = $this->getContext()->getDatabaseManager()->getDatabase("eventdb_r")->getConnection();
		return $this->__conn_r;
	}

	protected function getWriteConnection() {
		if(is_null($this->__conn_w))
			$this->__conn_w = $this->getContext()->getDatabaseManager()->getDatabase("eventdb_w")->getConnection();
		return $this->__conn_w;
	}	

	public function getOperator(&$operator,&$value) {	
		$negate = false;
		switch ($operator) {
			case 51:
				$operator = '!=';
				break;
			case 60:
				$operator = 'like';
				break;
			case 61:
				$operator = 'not like';
				break;
			case 70:
				$operator = '<=';
				break;
			case 71:
				$operator = '>=';
				break;
			case 'NAND':
				$negate = true;
			case 'AND':
				$operator = '&';
				break;
			case 'NOR':
				$negate = true;
			case 'OR': 
				$operator = '|';
				break;
			case 'REGEXP':
				$operator = 'REGEXP';
				$value = $this->createDbRegExp($value);
				break;
			case 'NOT REGEXP':
				$operator = 'NOT REGEXP';
				$value = $this->createDbRegExp($value);
				break;	
			case 'IN':
				$operator = 'IN';
				$value = explode('|',$value);
				break;
			case 'NOT IN':
				$negate = true;
				$operator = 'IN';
				$value = explode('|',$value);
				break;
			case 50:
			default:
				$operator = '=';
				break;	
		}

		return $negate;
	}

	public function buildWhereDql($filterCollection = null,array &$values,$type = "AND") {
		if(!$filterCollection)
			return false;
		$isFirst = true;	
		
		$dql = "";	
		foreach($filterCollection as $filter) {
			$isNegative = false;	
			if(!isset($filter["isGroup"])) {	
				$isNegative = $this->getOperator($filter['operator'],$filter['value']);	
				if($filter['value'] === '' || is_null($filter['value']))
					continue;
			}
			$chain = strtoupper($type);
			if($isFirst)
				$chain = "";
		
			if($isNegative === true)
				$chain = $chain." NOT ";
			if(!isset($filter["isGroup"])) {
				if(is_array($filter["value"])) {
					if(count($filter["value"]) == 1)
						$filter["value"] = $filter["value"][0];
				}
				if(!is_numeric($filter["value"])) {
					$dql .= " ".$chain." ".$filter["target"]." ".$filter["operator"];	
					if(is_array($filter["value"])) {
						$dql .= "('".implode("','",$filter["value"])."')";
						
					} else {
						$dql .= '?';
						$values[] = $filter["value"];
					}
				} else {
					if($filter["operator"] == "IN")
						$filter["value"] = "(".$filter["value"].")";
					$dql .= " ".$chain." ".$filter["target"]." ".$filter["operator"]." ".$filter["value"];	
				}
			} else {
	
				$subDql = $this->buildWhereDql($filter["filter"],$values,$filter["operator"]); 
				if($subDql)
					$dql .= " ".$chain." (".$subDql.")";
			}
			if($dql != "")
				$isFirst = false;
		}
			
		return $dql;
	}
	
	public static function sortByGroup($elem1,$elem2) {
		if(isset($elem1["group"]) && isset($elem2["group"]))
			return strcmp($elem1["group"],$elem2["group"]);
		if(!isset($elem1["group"]) && isset($elem2["group"]))
			return 1;
		if(isset($elem1["group"]) && !isset($elem2["group"]))
			return 1;
		return 0;
	}
	
    public function getEvents($default=array(), $offset=0, $limit=false,
        $filter = array(
            'target' => 'EventDbEvent',
            'order_by' => false,
			'dir' => 'desc',
			'columns' => array('*'),
            'group_by' => false,
            'filter' => false,
			'count' => 'id'
        )) {
		
		if(!is_array($filter['columns']))
			$filter['columns'] = array('*');
    	$vals = array();
    	
		$selectDql = "SELECT DISTINCT ".implode(',',$filter['columns']);
		$countDql = "SELECT DISTINCT COUNT(".$filter['count'].") as __count";
		$dql =" FROM EventDbEvent";
		$wherePart = $this->buildWhereDql($filter['filter'],$vals);
 		if($wherePart)
			$dql .= " WHERE ".$wherePart;

    	if ($filter['group_by'] != false) {
    		$dql .= " GROUP BY ".$filter['group_by'];
    	}
     	if($filter['order_by'] != false) {	
			$dql .= " ORDER BY ".$filter['order_by']." ".($filter['dir'] == 'asc' ? 'asc' : 'desc');
		}
		$countDql .= $dql;
		if ($limit != false) {
    		$dql .= " LIMIT ".$limit;
    	}  
		if($offset) {
			$dql .= " OFFSET ".$offset;	
		}
    	$dql = $selectDql.$dql;
		
		$r = $this->getReadConnection()->query($dql,$vals);
    	if (!$r->count()) {
    		return array("values" => $default, "count" => 0);
    	}
   		
		$count = $this->getReadConnection()->query($countDql,$vals);
		
		$realHosts = array();
    	$eventAdditional = array();
    	$eventHosts = array();
		$facs = EventDBEvent::$FACILITIES;
		$prios = EventDBEvent::$PRIORITIES;
    	foreach ($r as $event) {	
        	$eventAdditional[$event->id] = array(); 	
			if(!is_null($event->facility))	
				$event->facility = $facs[$event->facility];
			if(!is_null($event->priority))
				$event->priority = $prios[$event->priority];
			if($event->host_address) {
        		$eventAdditional[$event->id]['address'] = $event->ip_address;
			}
			if (!in_array($event->host_name, $eventHosts)) {
    			$eventHosts[] = $event->host_name;
  			}
			$event->host_address = null;
		}
		
        $API = $this->getContext()->getModel('Icinga.ApiContainer', 'Web');

        $search = @$API->createSearch()->setSearchTarget(IcingaApi::TARGET_HOST);
        $search->setResultColumns(array('HOST_NAME'));
        $search->setSearchGroup(array('HOST_NAME'));
        
        $filterGroup = $search->createFilterGroup(IcingaApi::SEARCH_OR);
        foreach ($eventHosts as $host) {
        	$filterGroup->addFilter($search->createFilter('HOST_NAME', $host, IcingaApi::MATCH_EXACT));
        }
       
		$search->setSearchFilter($filterGroup);

        $search->setResultType(IcingaApiSearch::RESULT_ARRAY);
        $res = $search->fetch()->getAll();
   
		$q = $r;
        
		$r = $q->toArray(true);
       
		$q->free();
        foreach($r as &$event) {
			$event['real_host'] = false;
        	$event = array_merge($event,$eventAdditional[$event['id']]);
			foreach ($res as $host) {
        		$hostname = $host['HOST_NAME'];
	       
				if ($event['host_name'] == $hostname) {
	        		$event['real_host'] = true;
	        		break;
	        	}
        	}
        }
  		if($filter['group_by'])
			$count = $count->count();
		else
			$count = $count->getFirst()->__count;

        return array("values" => $r, "count" => $count);
    }
   
	public function getCount($field) {
	
		$q = Doctrine_Query::create($this->getReadConnection())
			->select('COUNT('.$field.') as __count')
			->distinct()->from('EventDbEvent')->execute();
		return $q->getFirst()->__count;
	}

   	public function createDbRegExp($val) {
		$val = preg_replace('/\\\d/','[[:digit:]]',$val);
		$val = preg_replace('/\\\s/','[[:space:]_]',$val);
		$val = preg_replace('/\\\w/','[[:alnum:]]',$val);
		$val = preg_replace('/\\\D/','[^[:digit:]]',$val);
		$val = preg_replace('/\\\S/','[^[:space:]_]',$val);
		$val = preg_replace('/\\\W/','[^[:alnum:]]',$val);
		
		return $val;
	}

    public function getEventById($eId) {
    	$q = Doctrine_Query::create('eventdb_r')
    	->from('EventDbEvent e')
    	->where('e.id = ?', $eId);
    	
    	return $q->fetchOne();
    }
    
    public function addComment(array $comments = array()) {
    	
		if (!$this->getContext()->getUser()->isAuthenticated())
			return false;
		$username;
		try {
			$username = $this->getContext()->getUser()->getNsmUser()->user_name;
		} catch( AppKitDoctrineException $e) {
			$username = $this->getContext()->getUser()->user_name;
		}
		foreach($comments as $comment) {
			$events = EventDbEvent::getEventByIds($comment->ids);
			foreach($events as $event) {
				if($comment->type == 1)
					$event->acknowledge($comment->message,$username);
				else if($comment->type == 2)
					$event->revoke($comment->message,$username);
				else
					$event->addComment($comment->message,$username);
	}
		}
    }
}
