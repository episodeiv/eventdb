<?php

class EventDB_EventDBModel extends EventDBBaseModel {

    const COMMENT = 'comment';
    const ACKNOLEDGE = 'ack';
    const REVOKE = 'revoke';

    private $__conn_r = NULL;
    private $__conn_w = NULL;

    protected function getReadConnection() {
        if (is_null($this->__conn_r))
            $this->__conn_r = $this->getContext()->getDatabaseManager()->getDatabase("eventdb_r")->getConnection();
        return $this->__conn_r;
    }

    protected function getWriteConnection() {
        if (is_null($this->__conn_w))
            $this->__conn_w = $this->getContext()->getDatabaseManager()->getDatabase("eventdb_w")->getConnection();
        return $this->__conn_w;
    }

    public function getOperator(&$operator, &$value) {
        $negate = false;
        switch ($operator) {
            case 51:
                $operator = '!=';
                break;
            case 60:
                $operator = 'like';
                break;
            case 61:
                $operator = 'not like';
                break;
            case 70:
                $operator = '<=';
                break;
            case 71:
                $operator = '>=';
                break;
            case 'NAND':
                $negate = true;
            case 'AND':
                $operator = '&';
                break;
            case 'NOR':
                $negate = true;
            case 'OR':
                $operator = '|';
                break;
            case 'REGEXP':
                $t = strtolower($this->getReadConnection()->getDriverName());
                if (trim($t) == "oracle")
                    $operator = 'REGEXP_LIKE';
                else
                    $operator = 'REGEXP';
                $value = $this->createDbRegExp($value);
                break;
            case 'NOT REGEXP':
                $operator = 'NOT REGEXP';
                $value = $this->createDbRegExp($value);
                break;
            case 'IN':
                $operator = 'IN';
                $value = explode('|', $value);
                break;
            case 'NOT IN':
                $negate = true;
                $operator = 'IN';
                $value = explode('|', $value);
                break;
            case 50:
            default:
                $operator = '=';
                break;
        }

        return $negate;
    }

    public function buildWhereDql($filterCollection = null, array &$values, $type = "AND") {
        if (!$filterCollection)
            return false;
        $isFirst = true;
        $dql = "";

        foreach ($filterCollection as $filter) {

            $isNegative = false;
            if (!isset($filter["isGroup"])) {
                $isNegative = $this->getOperator($filter['operator'], $filter['value']);
                if ($filter['value'] === '' || is_null($filter['value']))
                    continue;
            }
            $chain = strtoupper($type);
            if ($isFirst)
                $chain = "";

            if ($isNegative === true)
                $chain = $chain . " NOT ";

            if (!isset($filter["isGroup"])) {
                if ($filter["operator"] == "REGEXP_LIKE") {
                    $dql = " " . $filter["operator"] . "(" . $filter["target"] . ", '" . $filter["value"] . "','i')";
                } else {
                    if (is_array($filter["value"])) {
                        if (count($filter["value"]) == 1)
                            $filter["value"] = $filter["value"][0];
                    }

                    if (!is_numeric($filter["value"])) {
                        $dql .= " " . $chain . " " . $filter["target"] . " " . $filter["operator"]." ";
                        // Check if we need parenthesis around the filter expression
                        if ($filter['operator'] == 'IN' && !is_array($filter['value'])) {
                            $filter['value'] = array($filter['value']);
                        }
                        if (is_array($filter["value"])) {
                            $dql .= "('" . implode("','", $filter["value"]) . "')";
                        } else {
                            $dql .= '?';
                            $values[] = $filter["value"];
                        }
                    } else {

                        if ($filter["operator"] == "REGEXP_LIKE")
                            $dql = " " . $chain . " " . $filter["operator"] . "(" . $filter["target"] . ", " . $filter["value"] . ",'i')";
                        else {
                            if ($filter["operator"] == "IN")
                                $filter["value"] = "(" . $filter["value"] . ")";
                            $dql .= " " . $chain . " " . $filter["target"] . " " . $filter["operator"] . " " . $filter["value"];
                        }
                    }
                }
            } else {



                $subDql = $this->buildWhereDql($filter["filter"], $values, $filter["operator"]);
                if ($subDql)
                    $dql .= " " . $chain . " (" . $subDql . ")";
            }
            if ($dql != "")
                $isFirst = false;
        }
        return $dql;
    }

