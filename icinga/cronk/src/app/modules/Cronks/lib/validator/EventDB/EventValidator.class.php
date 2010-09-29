<?php

class EventValidator extends AgaviNumberValidator {
	
	protected function validate() {
		if (!parent::validate()) {
			$this->throwError();
			return false;
		}
		
		$edb = $this->getContext()->getModel('EventDB.EventDB', 'Cronks');
		$eId = $this->getData($this->getArgument());
		
		$e = $edb->getEventById($eId);
		
		if (!$e) {
			$this->throwError();
			return false;
		}
		
		$this->export($e);
		return true;
	}

}