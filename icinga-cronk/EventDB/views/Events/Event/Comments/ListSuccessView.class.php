<?php

class EventDB_Events_Event_Comments_ListSuccessView extends EventDBBaseView {
	
	public function executeHtml(AgaviRequestDataHolder $rd) {
		$this->setupHtml($rd);

		$this->setAttribute('_title', 'EventDB.Events.Event.Comments.List');
	}
	
	public function executeJson(AgaviRequestDataHolder $rd) {
		$e = $rd->getParameter('event');

		$comments = $e->comments->count() ? $e->comments->toArray() : array();
		
		$l = $rd->getParameter('limit', 25);
		
		if (count($comments) > $l) {
			$o = $rd->getParameter('offset', 0);
			
			for ($i=0; $i < $o; ++$i) {
				array_shift($comments);
			}
			
			for ($i=count($comments); $i < $l; ++$i) {
				array_pop($comments);
			}
		}
	    
		return json_encode(array('comments' => $comments));
	}

}
