<?php

class Cronks_EventDB_EventDBModel extends CronksBaseModel {
	
	const COMMENT = 'comment';
	const ACKNOLEDGE = 'ack';
	const REVOKE = 'revoke';
    
    public function getEvents($default=array(), $offset=0, $limit=false,
        $filter = array(
            'target' => 'EventDbEvent',
            'column' => '*',
            'group_by' => false,
            'filter' => false
        )) {
    	$q = Doctrine_Query::create()
    	->select($filter['column'])
    	->from($filter['target'])
    	->offset($offset);
    	
    	if ($filter['filter']) {
    		$where = true;
    		
    		foreach ($filter['filter'] as $filter_) {
    			$o = '';
    			switch ($filter_['operator']) {
    				case 51:
    					$o = '!=';
    					break;
    				case 60:
    					$o = 'like';
    					break;
    				case 61:
    					$o = 'not like';
    					break;
    				case 70:
    					$o = '<=';
    					break;
    				case 71:
    					$o = '>=';
    					break;
    				case 50:
    				default:
                        $o = '=';
                        break;
    			}
    			if ($where) {
    				$q->where($filter_['target'] . " $o ?", $filter_['value']);
    				$where = false;
    			} else {
    				$q->andWhere($filter_['target'] . " $o ?", $filter_['value']);
    			}
    		}
    	}
    	
    	if ($limit !== false) {
    		$q->limit($limit);
    	}
    	
    	if ($filter['group_by'] !== false) {
    		$q->groupBy($filter['group_by']);
    	}
    	
    	$r = $q->execute();
    	
    	if (!$r->count()) {
    		return $default;
    	}
    	
    	$realHosts = array();
    	$eventHosts = array();
    	
    	foreach ($r as $event) {
    		if (!in_array($event->event_host, $eventHosts)) {
    			$eventsHosts[] = $event->event_host;
    		}
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
        
        $r = $r->toArray(true);
        
        foreach($r as &$event) {
        	$event['real_host'] = false;
        	foreach ($res as $host) {
        		$hostname = $host['HOST_NAME'];
	        	if ($event['event_host'] == $hostname) {
	        		$event['real_host'] = true;
	        		break;
	        	}
        	}
        }
    	
    	
        return $r;
    }
    
    public function getEventById($eId) {
    	$q = Doctrine_Query::create()
    	->from('EventDbEvent e')
    	->where('e.event_id = ?', $eId);
    	
    	return $q->fetchOne();
    }
    
    public function addComment($events=array(), $ack=array(), $comment='') {
    	if ($this->getContext()->getUser()->isAuthenticated()) {
    		
    		$ecs = array();

    	    foreach ($events as $event) {
    	    	$ack_ = 0;
    	    	
    	    	if (array_key_exists($event->event_id, $ack)) {
                    $ack_ = $event->acknowledge($ack[$event->event_id]);
    	    	}

                $cType = null;
                
                switch ($ack_) {
                	case 0:
                		$cType = self::COMMENT;
                		break;
                	case 1:
                		$cType = self::ACKNOLEDGE;
                		break;
                	case -1:
                		$cType = self::REVOKE;
                		break;
                }
                
                $ec = null;
                
                if (array_key_exists($cType, $ecs)) {
                	$ec = $ecs[$cType];
                } else {
                	if ($comment) {
		                $ec = new EventDbComment();
		                $ec->comment_msg = $comment;
		                $ec->comment_type = $cType;
		                $ec->Author = $this->getContext()->getUser()->getNsmUser();
		                $ec->save();
		                
		                $ecs[$cType] = $ec;
                	} else {
                		$ecs[$cType] = null;
                	}
                }
       
                if ($ec) {
                    $event->comment($ec);
                }
            }	    	
    	}
    }

}