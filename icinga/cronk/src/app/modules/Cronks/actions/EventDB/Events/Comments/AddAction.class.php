<?php

class Cronks_EventDB_Events_Comments_AddAction extends CronksBaseAction {
    
	public function getDefaultViewName() {
        return 'Success';
    }
    
    public function executeRead(AgaviParameterHolder $rd) {
        return $this->getDefaultViewName();
    }
    
    public function executeWrite(AgaviParameterHolder $rd) {
    	$edb = $this->getContext()->getModel('EventDB.EventDB', 'Cronks');
    	$edb->addComment(
    	   $rd->getParameter('events'),
    	   $rd->getParameter('event_ack', array()),
    	   $rd->getParameter('comment', '')
    	);
    	
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