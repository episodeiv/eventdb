<?php

class Cronks_EventDB_IndexSuccessView extends CronksBaseView {
	
	public function executeHtml(AgaviRequestDataHolder $rd) {
        $this->setupHtml($rd);

        $this->setAttribute('_title', 'EventDB.Index');
	}

}