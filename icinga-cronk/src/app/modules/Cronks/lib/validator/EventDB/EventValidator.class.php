<?php

class EventValidator extends AgaviNumberValidator {
	
	protected function validate() {

		if (!parent::validate()) {
			$this->throwError();
			return false;
		}
	
		$eId = $this->getData($this->getArgument());
		$ids = EventDbEvent::getEventByIds(array($eId));	
		
		if ($ids->count() == 0) {
			$this->throwError();
			return false;
		}
	
		$this->export($ids->getFirst());
		return true;
	}

}