    public static function sortByGroup($elem1, $elem2) {
        if (isset($elem1["group"]) && isset($elem2["group"]))
            return strcmp($elem1["group"], $elem2["group"]);
        if (!isset($elem1["group"]) && isset($elem2["group"]))
            return 1;
        if (isset($elem1["group"]) && !isset($elem2["group"]))
            return 1;
        return 0;
    }

    public function getEvents($default = array(), $offset = 0, $limit = false, $filter = array(
        'simple' => false,
        'target' => 'EventDbEvent',
        'order_by' => false,
        'dir' => 'desc',
        'columns' => array('*'),
        'group_by' => false,
        'filter' => false,
        'count' => 'id',
        'expanded' => false,
        'group_leader'  => false
    )) {
        $useEDBC = AgaviConfig::get("modules.eventdb.use_edbc");

        if (!is_array($filter['columns']))
            $filter['columns'] = array('*');
        $vals = array();

        $selectDql = "SELECT  " . implode(',', $filter['columns']);
        //$countDql = "SELECT  COUNT(".$filter['count'].") as __count";
        $dql = " FROM EventDbEvent";
        $wherePart = $this->buildWhereDql($filter['filter'], $vals);
        if ($useEDBC == true) {
            if (!isset($filter["group_leader"]) || $filter["group_leader"]  === false) {
                $wherePart .= ($wherePart ? " AND " : "")."  (group_id IS NULL OR group_leader = -1)";
            } else {
                $id = intval($filter["group_leader"]);
                $wherePart .= ($wherePart ? " AND " : "")."  (group_leader = $id )";
            }
        }

        if ($wherePart)
            $dql .= " WHERE " . $wherePart;

        if ($filter['group_by'] != false) {
            $dql .= " GROUP BY " . $filter['group_by'];
        }
        if ($filter['order_by'] != false) {
            $dql .= " ORDER BY " . $filter['order_by'] . " " . ($filter['dir'] == 'asc' ? 'asc' : 'desc');
        }
        //$countDql .= $dql;
        if ($limit != false) {
            $dql .= " LIMIT " . $limit;
        }
        if ($offset) {
            $dql .= " OFFSET " . $offset;
        }
        $dql = $selectDql . $dql;

        if ($filter['simple']) // required for host/program summary
            return $this->getPlainQuery($dql, "", $vals);

        $r = $this->getReadConnection()->query($dql, $vals);
        if (!$r->count()) {
            return array("values" => $default, "count" => 0);
        }

        //count = $this->getReadConnection()->query($countDql,$vals);

        $realHosts = array();
        $eventAdditional = array();
        $eventHosts = array();
        $facs = EventDBEvent::$FACILITIES;
        $prios = EventDBEvent::$PRIORITIES;
        foreach ($r as $event) {
            $eventAdditional[$event->id] = array();
            if (!is_null($event->facility))
                $event->facility = $facs[$event->facility];
            if (!is_null($event->priority))
                $event->priority = $prios[$event->priority];
            if ($event->host_address) {
                $eventAdditional[$event->id]['address'] = $event->ip_address;
            }
            if (!in_array($event->host_name, $eventHosts)) {
                $eventHosts[] = array("host" => $event->host_name, "addr" => $event->ip_address);
            }
            $event->host_address = null;
        }

        $query = IcingaDoctrine_Query::create();
        $queryDQL = "SELECT h.display_name, h.address FROM IcingaHosts h WHERE ";
        $params = array();

        foreach ($eventHosts as $entry) {
            $queryDQL .= (empty($params) ? "" : " OR ")." (h.display_name = ? OR h.address = ?)";
            $params[] = $entry["host"];
            $params[] = $entry["addr"];
        }

        $query->parseDqlQuery($queryDQL);
        $res = $query->execute($params,Doctrine::HYDRATE_ARRAY);
        $q = $r;
        $r = $q->toArray(true);

        $eventsWithComments = $this->getEventsWithComment($r);

        $q->free();
        $encodeUtf8 = $this->getReadConnection()->getCharset() === 'latin1';
        foreach ($r as &$event) {
            $event['real_host'] = false;
            $event = array_merge($event, $eventAdditional[$event['id']]);
            foreach($res as $host) {
                $hostname = $host['display_name'];
                $ipAddress = $host['address'];
                if ($event['host_name'] == $hostname) {
                    $event['real_host'] = $hostname;
                    break;
                } else if (isset($event['ip_address']) && $event['ip_address'] == $ipAddress) {
                    $event['real_host'] = $hostname;

                }
            }
            if (array_key_exists($event['id'], $eventsWithComments)) {
                $event['has_comment'] = true;
            }
            if ($encodeUtf8) {
                foreach ($event as &$value) {
                    if ($value !== null && ! ctype_digit($value)) {
                        $value = utf8_encode($value);
                    }
                }
            }
        }


        /*
          if($filter['group_by'])
          $count = $count->count();
          else
          $count = $count->getFirst()->__count;
         */
        return array("values" => $r/* , "count" => $count */);
    }

