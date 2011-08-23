<?php

class EventDB_Events_Comments_AddAction extends EventDBBaseAction {
    
	public function getDefaultViewName() {
        return 'Success';
    }
    
    public function executeRead(AgaviParameterHolder $rd) {
        return $this->getDefaultViewName();
    }
    
    public function executeWrite(AgaviParameterHolder $rd) {
    	$edb = $this->getContext()->getModel('EventDB', 'EventDB');
    	$edb->addComment($rd->getParameter('comments'));
    	
        return $this->getDefaultViewName();
    }

    public function isSecure() {
        return true;
    }
    
    public function getCredentials() {
        return array ('icinga.user');
    }
    
    public function handleError(AgaviRequestDataHolder $rd) {
        return 'Error';
    }

}
