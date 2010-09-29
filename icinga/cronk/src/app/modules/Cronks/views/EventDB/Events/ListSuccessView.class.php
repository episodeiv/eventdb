<?php

class Cronks_EventDB_Events_ListSuccessView extends CronksBaseView {
	
	public function executeHtml(AgaviRequestDataHolder $rd) {
		$this->setupHtml($rd);

		$this->setAttribute('_title', 'EventDB.Events.List');
	}
	
	public function executeJson(AgaviRequestDataHolder $rd) {
		$edb = $this->getContext()->getModel('EventDB.EventDB', 'Cronks');
		
		return json_encode(array(
			'events' => $edb->getEvents(array(),
				$rd->getParameter('offset', 0),
				$rd->getParameter('limit', false),
				array(
				    'target' => $rd->getParameter('target', 'EventDbEvent'),
				    'column' => $rd->getParameter('column', '*'),
				    'group_by' => $rd->getParameter('group_by', false),
				    'filter' => $rd->getParameter('filter', false)
				)
		)));
	}

}