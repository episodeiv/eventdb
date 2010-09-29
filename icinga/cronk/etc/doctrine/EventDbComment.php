<?php

class EventDbComment extends BaseEventDbComment {
	
    public function setUp () {
        parent::setUp();
        
        $this->hasOne('NsmUser as Author', array(
             'local' => 'comment_user_id',
             'foreign' => 'user_id'));

        $this->hasMany('EventDbEvent', array(
             'refClass' => 'EventDbEventComment',
             'local' => 'comment_id',
             'foreign' => 'event_id'));

        $options = array (
            'created' =>  array('name'  => 'comment_created'),
            'updated' =>  array('name'  => 'comment_modified'),
        );

        $this->actAs('Timestampable', $options);
    }
    
    public function toArray($deep=false, $prefixKey=false) {
    	$array = parent::toArray($deep, $prefixKey);
    	$array['comment_author'] = $this->Author->user_name;
    	return $array;
    }

}