    private function getEventsWithComment(array $events)
    {
        $sql = 'SELECT DISTINCT c.event_id AS id FROM comment c WHERE c.event_id IN (';
        $ids = array();
        foreach ($events as $event) {
            $ids[] = (int) $event['id'];
        }
        $sql .= implode(', ', $ids);
        $sql .= ')';
        $res = $this->getReadConnection()->execute($sql);
        $eventIds = array();
        foreach ($res->fetchAll() as $row) {
            $eventIds[$row['id']] = true;
        }
        return $eventIds;
    }

    public function getCount($field) {

        $q = Doctrine_Query::create($this->getReadConnection())
                        ->select('COUNT(' . $field . ') as __count')
                        ->distinct()->from('EventDbEvent')->execute();
        return $q->getFirst()->__count;
    }

    public function createDbRegExp($val) {
        $val = preg_replace('/\\\d/', '[[:digit:]]', $val);
        $val = preg_replace('/\\\s/', '[[:space:]_]', $val);
        $val = preg_replace('/\\\w/', '[[:alnum:]]', $val);
        $val = preg_replace('/\\\D/', '[^[:digit:]]', $val);
        $val = preg_replace('/\\\S/', '[^[:space:]_]', $val);
        $val = preg_replace('/\\\W/', '[^[:alnum:]]', $val);

        return $val;
    }

    public function getEventById($eId) {
        $q = Doctrine_Query::create('eventdb_r')
                ->from('EventDbEvent e')
                ->where('e.id = ?', $eId);

        return $q->fetchOne();
    }

    private function getPlainQuery($requestDQL, $countDQL, $vals) {

        $r = $this->getReadConnection()->query($requestDQL, $vals, Doctrine::HYDRATE_SCALAR);
        $r = $this->reHydrateScalarResult($r);
        if (!count($r))
            return array("values" => array(), "count" => 0);
        /*
          $count = $this->getReadConnection()->query($countDQL,$vals,  Doctrine::HYDRATE_SCALAR);
          $count = $this->reHydrateScalarResult($count); */
        return array("values" => $r);
    }

    private function reHydrateScalarResult(array $r) {
        $rehydrated = array();
        foreach ($r as $event) {
            $element = array();
            foreach ($event as $field => $val) {
                $field = explode("_", $field, 2);
                $element[$field[1]] = $val;
            }
            $rehydrated[] = $element;
        }
        return $rehydrated;
    }

    public function addComment(array $comments = array()) {

        if (!$this->getContext()->getUser()->isAuthenticated())
            return false;

        try {
            $username = $this->getContext()->getUser()->getNsmUser()->user_name;
        } catch (AppKitDoctrineException $e) {
            $username = $this->getContext()->getUser()->user_name;
        }
        foreach ($comments as $comment) {
            $events = EventDbEvent::getEventByIds($comment->ids);
            foreach ($events as $event) {
                if ($comment->type == 1)
                    $event->acknowledge($comment->message, $username);
                else if ($comment->type == 2)
                    $event->revoke($comment->message, $username);
                else
                    $event->addComment($comment->message, $username);
            }
        }
    }

}
