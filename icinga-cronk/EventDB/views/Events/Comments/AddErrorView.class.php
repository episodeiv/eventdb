<?php

class EventDB_Events_Comments_AddErrorView extends EventDBBaseView {
    
    public function executeHtml(AgaviRequestDataHolder $rd) {
        $this->setupHtml($rd);

        $this->setAttribute('_title', 'EventDB.Events.Comments.Add');
    }
    
    public function executeJson(AgaviRequestDataHolder $rd) {
    	return json_encode(array('success' => false, 'errorMessage' => 'Test'));
    }
    
}