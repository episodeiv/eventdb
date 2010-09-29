<?php

class Cronks_EventDB_Events_Comments_AddSuccessView extends CronksBaseView {
	
	public function executeHtml(AgaviRequestDataHolder $rd) {
		$this->setupHtml($rd);

		$this->setAttribute('_title', 'EventDB.Events.Comments.Add');
	}
	
	public function executeJson(AgaviRequestDataHolder $rd) {
		return json_encode(array('success' => true, 'message' => 'Event(s) updated.'));
	}
	
}