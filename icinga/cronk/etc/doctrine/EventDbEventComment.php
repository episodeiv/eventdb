<?php

class EventDbEventComment extends BaseEventDbEventComment {
	
    public function setUp() {
        parent::setUp();

        $this->hasOne('EventDbComment', array(
             'local' => 'comment_id',
             'foreign' => 'comment_id'));

        $this->hasOne('EventDbEvent', array(
             'local' => 'event_id',
             'foreign' => 'event_id'));
    }

}