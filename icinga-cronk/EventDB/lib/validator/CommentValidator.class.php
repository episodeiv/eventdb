<?php

class CommentValidator extends AgaviStringValidator {

    protected function validate() {
        if (!parent::validate()) {
            $this->throwError();
            return false;
        }

        $eventIds = array();

        $eId = $this->getData($this->getArgument());

        $comments = json_decode($eId);
        foreach ($comments as $comment) {
            if (!isset($comment->ids)) {
                if(!isset($comment->filter)) {
                    $this->throwError();
                    return false;
                } else {
                    $comment->ids = $this->getCommentIdsByFilter($comment->filter);
                }                
                
            }
            if (!isset($comment->message)) {
                $this->throwError();
                return false;
            }
            if (!isset($comment->type)) {
                $this->throwError();
                return false;
            }
            if (!is_array($comment->ids))
                $comment->ids = array($comment->ids);
            $eventIds = array_merge($eventIds, $comment->ids);
        }
        $eventIds = array_unique($eventIds);
        EventDbEvent::prepareRead();
        $idsFromDb = Doctrine_Query::create()
                ->select('id')
                ->from('EventDbEvent')
                ->whereIn('id', $eventIds)
                ->execute(null, Doctrine_Core::HYDRATE_ARRAY);

        $this->flatten($idsFromDb);
        $diff = array_diff($eventIds, $idsFromDb);


        if (count($diff) > 0)
            $this->throwError();

        $this->export($comments);
        return true;
    }

    private function getCommentIdsByFilter($filter) {
        $filterParser  = $this->getContext()->getModel("EventDBFilterParser","EventDB");
        $edb  =  $this->getContext()->getModel("EventDB","EventDB");
        $filterArray = $filterParser->getFilterDefinition(json_decode($filter,true));
        $filterArray["columns"] = array("id");
        $filterArray["simple"] = false;
        $events = $edb->getEvents(array(),null,null,$filterArray);
        $ids = array();
        foreach($events["values"] as $event) {
            $ids[] = $event["id"];
        }
        return $ids;
    }
    
    protected function flatten(&$arr) {
        foreach ($arr as $key => &$elem) {
            $arr[$key] = $elem["id"];
        }
    }

}

