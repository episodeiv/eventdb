<?php

class EventDB_EventDBFilterParserModel extends EventDBBaseModel {

	public function getFilterDefinition(array $filter) {	
		$this->prepareFilter($filter);
		$def = array();
		$programFilterGroup = array(
			"operator" => "AND",
			"isGroup" => true, 
			"filter" => array()
		);
		$hostFilterGroup = array(
			"operator" => "AND",
			"isGroup" => true, 
			"filter" => array()
		);

		$this->parsePatterns("host_name",$filter["hostFilter"],$hostFilterGroup["filter"]);
		$this->parsePatterns("program",$filter["programFilter"],$programFilterGroup["filter"]);
		$def[] = $hostFilterGroup;
		$def[] = $programFilterGroup;
		foreach($filter["messageFilter"]["items"] as $msg)  {
			if(!is_array($msg))
				continue;
			
			$this->parseMessage($def,$msg);
		}

		$this->buildBitMask($def,$filter["facilityExclusion"],$filter["priorityExclusion"]);
		$this->parseTimespan($def,$filter["timespan"]);
		$this->parseMisc($def,$filter["misc"]);
		$this->parseSourceExc($def,$filter["sourceExclusion"]);
	
		$fields = array( 
	//		'offset' => $filter["display"]["offset"],
			'limit' => $filter['display']['limit'],
			'order_by' => $filter["display"]["order"]["field"],	
			'dir' => $filter["display"]["order"]["dir"],
			'group_by' => $filter["display"]["group"]["field"],
			'filter' => $def
		);
		
		return $fields;
	}

	protected static $filterTemplate = array(
		'hostFilter' => array(
			'include_pattern_type' => 'disabled',
			'exclude_pattern_type' => 'disabled',
			'include_pattern' => false,
			'exclude_pattern' => false,
			'include_set' => array(),
			'exclude_set' => array()
		),
		'programFilter' => array(
			'include_pattern_type' => 'disabled',
			'exclude_pattern_type' => 'disabled',
			'include_pattern' => false,
			'exclude_pattern' => false,
			'include_set' => array(),
			'exclude_set' => array()		
		),
		'messageFilter' => array('items' => array()),
		'timespan' => array(
			'from' => -1,
			'to' => -1
		),
		'misc' => array('hideAck' => false),
		'sourceExclusion' => array(),
		'facilityExclusion' => array(),
		'priorityExclusion' => array(),
		'display' => array(
			'group' => array("field" => false),
			'order' => array("dir" => 'desc',"field" => 'modified'),
			'limit' => 25	
		)
	);
	
	protected function prepareFilter(array &$filter) {
		$filter = $filter+self::$filterTemplate; // set default vals
		if($filter["display"]["limit"] > 200)
			$filter["display"]["limit"] = 200;
	
	}

	protected function parsePatterns($field,$filter, &$def) {
		$subgroup1 = array(
			"isGroup" => true,
			"filter" => array(),
			"operator" => "OR"
		);
		$subgroup2 = array(
			"isGroup" => true,
			"filter" => array(),
			"operator" => "OR"
		);

        if(isset($filter["include_pattern_type"]) && isset($filter["include_pattern"]))
    		$this->getFilterForPattern($subgroup1["filter"],$field,$filter["include_pattern_type"],$filter["include_pattern"]);
        if(isset($filter["exclude_pattern_type"]) && isset($filter["exclude_pattern"]))
            $this->getFilterForPattern($subgroup2["filter"],$field,$filter["exclude_pattern_type"],$filter["exclude_pattern"],true);
        if(isset($filter["include_set"]))
            $this->parseSet($subgroup1["filter"],$field,$filter["include_set"]);
		if(isset($filter["exclude_set"]))
            $this->parseSet($subgroup2["filter"],$field,$filter["exclude_set"],true);

        $def[] = $subgroup1;
		$def[] = $subgroup2;
	}

	protected function parseSourceExc(array &$def,array $excl) {
		
		if(empty($excl))
			return false; 
		$def[] = array(
			'target' => 'type',
			'operator' => 'NOT IN',
			'value' => implode('|',$excl)
		);
	
	}

	protected function parseMisc(array &$def,$filter) {
		if($filter["hideAck"])
			$def[] = array(
				'target' => 'ack',
				'operator' => 50,
				'value' => 0
			);

		return false;
	}
	
	protected function parseTimespan(array &$def,array $ts) {	
	
		if($ts["from"] != -1) {
			$def[] = array(
				"target" => 'created',
				"operator" => 71, 
				"value" => date('Y-m-d H:i:s',$ts["from"])
			);
		}
		if($ts["to"] != -1) {
			$def[] = array(
				"target" => 'created',
				"operator" => 70, 
				"value" => date('Y-m-d H:i:s',$ts["to"])
			);
		}
	}
	
	protected function buildBitMask(array &$def,array $facExc, array $prioExc) {
		if(empty($facExc) && empty($prioExc))
			return false;
		$bitMask = 0x00;
		foreach($facExc as &$e) {
			$e = intval($e);	
		}
		foreach($prioExc as &$e) {
			$e = intval($e);	
		}	
		if(!empty($facExc))
			$def[] = array(
				"target" => 'facility',
				"operator" => 'NOT IN',
				"value" => implode("|",$facExc)
			);
		
		if(!empty($prioExc))
			$def[] = array(
				"target" => 'priority',
				"operator" => 'NOT IN',
				"value" => implode("|",$prioExc)
			);
	}

	protected function parseSet(array &$def,$field,array $set =  array(),$negate = false) {
		$operator = $negate ? "NOT IN" :"IN";
		if(empty($set))
			return false;
		
		$cpSet = array();
		foreach($set as $elem) {
			if(is_array($elem)) {
				foreach($elem as $e) 
					$cpSet[] = $e;
			} else 
				$cpSet[] = $elem;
		}
		$set = $cpSet;

		$def[] = array(
			"target" => $field,
			"operator" => $operator,
			"value" => implode('|',$set)	
		);
	}
	
	protected function parseMessage(array &$def,array $msgs) {
		$op;
	
		if($msgs["isRegexp"])
			$op = $msgs["type"] == "exc" ? "NOT REGEXP" : "REGEXP";
		else {
			$msgs["message"] = "%".$msgs["message"]."%";
			$op = $msgs["type"] == "exc" ? 61 : 60;
		}

		$def[] = array(
			"target" => "message",
			"operator" => $op,
			"value" => $msgs["message"]
		);
	}
	
	protected function getFilterForPattern(array &$def,$field,$type,$string,$negate = false) {
		$operator = 50;
		switch($type) {
			case "regexp":
				$operator = "REGEXP";
				break;
			case "contains":
				$string = "%".$string."%";
				$operator = $negate ? 61 : 60;
				break;
			case "exact":
				$operator = $negate ? 51 : 50;
				break;
			default:
				return false; 
		}

		$filter = array(
			"target" => $field,
			"operator" => $operator,
			"value" => $string,
			"group" => $field
		);
		$def[] = $filter;
	}
}
