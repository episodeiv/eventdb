<?php

class EventDB_IndexSuccessView extends EventDBBaseView {
	
	public function executeHtml(AgaviRequestDataHolder $rd) {
        $this->setupHtml($rd);

        $this->setAttribute('_title', 'EventDB.Index');
	}

}