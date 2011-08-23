<?php

class CommentValidator extends AgaviStringValidator {
	
	protected function validate() {
		if (!parent::validate()) {
			$this->throwError();
			return false;
		}
	
		$eventIds = array();
		
		$eId = $this->getData($this->getArgument());
		
		$comments = json_decode($eId);
		foreach($comments as $comment) {
			if(!isset($comment->ids)) {
				$this->throwError();
				return false;
			}
			if(!isset($comment->message)) {
				$this->throwError();
				return false;
			}
			if(!isset($comment->type)) {
				$this->throwError();
				return false;
			}		
			if(!is_array($comment->ids))
				$comment->ids = array($comment->ids);
			$eventIds = array_merge($eventIds,$comment->ids);
		}
		$eventIds = array_unique($eventIds);
		EventDbEvent::prepareRead();
		$idsFromDb = Doctrine_Query::create()
			->select('id')
			->from('EventDbEvent')
			->whereIn('id',$eventIds)
			->execute(null,Doctrine_Core::HYDRATE_ARRAY);
		
		$this->flatten($idsFromDb);
		$diff = array_diff($eventIds,$idsFromDb);


		if(count($diff) > 0)
			$this->throwError();	
		
		$this->export($comments);
		return true;
	}
	protected function flatten(&$arr) {
		foreach($arr as $key => &$elem) {	
			$arr[$key] = $elem["id"];
		}	
	}
}

