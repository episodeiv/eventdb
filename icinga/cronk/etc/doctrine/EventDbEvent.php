<?php

class EventDbEvent extends BaseEventDbEvent {
	
    public function setUp () {
        parent::setUp();

        $this->hasMany('EventDbComment as Comments', array(
             'refClass' => 'EventDbEventComment',
             'local' => 'event_id',
             'foreign' => 'comment_id'));

        $options = array (
            'created' =>  array('name'  => 'event_created'),
            'updated' =>  array('name'  => 'event_modified'),
        );

        $this->actAs('Timestampable', $options);
    }
    
    public function acknowledge($acknoledge=1) {
    	$acknoledge_ = $this->event_ack;
    	$this->event_ack = $acknoledge;
    	
    	$ret = 0;
    	
    	if ($acknoledge == 1 && $acknoledge != $acknoledge_) {
    		// 'acked'
    		$ret = 1;
    	}
    	
        if ($acknoledge == 0 && $acknoledge != $acknoledge_) {
            // revoked
            $ret = -1;
        }
        
        if ($ret != 0) {
        	$this->save();
        }

        return $ret;
    }
    
    public function comment(EventDbComment $ec) {
    	$this->Comments[] = $ec;
    	$this->save();
    }

}