<?php
/**
 * NETWAYS eventdb for nagios.
 *
 * Script displaying database entries from syslogng.
 * Extended through types (syslog, snmptt).
 *
 * Copyright (C) 2007, NETWAYS GmbH <www.netways.de>, Marius Hein
 *
 * $Id: index.php 71 2009-10-26 12:39:55Z wpreston $
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 */

 /* Changes:
  * 
  * 2009-08-26: William Preston, NETWAYS GmbH
  *             - added a start date field to hide older events
  *             - additional style definitions
  * 2009-06-11: Martin Fuerstenau <mf_at_maerber.de>
  *             - Added the ability to list program from the frontend. It can be
  *               be delivered by all agents but was not accessable using the frontend
  *             - Added td.critical and td.down in style definitions. Due to the fact 
  *               that not all agents are using syslog-ng there is no need to use
  *               the error levels from syslog (err,emerg,crit,info etc.) The agent can
  *               deliver directly the Nagios ones (critical,down). 
  */

/**
 * Init
 * ----
 */

// Checking to include styles, javascript or display the image ;-)
if (check_get_key('css')) {
	html_css();
}
elseif (check_get_key('js')) {
	html_js();
}
elseif (($img = check_get_key('image'))) {
	html_image($img);
}

// Init the time stack and prepare args
timestack ('script', false, 'Start script');
$a = array_merge($_GET, $_POST);

// ----

/**
 * Configuration
 * -------------
 */

timestack ('config', false, 'Parsing the config');

// Application
cset('app.title', 'NETWAYS eventdb');
cset('app.version', '1.3');

// Database
cset('db.user', 'eventdb');
cset('db.pass', 'eventdb');
cset('db.host', 'localhost');
cset('db.name', 'eventdb');

// If other fields except the uid are needed in the FE without displaying
cset('db.additionalfields', Array () );

// Available order arguments
cset('db.orderbyfields', Array ('datetime', 'priority', 'facility', 'host', 'uid'));
cset('db.orderfields', Array ('ASC' => 'Ascending', 'DESC' => 'Descending'));
cset('db.startdatefields', Array ('6 hour'  => '6 hours' , '12 hour' => '12 hours' ,
				  '1 day'   => '1 day'   , '2 day'   => '2 days'   , '7 day'   => '1 week',
				  '1 month' => '1 month' , '2 month' => '2 months' , '6 month' => '6 months',
				  '1 year'  => '1 year'  ));

// Default ordering
cset('db.orderby', 'datetime');
cset('db.order', 'DESC');
cset('db.startdate', '2 day');

// Page
cset('page.maxrows', 20);
cset('page.maxpagelinks', 20);

cset('page.encoding', Array (
	'input' 	=> 'ISO-8859-1',
	'output'	=> 'UTF-8',
));

// The visible fieldlist
cset('page.fields', Array (
  'acknowledged'	=> '<a href="#" onclick="markAll(\'ack[]\');">rev</a>',
  'uid'				=> 'ID',
  'type'			=> 'Type',
  'host'			=> 'Host',
  'facility'		=> 'Facility',
  'priority'		=> 'Priority',
  'datetime'		=> 'Datetime',
  'program'		=> 'Program',
  'message'			=> 'Message'
));

// Hiddenfields and link persistence

// Persistence from form to link
cset('page.pvars', Array (
  'offset',
  'type',
  'host',
  'facility',
  'order',
  'orderby',
  'message',
  'regexp',
  'priority',
  'displayrows',
  'displayack',
  'event_id',
  'message_notext',
  'message_notexists',
  'startdate'
));

// Persistence from link fro form
cset('page.hiddenfields', Array ('offset', 'event_id') );

cset('page.func', Array ('acknowledged' => 'func_acknowledged'));

// Form
cset('form.name', 'frm_eventdb');
cset('form.action', url_self());
cset('form.method', 'post');
cset('form.enctype', 'application/x-www-form-urlencoded');

// Ack comment types
cset('lang.ctypes', Array (
	'rev' 		=> 'revoked',
	'ack' 		=> 'acknowledged',
	'notspec'	=> 'not specified comment'
));

// Allow owerwriting from args
c_overwrite();

timestack (false, 'config');

// -------------

// Database query
timestack ('db', false, 'fetching data');
db_get_pk();
if (check_to_ack($a)) {
  db_make_ack($a['author'], $a['comment'], $a['ack'], $a['nack']);
  http_redir();
}

if (check_add_comment($a)) {
  db_add_comment($a['author'], $a['comment'], $a['event_id'], 'comment');
  http_redir();
}

$arr = get_data(
  array(cget('db.orderby'), cget('db.order')),
  $a['type'], $a['host'], $a['offset'], $a['facility'], $a['priority'],
  $a['message'], $a['regexp'], $a['displayack'], $a['message_notext'],
  $a['message_notexists'], cget('db.startdate')
);
timestack (false, 'db');

// HTML Display
timestack ('html', false, 'processing html');

/**
 *  Displaying the page ...
 */

// HTTP headers
http_header();

// HTML header
html_header(cget('app.title'));

// Page header (image and things ...)
html_page_header();

// Starting the form
html_data_form(true);

// Filtertable
html_form_filter();

// Displaying the data
html_data_table($arr['data'], $arr['count']);

// Display the comments ...
if (isset($a['event_id']) && check_event_id($a['event_id'])) {
  html_comments_box($a['event_id']);
  html_form_acknowledge('Add new comment');
}
else { // Or acknowledge
  html_form_acknowledge('acknowledge');
}

// Mkay, closing the form
html_data_form();

// Closing the to timestacks for displaying times ...
timestack (false, 'html');
timestack (false, 'script');

// Display
html_timestack();

// Closing html tags
html_footer();

// Default exit ...
exit (0);

/**
 * Functions
 * ---------
 */

function get_data
  (
    $order = array(),
    $type_arr = array(),
    $host_arr=array(),
    $offset=false,
    $facility_arr = array(),
    $priority_arr = array(),
    $message = false,
    $regexp = false,
    $displayack = false,
    $msg_is_empty = false,
    $msg_not_exists = false,
    $startdate = false
  )
{

  $parts = Array ();

  if (is_array($host_arr)) {
    foreach ($host_arr as $host) {
      if (!$host) continue;
      $parts[] = Array (
        'field' => 'host',
        'value' => $host,
        'op'	=> '=',
        'agg'	=> 'or'
      );
    }
  }

  if (is_array($type_arr)) {
  	$i = false;
  	foreach ($type_arr as $type) {
      if (!$type) continue;
      $parts[] = Array (
        'field' => 'type',
        'value' => $type,
        'op'	=> '=',
        'agg'	=> 'or',
        'newblock' => !$i
      );
      $i=true;
  	}
  }

  if (is_array($facility_arr)) {
    $i = false;
    foreach ($facility_arr as $facility) {
      if (!$facility) continue;
      $parts[] = Array (
        'field' => 'facility',
        'value' => $facility,
        'op'	=> '=',
        'agg'	=> 'or',
        'newblock' => !$i
      );
      $i=true;
    }
  }

  if (is_array($priority_arr)) {
    $i = false;
    foreach ($priority_arr as $priority) {
      if (!$priority) continue;
      $parts[] = Array (
        'field' => 'priority',
        'value' => $priority,
        'op'	=> '=',
        'agg'	=> 'or',
        'newblock' => !$i
      );
      $i=true;
    }
  }

  if (!$msg_is_empty) {
    if ($message && strlen(strval($message))>0) {
      $message = preg_replace('@\*@', '%', $message);
      $parts[] = Array (
        'field' => 'message',
        'value' => $message,
        'op'	=> $msg_not_exists ? 'NOT LIKE' : 'LIKE',
        'agg'	=> 'and',
        'newblock' => true,
      );
    }

    if (regexp && strlen(strval($regexp))>0) {
      $parts[] = Array (
        'field' => 'message',
        'value' => $regexp,
        'op'	=> $msg_not_exists ? 'NOT REGEXP' : 'REGEXP',
        'agg'	=> 'and',
        'newblock' => true,
      );
    }
  }
  else {

      $parts[] = Array (
        'field' => 'message',
        'value' => '',
        'op'	=> '=',
        'agg'	=> 'or',
        'newblock' => true,
      );

      $parts[] = Array (
        'field' => 'message',
        'value' => '__NULL__',
        'op'	=> 'IS',
        'agg'	=> 'or',
        'newblock' => false,
      );

  }

  if (!$displayack) {
    $parts[] = Array (
      'field' => 'acknowledged',
      'value' => '0',
      'op'	=> '=',
      'add'	=> 'and',
      'newblock' => true,
    );
  }

  if ($startdate) {
    $parts[] = Array (
      'field' => 'datetime',
      'value' => '',
      'op'	=> '> date_sub(now(), interval '.$startdate.') and \'\'=',
      'add'	=> 'and',
      'newblock' => true,
    );
  }

  // var_dump($parts);

  $limit = sprintf('%d, %d', $offset, cget('page.maxrows'));

  $count = db_query(
    sql_select(Array('COUNT(*) as count'), 'events', $parts)
  );

  if (is_array($count) && $count[0]['count']) {
    $count = $count[0]['count'];
  }
  else {
    $count = false;
  }

  if (is_array($order) && count($order) == 2) {
  if ($order[0] && $order[1]) {
    $order = sprintf('%s %s', $order[0], $order[1]);
  }
  else {
    $order = false;
  }
  }
  else {
    $order=false;
  }

  $sql = sql_select(db_get_fieldlist(), 'events', $parts, $limit, $order);

  // var_dump($sql);

  $arr = db_query(
    $sql
  );

  return Array('count' => $count, 'data' => $arr);
}

function get_orderby_fields() {
  $out = array ();
  foreach (cget('db.orderbyfields') as $val) {
    $out[$val] = $val;
  }
  return $out;
}

function get_order_fields() {
  return cget('db.orderfields');
}

function get_startdate_fields() {
  return cget('db.startdatefields');
}

function db_get_fieldlist() {
	static $fields = NULL;

	if ($fields == NULL) {
		$fields = array_keys(array_flip(array_merge(
			Array (db_get_pk()),
			array_keys(cget('page.fields')),
			cget('db.additionalfields')
		)));
	}

	return $fields;
}

function db_get_distinct_single($field) {
  $sql = sql_select(Array('DISTINCT '. $field), 'events', false, false, $field. ' ASC');
  $arr = array ();

  foreach (db_query($sql) as $row) {
    $arr[$row[$field]] = $row[$field];
  }

  return $arr;
}

function db_get_hosts() {
  return db_get_distinct_single('host');
}

function db_get_facilities() {
  return db_get_distinct_single('facility');
}

function db_get_types() {
  return db_get_distinct_single('type');
}


function db_get_priorities() {
  return db_get_distinct_single('priority');
}

function db_get_pk() {
	static $PK = NULL;
	if ($PK === NULL) {
		$arr = db_query('SHOW COLUMNS FROM events');
		foreach ($arr as $row) {
			if ($row['Key'] == 'PRI') {
				$PK = $row['Field'];
				break;
			}
		}
	}

	return $PK;
}

function db_make_ack($author, $comment, $uids, $del_uids = array()) {

  if (!is_array($uids)) $uids = array();
  if (!is_array($del_uids)) $del_uids = array();

  $diff_del = array_diff($del_uids, $uids);
  if (is_array($diff_del) && count($diff_del)>0) {
  	$re = NULL;
  	foreach ($diff_del as $del_uid) {
  		$re += db_query(sql_update(Array('acknowledged' => 0), 'events', 'uid='. $del_uid));
	  	if ($re && strlen($author)>0 && strlen($comment)>0) {
		    db_add_comment($author, $comment, $del_uid, 'rev');
	  	}
  	}
	// Check $re?
  }

  $diff_add = array_diff($uids, $del_uids);
  if (is_array($diff_add) && count($diff_add)>0) {
	  $re = NULL;
	  foreach ($diff_add as $add_uid) {
	  	$re = db_query(sql_update(Array('acknowledged' => 1), 'events', 'uid='. $add_uid));
	  	if ($re && strlen($author)>0 && strlen($comment)>0) {
		    db_add_comment($author, $comment, $add_uid, 'ack');
	  	}
	  }
  }

  return true;
}

function db_add_comment($author, $comment, $id, $type = 'notspec') {
  if (check_event_id($id)) {
      $sql_insert = sql_insert(
        Array(
          'event_fk'=> $id,
          'type'	=> $type,
          'author'	=> $author,
          'comment'	=> $comment,
          'crdate'	=> strftime('%Y-%m-%d %X', time())
        ), 'comments'
      );

      return db_query($sql_insert);
  }
}

function sql_update($fields, $table, $where) {
  if (!$where) return false;
  $sql = sprintf('UPDATE %s SET ', $table);
  $parts = Array ();
  foreach ($fields as $name=>$value) {
    $parts[]= sprintf(' %s = \'%s\'', $name, $value);
  }
  $sql .= implode(', ', $parts);
  $sql .= ' WHERE '. $where;
  $sql .= ';';

  return $sql;
}

function sql_insert($fields, $table) {
  $sql = sprintf(
    'INSERT INTO %s(%s) VALUES(%s);',
    $table,
    implode(', ', array_keys($fields)),
    '\''. implode('\', \'', array_values($fields)). '\''
  );

  return $sql;
}

function sql_select($fields, $table, $where=false, $limit=false, $order=false, $join=false) {
  $sql = false;

  $sql = sprintf('SELECT %s FROM %2$s%3$s', implode(', ', $fields), $table, $join!=false ? ' '. $join. ' ' : false);

  if ($where != false) {
    $sql .= ' WHERE ';
    if (is_array($where)) {
      $sql .= sql_query_parts($where);
    }
    else {
      $sql .= $where;
    }
  }

  if ($order != false) {
    $sql .= sprintf(' ORDER BY %s', $order);
  }

  if ($limit != false) {
    $sql .= sprintf(' LIMIT %s', $limit);
  }

  // var_dump($sql);

  return $sql;
}

function sql_query_parts($array) {
  $sql = false;
  $i = 0;

  foreach ($array as $part) {

    if ($i > 0) {
      if ($part['newblock']) {
        $sql .= 'AND';
      }
      else {
        $sql .= $part['agg'];
      }
    }

    if ($array[$i-1]['agg'] != $part['agg'] || $part['newblock']) $sql .= ' (';


  if (strtolower($part['op']) == 'regexp') {
    $sign = '"';
  }
  elseif ($part['value'] == '__NULL__') {
    $sign = false;
    $part['value'] = 'NULL';
  }
  else {
    $sign = '\'';
  }

    $sql .= sprintf(' %1$s %2$s %4$s%3$s%4$s ',
      $part['field'],
      $part['op'],
      $part['value'],
      $sign
    );

    if ($array[$i+1]['agg'] != $part['agg'] || $array[$i+1]['newblock'] || !is_array($array[$i+1])) $sql .= ') ';

    $i++;
  }

  return $sql;
}

function db_init() {
  global $db;
  $db = @mysql_pconnect(
    cget('db.host'),
    cget('db.user'),
    cget('db.pass')
  );

  if (!$db) {
    mkerror(mysql_error(), mysql_errno(), 'DB');
  }

  if (!mysql_select_db(cget('db.name'), $db)) {
    mkerror(mysql_error(), mysql_errno(), 'DB');
  }

  return $db;
}

function db_conn() {
  static $db = NULL;

  if ($db == NULL) {
    $db = db_init();
  }

  return $db;
}

function db_query($sql) {
  $res = @mysql_query($sql, db_conn());
  if (!$res) {
    mkerror(mysql_error(), mysql_errno(), 'DB');
  }

  elseif (($num = @mysql_num_rows($res)) > 0) {
    return db_res2array($res);
  }

  elseif (($num = @mysql_insert_id(db_conn())) > 0) {
    return $num;
  }

  elseif (($num = @mysql_affected_rows(db_conn())) > 0) {
    return $num;
  }

  return false;
}

function db_res2array($res) {
  if ($res) {
    $tmp = array ();
    while ($row = mysql_fetch_assoc($res)) {
      $tmp[] = $row;
    }

    mysql_free_result ($res);

    return $tmp;
  }

  return false;
}

function check_to_ack($a) {
  return ($a['make_ack']
  // && strlen(strval($a['comment']))>0
  // && strlen(strval($a['author']))>0
  // && is_array($a['ack'])
  // && count($a['ack']) > 0
  && !$a['event_id']);
}

function check_add_comment($a) {
  return ($a['make_ack']
  && strlen(strval($a['comment']))>0
  && strlen(strval($a['author']))>0
  && $a['event_id']);
}

function check_event_id($id) {
  if (is_numeric($id) && intval($id) > 0) {
    $parts = Array(Array(
      'field' => db_get_pk(),
      'value' => $id,
      'op' => '=',
      'agg' => 'and',
      'newblock' => true
    ));

    $arr = db_query(sql_select(Array('COUNT(*) as count'), 'events', $parts));
    if (is_array($arr) && $arr[0]['count'] > 0) {
      return true;
    }
  }
  return false;
}

function check_get_key($key) {
	if (is_array($_GET) && array_key_exists($key, $_GET) && strlen(strval($re = $_GET[$key]))>0) {
		return $re;
	}
	return false;
}

function func_acknowledged($key, $value, $uid) {
  $id = sprintf('ack-%s-%s-%s', $key, $value, $uid);

  $out = NULL;

  $out .= html_widget_checkbox('ack[]', false, $uid, ($value == 1 ? $uid : false), ($GLOBALS['a']['event_id'] ? array('disabled'=>'disabled'):false));

  if ($value == 1) {
    $out .= html_link(
    	sprintf(
			'<img border="0"' .
			' alt="comment" title="view comments"' .
			' width="11" height="10" ' .
			'src="%1$s?image=comment.png" />', url_self()
		),
    	Array('event_id' => $uid)
    );
    $out .= html_widget_input('nack[]', $uid, array(), 'hidden');
  }

  return $out;
}

function html_header($title) {
$head = <<<END
<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC
	"-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<title>%1\$s</title>

	<meta name="author" content="NETWAYS GmbH" />
	<meta name="description" content="NETWAYS eventdb - capture events for nagios" />

	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
	<meta http-equiv="expires" content="0" />
	<meta http-equiv="cache-control" content="no-cache" />
	<meta http-equiv="pragma" content="no-cache" />

	<link rel="stylesheet" type="text/css" media="all" href="%2\$s?css=1" />

	<script language="Javascript" type="text/javascript" src="%2\$s?js=1">
	<!-- /* <![CDATA[ */
		// Scriptsource
	/* ]]> */ -->
	</script>
</head>
<body onload="loadTitleBoxStates();">
END;

  printf($head,
	  $title,
	  url_self()
  );

  return true;
}

function html_footer() {
  printf(
  '</body>' .
  '</html>'
  );
  return true;
}

function html_get_value_class($key, &$value) {

    $classes = array();

    switch ($key) {
      case 'facility':

      break;

      case 'datetime':
		$classes[] = 'nowrap';
      break;

      case 'message':
      	if (!$value) {
      		$classes[] = 'empty';
      		$value = '<span class="null">&lt;NULL&gt;</span>';
      	}
      break;

      case 'acknowledged':
        $classes[] = 'nowrap';
        if ($value == 1) $classes[] = 'ack_ok';
      break;

      default:
        $classes[] = $key;
        $classes[] = $value;
      break;

    }

    return count($classes)>0 ? implode(' ', $classes) : false;
}

function html_page_header() {
  printf(
    '<a href="http://www.netways.de" target="_blank">' .
    '<img alt="NETWAYS GmbH" title="NETWAYS EventDB" border="0" src="%s?image=netways.png" width="230" height="43" /></a>',
    url_self()
  );

  $anchors = Array (
    'Filter' => 'eventdb-box-form_filter',
    'Data' => 'eventdb-box-data-dable',
    'Comments' => 'eventdb-box-show-comment',
    'Ack / Add' => 'eventdb-box-form_ack',
  );

  $larr = Array ();

  $larr[] = sprintf('<a href="%1$s">Reset</a>', $_SERVER['SCRIPT_NAME']);
  $larr[] = html_link('Reload', Array ());

  foreach ($anchors as $name=>$anchor) {
  	if ($anchor == 'eventdb-box-show-comment' && !$GLOBALS['a']['event_id']) continue;

  	$larr[] = sprintf('<a href="#%1$s">%2$s</a>', $anchor, $name);
  }

  printf('<div>%s</div>', implode('&nbsp;-&nbsp;', $larr));
}

function html_data_table($arr, $count) {

  echo html_box_start('data-dable', 'Data');

  if (is_array($arr) && count($arr)>0) {

    html_table_header($count);

    html_page_browser($count);

    printf('<table class="datatable">');

    printf('<tr><th>%s</th></tr>', implode('</th><th>', array_values(cget('page.fields'))));


    $i = 0;
    foreach ($arr as $row) {
      printf('<tr class="%s%s">', $i%2 ? 'even' : 'odd', $GLOBALS['a']['event_id'] == $row[db_get_pk()] ? ' mark' : false);
      foreach (cget('page.fields') as $key=>$field) {
        if (array_key_exists($key, $row) && strlen(strval($row[$key]))>0) {
        	$value =& $row[$key];
        }
        else {
        	$value = '<field not on resultset>';
        }

        $value = preg_replace('@\n+$@', '', $value);
        $value = preg_replace('@\n@', '<br />', $value);

        $class = html_get_value_class($key, $value);

        if (is_array(cget('page.func')) && ($func = cget(sprintf('page.func.%s', $key))) != false && is_callable($func)) {
          $value = call_user_func($func, $key, $value, $row[db_get_pk()]);
        }

		EncodeString($value);

        printf('<td%2$s>%1$s</td>', $value ? $value : '&nbsp;', $class ? sprintf(' class="%s"', $class) : false);
      }
      printf('</tr>');
      $i++;
    }


    printf('</table>');

  }
  else {
    printf('<br /><b>%s</b>', 'No matching data was found!');
  }

  echo html_box_stop();

}

function html_page_browser($count) {

  // RowsPerPage
  $rpp = cget('page.maxrows');

  // Max Pages
  $mp = MaxPages($count);
  if ($mp < 1) return false;

  // The Offset
  $o = $GLOBALS['a']['offset'] ? $GLOBALS['a']['offset'] : 0;

  // Act page num
  $page_act = $o/$rpp;

  // Max page links
  $links_max = cget('page.maxpagelinks')-1;

  // All the links
  $links = array ();

  // First and Prev
  $links[] = html_link('First', Array ('offset' => 0), 'page_link');
  $links[] = html_link('Prev', Array ('offset' => $o>$rpp ? $o-$rpp : '0'), 'page_link');
  $links[] = '[';

  // Startpage
  if ($mp < $links_max) {
    $pages_start = 0;
  }
  else {
    $pages_start =  (floor($page_act / $links_max) * $links_max );
    if ($pages_start > $mp - $links_max) $pages_start = $mp-$links_max;
  }

  // Stoppage
  $pages_stop = $pages_start + $links_max;

  if ($pages_stop > $mp) $pages_stop = $mp;

  for ($i=$pages_start; $i<=$pages_stop; $i++) {
    if ($page_act == $i) {
      $class = 'page_link page_act';
      $wleft = false;
      $wright = false;
    }
    else {
      $class = 'page_link';
      $wleft = false;
      $wright = false;
    }

    $links[] = sprintf('%s%s%s', $wleft, html_link(sprintf('%02d', $i+1), Array ('offset' => $i*$rpp), $class), $wright);
  }

  // Next and Last
  $links[] = ']';
  $links[] = html_link('Next', Array ('offset' => $o<$mp*$rpp ? $o+$rpp : $mp*$rpp), 'page_link');
  $links[] = html_link('Last', Array ('offset' => $mp*$rpp), 'page_link');

  // Building the table
  printf( '<table class="pagebrowser"><tr>');
  printf( '<td>%s</td>', implode('</td><td>', $links) );
  printf( '</tr></table>' );
}

function proc_link_args(&$arr, $key, $val) {
  if (is_array($val)) {
    $i = 0;
    foreach ($val as $val_item) {
      $arr[sprintf('%s[%d]', $key, $i)] = $val_item;
      $i++;
    }
  }
  elseif ($val == '__delete_arg__') {
    unset($arr[$key]);
  }
  else {
    $arr[$key] = urlencode($val);
  }
}

function html_href($args = array(), $anchor = false, $plain = false) {
  $cargs = array ();

  foreach (cget('page.pvars') as $val) {
    if (array_key_exists($val, $GLOBALS['a']) && strlen(strval($GLOBALS['a'][$val])) > 0) {
      proc_link_args($cargs, $val, $GLOBALS['a'][$val]);
    }
  }

  foreach ($args as $key => $val) {
    if (strval(strlen($val))>0) {
      proc_link_args($cargs, $key, $val);
    }
  }

  $targs = array ();
  foreach ($cargs as $key=>$val) {
    $targs[] = sprintf('%s=%s', $key, $val);
  }

  if ($anchor) {
    $anchor = '#'. $anchor;
  }

  $href = sprintf(
  	'%s%s%s%s',
  	url_self(),
  	count($targs) > 0 ? '?' : false,
  	implode($plain ? '&' : '&amp;', $targs),
  	$anchor ? $anchor : false
  );

  return $href;
}

function html_link($caption, $args = array(), $class=false, $anchor = false) {
  $link = sprintf('<a%3$s href="%2$s">%1$s</a>',
    $caption,
    html_href($args, $anchor),
    $class ? ' class="'. $class. '"' : false
  );
  return $link;
}

function html_table_header($count) {
  $mp = MaxPages($count)+1;

  $p = ($GLOBALS['a']['offset'] ? $GLOBALS['a']['offset'] / cget('page.maxrows') : 0)+1;

  $out = sprintf(
    '<div class="data_header">%1$d entries found.<br />' .
    'Page %2$d/%3$d</div>',

    $count, $p, $mp
  );

  echo $out;
}

function html_timestack() {
  $data = timestack(false, false, false, true);
  $tmp = Array ();

  foreach ($data as $key=>$val) {
    $tmp[] = sprintf('%s: %.5fs', $key, $val['sum']);
  }

  printf(
    '<div class="timings">%s v%s (%s)</div>',
    cget('app.title'),
    cget('app.version'),
    implode(',&nbsp;', $tmp)
  );
}

function html_box($id, $content, $title) {

  $box = html_box_start($id, $title). $content. html_box_stop();

  return $box;

}

function html_box_start($id, $title) {
  $id = 'eventdb-box-'. $id;

  $box = sprintf(
//    '<a name="%1$s-anchor"></a>' .
	"\n".
    '<div id="%1$s" class="box">' .
    '<div class="header">%2$s&nbsp;(<a href="#%1$s" onclick="toggle(\'%1$s\');">toggle</a>)</div>' .
    '<div class="content">',
    $id, $title
  );
  return $box;
}

function html_box_stop() {
  return '</div></div>'. "\n";
}

function html_comments_box($id) {
  $parts = Array(Array(
    'field' => 'comments.event_fk',
    'value' => $id,
    'op' => '=',
    'agg' => 'and',
    'newblock' => true
  ));

  $arr = db_query(sql_select(
    Array('comments.*', 'events.host'),
    'comments', $parts, false, 'crdate DESC', 'inner join events on events.uid = comments.event_fk')
  );

  if (is_array($arr) && count($arr) > 0) {
    $host = $arr[0]['host'];

    $content = array ();
    foreach ($arr as $row) {
      $tmp = <<<END
      <table border="0" cellpadding="0" cellspacing="0" class="comment">
        <tr>
          <td class="c_header">%4\$s by %1\$s (%2\$s)</td>
        </tr>
        <tr>
          <td class="c_message"><code>%3\$s</code></td>
        </tr>
      </table>
END;
      $content[] = sprintf(
      	$tmp,
      	$row['author'],
      	$row['crdate'],
      	preg_replace('@\n@u', '<br />', $row['comment']),
      	cget('lang.ctypes.'. $row['type'], $row['type'])
      );
    }

    $content[] = html_link('Return to acknowledge.', Array('event_id' => '__delete_arg__'), 'return_ack');

    echo html_box('show-comment', implode('<br />', $content), sprintf('Comments for event uid %d, %s', $id, $host));
  }

  return false;
}

function html_select_options($array, $selected=false, $add_blank = false) {
  $out = array ();
  if ($add_blank) $out[] = '<option value=""></option>';
  foreach ($array as $key=>$val) {
    $stag = false;
    if (is_array($selected)) {
      foreach ($selected as $sval) {
        if ($sval == $key) $stag = true;
      }
    }
    elseif ($selected == $key) $stag = true;

    $out[] = sprintf('<option value="%1$s"%3$s>%2$s</option>', $key, $val, $stag ? ' selected="selected"' : false);
  }

  return implode('', $out);
}

function html_widget_input($name, $value, $attr = array(), $type='text') {
  if (is_array($attr) && count($attr) > 0) {
    $add_attr = true;
  }
  else {
    $add_attr = false;
  }

  $widget = sprintf(
    '<input type="%4$s" name="%1$s" value="%2$s"%3$s />',
    $name, stripslashes($value), $add_attr ? ' '. html_attr_from_array($attr) : false, $type
  );

  return $widget;
}

function html_widget_checkbox($name, $caption=false, $value, $selected, $attr = array()) {
  if ($value == $selected) {
    $attr['checked'] = 'checked';
  }

  $attr['id'] = sprintf('check-%s-%s', preg_replace('@\[\]@', '', $name), uniqueid());

  $widget = sprintf(
    '<input type="checkbox" name="%1$s" value="%2$s"%3$s />' .
      ($caption ? '<label for="%4$s">%5$s</label>' : ''),

      $name,
      $value,
      html_attr_from_array($attr),
      $attr['id'],
      $caption
    );

    return $widget;
}

function html_widget_select($name, $options, $multiple = false, $attr = array()) {

  if ($multiple) {
    $name .= '[]';
    $attr['multiple'] = 'multiple';
    if (!$attr['size']) $attr['size'] = '5';
  }

  if (is_array($attr) && count($attr) > 0) {
    $add_attr = true;
  }
  else {
    $add_attr = false;
  }

  $widget = sprintf(
    '<select name="%1$s"%2$s>%3$s</select>',
    $name,
    $add_attr ? ' '. html_attr_from_array($attr) : false,
    $options
  );

  return $widget;

}

function html_attr_from_array($arr) {
  $tmp = array ();
  foreach ($arr as $key=>$val) {
    $tmp[] = sprintf('%s="%s"', $key, $val);
  }
  return implode(' ', $tmp);
}

function html_form_filter() {
  $content = <<<END

  <table border="0" cellspacing="4" class="filter_table">
  <tr>

  <td>
  <fieldset>
  <legend>Filter</legend>
  <table border="0" cellpadding="2" cellspacing="0">
    <tr>
      <td>Type:</td>
      <td>Host:</td>
      <td>Facility:</td>
      <td>Priority:</td>
    </tr>
    <tr>
      <td> %12\$s </td>
      <td> %1\$s </td>
      <td> %2\$s </td>
      <td> %3\$s </td>
    </tr>
    <tr>
      <td colspan="3"> %9\$s </td>
    </tr>
  </table>
  </fieldset>
  </td>

  <td>
  <fieldset>
  <legend>Text</legend>
  <table border="0">

    <tr>
      <td>Message (Wildcard is '*'):</td>
     </tr>
     <tr>
      <td align="right"> %6\$s </td>
    </tr>

    <tr>
      <td>(AND) Regexp:</td>
     </tr>
     <tr>
      <td align="right"> %7\$s </td>
    </tr>

    <tr>
      <td> %11\$s </td>
    </tr>

    <tr>
      <td> %10\$s </td>
    </tr>

  </table>
  </fieldset>
  </td>

  <td>
  <fieldset>
  <legend>Display</legend>
  <table border="0">
    <tr>
      <td>OrderBy:</td>
      <td> %4\$s </td>
    </tr>

    <tr>
      <td>Order:</td>
      <td> %5\$s </td>
    </tr>

    <tr>
      <td>StartDate:</td>
      <td> %13\$s </td>
    </tr>

    <tr>
      <td>Rows:</td>
      <td> %8\$s </td>
    </tr>
  </table>
  </fieldset>
  </td>

  </tr>
  </table>

  <input type="submit" value="Update" />

END;

  $content = sprintf(
    $content,
    html_widget_select('host', html_select_options(db_get_hosts(), $GLOBALS['a']['host']), true),
    html_widget_select('facility', html_select_options(db_get_facilities(), $GLOBALS['a']['facility']), true),
    html_widget_select('priority', html_select_options(db_get_priorities(), $GLOBALS['a']['priority']), true),
    html_widget_select('orderby', html_select_options(get_orderby_fields(), cget('db.orderby'), false)),
    html_widget_select('order', html_select_options(get_order_fields(), cget('db.order'), false)),
    html_widget_input('message', $GLOBALS['a']['message'], array('style' => 'width: 250px;')),
    html_widget_input('regexp', $GLOBALS['a']['regexp'], array('style' => 'width: 250px;')),
    html_widget_input('displayrows', cget('page.maxrows'), array('style' => 'width: 50px;')),
    html_widget_checkbox('displayack', 'Display acknowledged items, too.', 'true', $GLOBALS['a']['displayack']),
    html_widget_checkbox('message_notext', 'Message is empty. (overwrites all above!)', 'true', $GLOBALS['a']['message_notext']),
    html_widget_checkbox('message_notexists', 'String not exists.', 'true', $GLOBALS['a']['message_notexists']),
    html_widget_select('type', html_select_options(db_get_types(), $GLOBALS['a']['type']), true),
    html_widget_select('startdate', html_select_options(get_startdate_fields(), cget('db.startdate'), false))
  );

  echo html_box('form_filter', $content, 'Filter');

}

function html_form_acknowledge($title) {
    $content = <<<END

    <table border="0" cellpadding="2" cellspacing="0">
      <tr>
        <td>Author:</td>
        <td> %1\$s </td>
      </tr>

      <tr>
        <td>Message:</td>
        <td><textarea name="comment" rows="4" cols="50" style="width: 422px;"></textarea></td>
      </tr>

      <tr>
        <td colspan="2" align="right"><input type="submit" name="make_ack" value="%2\$s" /></td>
      </tr>

      <tr>
      	<td colspan="2" align="right">%3\$s</td>
      </tr>
    </table>

END;

  $content = sprintf($content,
    html_widget_input('author', RemoteUser(),  array('style' => 'width: 422px;')),
    $title,
    $GLOBALS['a']['event_id'] ?
    html_link('Return to acknowledge.', Array('event_id' => '__delete_arg__'), 'return_ack') : '&nbsp;'
  );

  echo html_box('form_ack', $content, $title);
}

function html_data_form($start = false) {

  if ($start) {
    printf(
      '<form name="%1$s" method="%2$s" action="%3$s" enctype="%4$s">',
      cget('form.name'),
      cget('form.method'),
      cget('form.action'),
      cget('form.enctype')
    );
  }
  else {
    html_resolve_hiddenfields();
    printf('</form>');
  }

}

function html_resolve_hiddenfields() {
  if (is_array(($fields = cget('page.hiddenfields')))) {
    foreach ($fields as $field) {
      if (array_key_exists($field, $GLOBALS['a']) && strlen(strval($GLOBALS['a'][$field]))>0) {
        if (is_array($GLOBALS['a'][$field])) {
          for($i=0;$i<count($GLOBALS['a'][$field]);$i++) {
            printf(
              '<input type="hidden" name="%1$s[%3$d]" value="%2$s" />',
              $field, $GLOBALS['a'][$field][$i],$i
            );
          }
        }
        else {
          printf(
            '<input type="hidden" name="%1$s" value="%2$s" />',
            $field, $GLOBALS['a'][$field]
          );
        }
      }
    }
  }
}

function http_redir($args = array(), $anchor = false) {
	if (!headers_sent()) {
		header (sprintf('Location: %s', html_href($args, $anchor, true)));
		exit (0);
	}
	else {
		return false;
	}
}

function http_header() {
   if (!headers_sent()) {
     header("Cache-Control: must-revalidate");
     $offset = -3600;
     $ExpStr = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";
     header($ExpStr);
   }
}

function url_self() {
  return $_SERVER['PHP_SELF'];
}

function mkerror($msg, $code = 0, $type = 'MAIN', $exit = 1) {

  $msg = sprintf(
    "<pre>An error occured:\n\n\t%1\$s: %2\$s (%3\$d)</pre>",
    $type, $msg, $code
  );

  echo $msg;

  exit ($exit);

}

function c_overwrite() {
  if ((int)$GLOBALS['a']['displayrows'] > 0) {
    cset('page.maxrows', (int)$GLOBALS['a']['displayrows']);
  }

  if (strlen(strval($GLOBALS['a']['orderby'])) > 0) {
  	cset('db.orderby', $GLOBALS['a']['orderby']);
  }

  if (strlen(strval($GLOBALS['a']['order'])) > 0) {
  	cset('db.order', $GLOBALS['a']['order']);
  }
  if (strlen(strval($GLOBALS['a']['startdate'])) > 0) {
  	cset('db.startdate', $GLOBALS['a']['startdate']);
  }
}

function cget($key, $default=false) {
  global $config;
  $eval = sprintf(
    'return $config[\'%1$s\'];',
    implode('\'][\'', split('\.', $key))
  );

  if (strlen(strval($re = eval ($eval)))>0) {
  	return $re;
  }
  else {
  	return $default;
  }
}

function cset($key, $val) {
  global $config;
  if (is_array($val)) {
    $eval = sprintf('$config[\'%1$s\'] = $val;',
    implode('\'][\'', split('\.', $key))
    );
  }
  else {
    $eval = sprintf('$config[\'%1$s\'] = \'%2$s\';',
    implode('\'][\'', split('\.', $key)),
    $val
    );
  }

  return eval ($eval);
}

function RemoteUser($default='AnonymousGnome', $key = 'REMOTE_USER') {
  return array_key_exists($key, $_SERVER) ? $_SERVER[$key] : $default;
}

function MaxPages($count) {
  static $mp = NULL;

  if ($mp === NULL) {
    $mp = floor($count / (int)cget('page.maxrows'));
  }

  return $mp;
}

function EncodeString(&$value) {
	$value = iconv(cget('page.encoding.input'), cget('page.encoding.output'), $value);
	return $value;
}

function mfloat() {
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}

function uniqueid($l=8) {
	for($len=$l,$r='';strlen($r)<$len;$r.=chr(!mt_rand(0,2)?
	mt_rand(48,57):(!mt_rand(0,1)?mt_rand(65,90):mt_rand
	(97,122))));
	return $r;
}

function timestack($push=false, $pull=false, $msg=false, $return=false) {
  static $stack = array ();

  if ($push) {
    $stack[$push]['start'] = mfloat();
    if ($msg) {
      $stack[$push]['msg'] = $msg;
    }
  }

  if ($pull) {
    $stack[$pull]['stop'] = mfloat();
    $stack[$pull]['sum'] = $stack[$pull]['stop'] - $stack[$pull]['start'];
  }

  if ($return) {
    return $stack;
  }
  else return true;

}

function html_js() {
  header ('Content-type: text/javascript');
  echo <<<END

  // -- Some JS snips ---
  // Code is heavily borrowed from the RT3 Project.
  // http://www.bestpractical.com/rt/
  // Thanks allot ;-))

  function $() {
      var elements = new Array();

      for (var i = 0; i < arguments.length; i++) {
          var element = arguments[i];
          if (typeof element == 'string')
              element = document.getElementById(element);

          if (arguments.length == 1)
              return element;

          elements.push(element);
      }

      return elements;
  }

  function markAll(name) {
    var e = document.getElementsByName(name);
    if (e && e.length > 0) {
      for (var i=0;i<e.length;i++) {
        e[i].checked = !e[i].checked;
      }

      return true;
    }

    return;
  }

  function createCookie(name,value,days) {
      var path = "/";

      if (days) {
          var date = new Date();
          date.setTime(date.getTime()+(days*24*60*60*1000));
          var expires = "; expires="+date.toGMTString();
      }
      else
          expires = "";

      document.cookie = name+"="+value+expires+"; path="+path;
  }

  function addClass(id, value) {
      var e = $(id);
      // if ( e.className.match( new RegExp('\b'+ value +'\b') ) )
      if ( e.className.match( new RegExp( value ) ) )
          return;
      e.className += e.className? ' '+value : value;
  }

  function delClass(id, value) {
      var e = $(id);
      // e.className = e.className.replace( new RegExp('\\s?\\b'+ value +'\\b', 'g'), '' );
      e.className = e.className.replace( new RegExp('\\s?'+ value +'', 'g'), '' );
  }

  function show(id) { delClass( id, 'box_hide' ) }
  function hide(id) { addClass( id, 'box_hide' ) }

  function toggle(id) {
      var e = $(id);



      if ( e.className.match( /\bbox_hide\b/ ) ) {
          show(e);
          createCookie(id,1,365);
      }
      else {
          hide(e);
          createCookie(id,0,365);
      }

      return false;


  }

  function loadTitleBoxStates() {
      var cookies = document.cookie.split(/;\s*/);
      var len     = cookies.length;

      for (var i = 0; i < len; i++) {
          var c = cookies[i].split('=');

          if (c[0].match(/^eventdb-box/)) {
              var e   = document.getElementById(c[0]);
              if (e) {
                  var e2  = e.parentNode;

                  if (c[1] != 0) {
                      delClass(e, 'box_hide');
                  }
                  else {
                      addClass(e, 'box_hide');
                  }
              }
          }
      }
  }

END;
  exit (0);
}

function html_css() {
  header ('Content-type: text/css');
  echo <<<END

  body, td, th {
    font-family: Verdana, Arial, sans-serif;
    font-size: 8pt;
  }

  form, input {
  	margin: 0px 0px 0px 0px;
  	padding: 0px 0px 0px 0px;
  }

  input[type=checkbox] {
  	margin-right: 2px;
  }

  a {
    color: #333333;
  }

  a.return_ack {
  	background-color: #cc0000;
  	color: #ffffff;
  	padding: 2px;
  	display: block;
  	margin-top: 5px;
  	width: 140px;
  	white-space: nowrap;
  	text-align: center;
  }

  a.return_ack:hover {
  	background-color: #ffffff;
  	color: #333333;
  }

  td, th {
    vertical-align: top;
  }

  table.filter_table td fieldset {
  }

  table.filter_table td fieldset legend {
    background-color: #f1f1f1;
    padding: 4px;
  }

  table.datatable {
    border-collapse: collapse;
    border-top: 1px #666666 solid;
    border-bottom: 1px #666666 solid;
    border-right: 1px #666666 solid;
    width: 100%;
    margin-top: 10px;
  }

  table.datatable th {
    border-left: 1px #666666 solid;
    color: #333333;
    background-color: #f1f1f1;
    padding: 4px;
  }

  table.datatable td {
    vertical-align: top;
    margin: 0px 0px 0px 0px;
    padding: 2px;
    border-top: 1px #666666 solid;
    border-left: 1px #666666 solid;
  }

  table.datatable tr.odd {
    background-color: #f1f1f1;
  }

  table.datatable tr.even {
    background-color: #c0c0c0;
  }

  a.page_link {
    text-decoration: none;
    color: #333333;
    font-weight: normal;
  }

  a.page_act {
    text-decoration: underline;
    color: #cc0000;
  }

  table.pagebrowser {
    border: 1px #666666 solid;
    margin-top: 10px;
    text-align: center;
    background-color: #ffffff;
  }

  div.timings {
    border-top: 1px #c0c0c0 solid;
    margin-top: 10px;
    padding-top: 2px;
    color: #c0c0c0;
  }

  div.data_header {
    padding: 2px;
    background-color: #ffffff;
    border: 1px #666666 solid;
    margin-top: 10px;
  }

  div.bulk_form {
    margin-top: 10px;
    padding: 2px;
    background-color: #c0c0c0;
    border: 1px #666666 solid;
  }

  /**
   * Comments
   */

  table.comment {
    border: 1px #666666 solid;
    width: 100%;
  }

  table.comment td.c_header {
    background-color: #ffffff;
  }

  table.comment td.c_header, table.comment td.c_message {
    padding: 5px;
  }


  /**
   * Box CSS
   */

  .box {
    margin-top: 10px;
    background-color: #ffffff;
  }

  .box div.header {
    background-color: #333333;
    color: #ffffff;
    padding: 4px;
  }

  .box div.header a {
    color: #ffffff;
  }

  .box div.content {
  	padding: 10px;
  	border-left: 1px #666666 solid;
  	border-right: 1px #666666 solid;
  	border-bottom: 1px #666666 solid;
  }

  .box div.content {

  }

  .box_hide div.content {
    visibility: hidden;
    display: none;
  }

  /**
   * Special cell formats
   */
  tr.mark {
  	border: 2px #cc0000 solid;
  }

  span.null {
  	color: #777777;
  }

  td.empty {
  	/* background-color: #666666; */
  }

  td.nowrap {
  	white-space:nowrap;
  }

  td.err {
    color: #333333;
    background-color: #ff8000;
  }

  td.host {
  	font-weight: bolder;
  	white-space:nowrap;
  }

  td.alert {
    color: #000000;
/*    color: #ffffff; */
/*    background-color: #ff0000; */
  }

  td.CRITICAL,td.DOWN {
    color: #333333;
    background-color: #FF8080;
  }

  td.WARNING,td.UNREACHABLE {
    color: #333333;
    background-color: #ffff80;
  }

  td.crit {
    color: #333333;
    background-color: #FFA0A0;
  }



  td.HARD {
        font-weight: bolder;
  }


  td.OK,td.UP {
    color: #333333;
    background-color: #80ff80;
  }



  td.priority {
    font-weight: normal;
    text-align: center;
  }

  td.syslog {
    background-color: #00ff00;
    font-weight: normal;
  }

  td.event {
    color: #000000;
    font-weight: normal;
  }

  td.snmptrap {
    background-color: #66bbff;
    color: #ffffff;
    font-weight: normal;
  }

  td.mail {
    background-color: #350dff;
    color: #ffffff;
    font-weight: normal;
  }

  td.ack_ok {

  }

  td.type {

  }


END;

  exit (0);
}

function html_image($name) {
  $img = Array ();

  $img['netways.png'] = 'eNotWgVUFO3XH0oaaVkapBsplYYlVli6u1uQ7hKERWkQ2KWkOwTpllJCJARpkJKWbvjm/Z/vntmdM2cnnuc+9/5izsZoqCkR4lHjAQBACFOGaoH7TfDDjYMOfq+FWEeBO2wvGFwBexqHhEDgaaFpoioAMAIwqKyOX9Z+kH8WRX/Dw4tfi75I8d49X0oZKhsSQipMW8E6LkXGMFJCdVESDVEGfuoncuTmlBRrpLEwE+KkS2ttNCzCR5G5pTxRiDCqNz3IcEvkO8ODkYWq3awLpUXHgOulFoKYngsphh/2UqNFbddTZdrLL71/lJUr2kXhe4X/4BcUYp6ric+ELfXjJ3bILL/gJJ6TrbzPrP2H9c3s02HUn9flvZ2NNq79/qT/0E0/sb1yhfLSBPQMmX168IZC5fpi7WxtN+7vbioqKjw9PQNP/yYhkbi8VHP4eufowdiFBEXvZuCZopLXfwvCZT69nm8sUcs2an6zmiMZ+AlLPhCt7snfoBTJAavN/fmm0OPv7PV2izCbtNRUr52p7R5sBpMm10Xw5mSdIf9/ppAMNPB8DynmZubouI3P356Ymdn2ZvUrnbiPavZL77OdX7jmT7plAEwAEGxKu/c+TAv7Jf1ekOU0oypzOuxDx/xtfIGBRBPLQXOEPPeyky3s5q5+6Cf1PawQfmy125B0e+YZ55TcpSxD2rwtxpFhqI+JCcg9D6lUVXzaSc6ArAMnEHxtMt7iuWUh8LvF054JqqtgK2Evi4MuIwADoBZpWiwq643KmJhTwgAmE1oKgCbKPpdXQEn6TU6SmXkXVduu65k84km2hZnt9ITzRYPOhJmbHIQ0auAIx9OGOYV9gg6dBUbwgos5368neUuWznriyDywq49bqVZGhhEGvKUixgSYAAADA53EMazHIpEER9YXYh56d/bFcVItV0oy5NbPbtpbkjaur8nIguBSDoMRgMILOLLm/bSzAAiajAAO2yNLZgyx3opfrHo3ZHx+pSVDYk0SXg7xQDQq+TcN1MZ1GsLLim7MzJqweOch8Z7/sPTbPUdI+4soryB52sLudGL7o4JvVrYzhb14LH1Za1LMc5CXLhpWKSzhkWzKr8K7MQF0VZeJMOK331yWV5lZDQSo2teTVaYr/RcPL6Rv//UqJdATRvdeXV3hFlr9yTAPG2JGf8qogskbM01KjC7AkUnnSUAchlUafqbO+5PV32YsgaWk+QjwSfvuGWlPwWbZ9meYnZPS/NNZ3PsnUksyMrTf/nxcrjTTS7dJjozIeyPMHPnvKoj3yVP0imhldAceco4wSB+y3Nz8BOWIkqZ1713tjb45230Sp7gccrMPrmYcRMju78+8o6BxG0oeEhLlRED2WavnRzNTvv4WWUoSJFYBdIqRto8+Ik+bgRABIXEHS6y/X8mklSlSwzNYgLZlp7wiipCGsmYwKipK+uHq43VrWPUuQEygFEUAmagxlw70Puy4WBpk' .
  'ODz2kzjPHL1yENSGj/DAUxQT6CarjJuur683htPFhDG9TNmZTX1hEDgReT8liXyexuGSvlngfkO5BMmv6Wk+40YeasKb9rKUYlXXyWJCFiqWIHDYhDRL5HI2NjbwHInZDQ/W1KufefK7M51MkIDTv4J2Ixksx3tLHYHzjRcS9AmOWpaWq5G4ZHsfjdb8TzZHxX1D1n3axH0PJFwXnv+Kj6zVezAFu03fPAjiUHo433T4LenpR+tiA/DpBwtgwSsx0EsFNbe3S9AS7bYenAXcXfkrKCjom/nbgiOlJCf/HYUt+MFhg8T0BDEiZ8EW/q17qLRMIxqGQ4L3LsfPRdPO9cff8QJTPn04fHRzNHNycpIdjpqSu4lBPlfsI8dD1vPhPn73L8EqNGm86tqZFj9JkJieMC+JeOfxuFhIL0/m5fE3GkUq1LfhUc6RMWfjRPPErTFVN093zUEMbCJlCLcieUpBeXm5cfoPTnpfM9d4aXZj4PagrIRXr/z37dUJCeNy7nzjT7cHbp5vcXcbWxSbWxTrVyd3LS8MkVFsoq5BH5DqORJLn9cUyso4/a9msa5eXF2FsCZHJPRXfWJke+PqkwY0X+TkzvZzcHJ+/z5EDoAX9vf3L2ShjFhK1LtMn/z1bmv12TcyNh7GjfH7GhqrjzNXayn/59nISNDt5RF3oPnqSYBHfb0EX0How32PhYDlr8POm2pAQHi88bWs+xFJW/Ja5LpIhd51Oz0muLa9W7mhrhSlFWWmjacbDAR/czdVjpoJmlT8FvlyZ9a82yaCOCGQ18+ezdnY2/scLr2+Pt0W99lDdLDFwCailsWW3scZmxdzCClkFNmIUqwgkL9tkkeKHTtvDto+UPIGXG+XPOE1Or2/ORwcdkyij5CGJhFaPLLUKE4QArQnrDLUrEJd1q0SGYF8BGKBQ5KeDrMeOgUwsrNX1NYKCAlN0mvq6unxmVyuOFeY29sM62qtX9zEwHPHBiMRf88C6EQ5+9dGT7bAsiDO3DFVW9d2QrEVxSoW0NeozHgDzwieiovP/3w3uDYuTAhEEmsNRRLxCeFPxeBgqaSQCPKODjb+7t+YEPf6a9H85cuUF2eRPNC/FfFYym8r27fVa5s0TlHo2dy30u8xJoiLh4dpkGBsbe1iW46KNQsUWTXPJtWlDU1MFqqD84odwUBFrb75ve8zKGb5u9bSejB+FgTuYNMVfrcuz63K2aOzcvorv5kfgQctg2tH6xTknffXu4ttvvq28bQvjnM9t1yWO01nzqampuglA0xCrjau3mG1VmOjh//58yeU9ra9ufm5leLuzk4aj+FEg/MWyFaDmDKpnHzuBP+OHFdHxxCZ9D5nEN4MAE3nR5gBIIDuYHX5D4Shx75e5l9n1bFtAKgAgEYs+0wQDy4+A4xA0Xg0Jw1RLp8QpAIc7ehAAbFcqSZpxBMtH7XN6Hlpv42P+sVqbYs/Xk0KQ7+Y6MInf5NUoOW0jL2' .
  'FIJAInghCgJEYUkiAJiNM/doFR/BpmAXuqgWLxgzAoxONy0SsOfmlmFpTMFHrFSeyYGSEsWDcgYMcL7r3/Hf/vk/bQquTkYQNRZVJSzICgOH0JDMnIXqDdaTNgs90AQEC66+RuLDT9uCbc11uveugThX+DmHSGNrbPYaGXyLe3t7b29t6kA/1xY5gku9WIh5raWsvB52Mitv98OizfxVbwFrku+itLzC14kHT9XB3GHi2U4QXKZcHgsvBUsvk1snZ3uxJODY9lYRXBoZiT9QAuAqdn13331Nwm/B1qOdWkNujA4wsT4rrHdbyewO3xwV7nF1/sfH52NrCeoDSmIwIZqM3B2ROMtgulP2MONCeLIMbGE44QMLC7ETAAuX5h36zQ0taqzFBzMRcXFLSdTzEbe1Km/0Rae17pYmNGaerhfld67M3VjjRfklxcWsH/TPkkapqHALMVUEOuXkKNmx2FomK4uTkxCvDawiPunZ4blXDytsRN7yRpEVKUs/CDPe1tbGt40UJEjTNrFnLMb9X7CWKExrGuDnvqj/aHzmN3avcOz724LTtix3wHbO/edx+sRTYysTXtleHhnEtS2toevnnZEyhQDNlPXT0ZpZm1+zB6/BehvFNa10+mHX7qC9wIQzsx8Ojw24ulQ+3J2O++3OCnx4ef3pBuuPktDWC9F7/RmJndvrb+qlibC19x+1EMh1DSGJOko/7VnVNtQEyJYXMrbCjM1VWPoqI/qXLQnMmg/96ks/+XFkJepiMgNI7GVwOrYkM1WsLEYYFcmr/rt1oX/ymRAS19UivAjolIKMMyDGShgHvzfcn1hpOqlI29oTqHYxONCh/bJ0En/16kiBx/I2sK3iIhMxgzhFu96nz82pO4AFS2NZDXl7DULxNgZ15Uljo2TOA8Sl34P61WFGqHA7F0nbrwcnV5o/suwNz0aLiYi7oo4ieh/tf/KBYoMHBTD1KezY0YuZWTzUrQCDyNLMYEpMFYzaCG7R4bHxPYXdr228EZyAJ5oMvnZs05rnGO0RNVycbMY7AyQhSSDInNfKlzabYkp/1cBqv1pC72KLf6Kdw40ZXvfy0tDSzpV286CVy778/73cqakJ2q854HAejGQj/+jZIWluhNvdmhXz3fjMVmYCidX+yxDAB5Jg0PlNUTk6187X8Y8nUzMxMDZbhAhoJvze5UsF7aJ8BDbJPKJd3xY70TZUV/bYn8sa5k4O3CyI21LjELIUAYST0Z6q79TujMAO7Lkh2ApZxhaVeq8yifqOAuHhg/3uK3Zmq+vRIT8OPlnY2ynC4rWLu2EwxCHGv5wpLSuxtbEqd7eyKlw/19fQmv4yMjCwsLOzt7Z3+L1JTyYE3LSbnyGwE2HtojLy8cPdv34+2NjfLMilbTPg27MmS6OpelYPQRUgjWlhUBEc9mz28iJZjeueCXd55vfOBokKP2Wq5M3gwru1FZ8crTQqKkpC2v4KcRdLSh0uBhw' .
  'rQruUa2uTkZNK2alpa1/YjRcbzgI7d6SpnB4daySKFBKk69zVFzo2qJrX2G/sLI9UM/+XODltbWzkEEd1LAmphDsuE59TJKFR5pcxbKTlGGVLq1kmmX2sv3XX4csZr3wDBrbPkmnGyb6eoABgAlBvScJBIjGHbVKVqpKjIRFly7JWLO3ZOsefevvb2brBlj0RcdfK+ED7SNSAw5rLW/9LhzKviwm+NslPpLCswa5qHQ48SFfrOnGfopIIuUbIYNZ3X1JPCH0m/rtbK54g4T2dVlzPmx5CxF7Dtaaa2lvtv9m+ZX4J1oDqZJWitqDneDyTmmitDUcXZEv4TB9XPHVa6w03bfBT6nirFu8zW9XOdgCffux5pc/j9W+lJkL5vNvWAVlZXi8LrOUM+WuwOrgf4cKABewKTHFw/++mb0nI27wf5TtJnJuuFRss9MAmJrYiwcDfw30M/ZpW8FjzfliKN8dqfOxvq1Mei3iEhSQySWk43D7nqI1PvMypyHEVvKLT5XFVWhhvdS0FBMZjAIA0qfkPD2aO1wQQ6cT2j/4Wx8W7GB6bqxzwJzDDuP/gca1W1DqE6enqNLrPPmuarjj/wpX9A5I87CFM3DL/ZM9gG8VWDrCPA3H2tOXOvwUAehZC6v1xjVUlzQPnodt0L2Y1oHFJSUrYuujJBDQ0Ng3Z5XsFgEBEnHUZiYjmXhcu01hbHSfGTrbF0VclO36sUdemPaUsDfBJCgRL0U5GuiImJiRmzQEJUzau5toFqypuPOqIyMX2f9EZZ337XlIlDcLDKXwRcdRSN55pT+u/6kpdiAYyp6JgNOKMnq8Nyk922dkCaVoBEDnuO306rpbyuIgpxG/LwnqImnlokP/ShQvef65mexw+umezk5OMFz7GOf19JU2xUnbUg3mlaOc+9jz8P0ueK7scW2BEyBTI8D5LFFl7LdlHAqxuhxXu3xzeaa27os9JtD5JWy9GA+8jeWYAuT2ns5/79jfQ0emm9zeNmEFkLaGUDeXe4ioL+5kVT09CgrYBKSgUG061pcp7hExFpaG29/v3ZOk/j29pR35jKzo9RsQeb7YkirSFQ5OdwiPtce43uzdaDun7+6zl/R/sXz63qo4MzCv1Tzm/DNVVVZwEdDx3/8sBUGbu2FIFD4RLCEZ0yyi/vRNhjNepwooexhWV45cEeEULL1SuqVll9o8XuH7n6TzX7vAQAUjQc1bD5E3vb/RWcRxFIJHLSbkHT1LSK1GyornwbFg5kHsY4xvCoQPDe7ezufu0ZsJAPrs2ToiVSZM4bYKv76t+WRhd1q/qWAPjzVSpa3PJOYymTtJvL/09fDOhO2kwGwKnp8ro2GbcwDAlqoIOcLlYxU0kud/p33HqqdJy3wf3u+ixQaokQmAHNKGhCeFXX19dtbG3RZBT+C6w+QvZdtfH+7Cgz8crPn5OQuUHH36MZQkrzBC2oBvIVuvYniow9E/tdoLKyPaGLd5OTI' .
  'r+KHXei/DaRy6ahd9690ebibkum/J2Xt9EMrjWVlV8M84WoPyxaUoR8xrR7bu2LI4vBIpeNAyAI0LNNXIeUBZ24G+ztoE/Ot+6MqTDrlB2q7/cT46JxhMOtpysNQdNShKqD2AQozuVIBt6flkjLetJ46mlFGE8K9wp4mV74Sbg2zW94gIiZMLNxTgUecGX9lKyN/CLtrZrK+6u6kkjIWEgKqDTruM5069yeKpv0njEt8SuHmLwqyzJSoBFzlQi5bXZEgSzsxPkGFxM99Hatni99eO1oJFNsrFj1X0BHSFcuAj1cxtb2bXCAT+tqVBLwk03ybN9XFMwMe7GjFGiVQYz5IujVeNQ47/rQzx9CQiCio6PjwDGifDrca97eGDAagR9yxCANeehdnRnN8d+ad+uqMrIJXdSyFgq2GlH/WQjtiX07ruObYRg2oPiyCUZx4zv+dvfpBzdBdbpr16BZNm9lqA7M0Eq7SBdt5f7qcbMJn9CrbnSYzRdY6Hb/k/P7YPX389iUfYYsPdXv6pVZPECLAbLf+/fvtSk2QPjZAINCl2JDV0TEmytMmW/dEP/5I9IBrfpHjEz5FPq/sqrNrh7MWPM5td2G+V0qrifeBAJhv2MjrfqZk34OxLkxWaXzmQ4kMCy3y3FTFkrfrc6OnTMkai0JeurlVlzPvVWW6Wvc57fa1Gj+nP9H55iNgEGq3X4sJy4uLspSmfOwy62HzndEtTDSLrH5Yulmee5feu5S8PInWfm6ek80mVffGUXgFZVc5k33cfNs6tShw6PRRMEHU0xvJSPloIx69FWcOAaMGLV8VAAc7VEtiyw6q0M305+ac9THGGOvnKDZyL4RsKr/413QT2Tn5o6NtFaLiYouhT7cDQ02JFt9jxCwXR2Io7mICJ8RFo0Csg6gLFbnYJOfXUXr4bnJUpKgGVyhgFrb4TTQoXodLgUPpXLqCcInvUzTyeOgzE5iszu/Kh7umyQn1rcnSypMfRoTuUV7D5Y6dh+HXkaI9S/WHIZh4VMK64aw9SyVabPDJ/PH34B+xiN80dUX5OjHQY385LGxVIJ/QCxytlX5T09P93JxcyfQ817d1ofuLT9s90RgP6Z7ySLRKjZ5A5o99Vypu6omUS7+DVV5l+q0Imw0FIudJTpO9ndytvoB5Vh0G0x0Epdloq3bCarnMV/6Vm75mD4gRgRsd/YrzI0niUsv/EzD/vq0gWQzHRmB2ZZBV6hDShQNPtn77kzMQGzs+k8XX/t1HqR1Dockps/utmjYLFlLDSXMyV/TTaqogEQ/cxB+jwM93TmWJss0bfVqudrMpON7r6AucW/M7NESF08nXm16E8BixUxGUtYv3SsQ/zGtsLBQWPjfeIHSGJcnCykTM2z8YJE/YjPEyJn8Y/Z/sVihf4grQAKc711dznTdn5Xk7vHy8EiAlDzX4FyinuuqH/+Ig4Qk87V2EpSDatZiAEHpjuSDCgtyUVMX8NG5' .
  'Ztc8OKCVFDImK2rhTXWm/KphiUS0tLScbN6dLgUe8J1SctmbKnD9XC98mc+M7T5ZXtmARfbHyiDwu1sHrpmvS4fSVd2hy0LtWm/CSx+uumd6Ptw0RNG6atZ3c9435/vWX3HJ2GxtNwxY96mMOgI2zvfnPe9mtYZ0K/R4jRtHhGO+fdEpgQQ4vefn5uamJcqVCPk6PnB5npaaisPE4j6j0ho++q7hpm5aI4HhnMg1+NrkbFBaSsznNKG8omLR9WK190FZ6hEVGkSDd5IU41WECBlVLxonbkCCQMjxp/GyXCMlC0VoXr/ob9GCE+zZyFptx0hQNIeejmOH+t9kZy3wJNu/4hqUeW3sGFP10UCj7TMtVcOWyT3cZ2b/TODts3yg9nSDFey5yWL47lQZ2e/WF43P955NDaBAZkAWz4L0BtNmi3guNMKDS0ONPib90D64hvuuwLFTJ0FcgRCX+6LEkdpJ7gkKtOGWqR8/wjirCMj7mPbWv6dEP2aglLPZtDGiaaBFqCKMHPy/PCEnP92dcdufa/Ahmu4uEMoOKVZSJqPmPE0mEHuDoZEre52RS9MRpZKI2c0o8jP5dPKqsY6IxsJxPRw7pDNwq9rC2oUsWy5n4xvbhS2JJk5engNuwYOedM/uWVmrCV8wOIf5xtfcXFzU0iHtT+M1cQkheC2LA42v50ED8Q6R4paZf9t1ebWKXOZdurlQl6AfK4SlbI7lmhsbq4RBIwmgLF6EQLuZm67Cxul208z0dI6E/7GXra5rTleRHb3Ev1lQSmr8Y033Ofz9uaDoZTTbvvSPHVHGfGrlYkezIPf0yAC2WMgrGxkd43YcZF51+LzvOvJxAPYSEQqXAGXkYhGXiBmGOUXVJcTt7CZ/S4BAgNzpubnQ8GY1wt/fPz7WyyPLkJb8BUHEmevu46UsbpCgmCItqc7Ozir0qnCwnspHEbT8zY+Pt0LPQBL2llZWLrR6VxQ7QiAQ8BcVhxGw9C6+fhjzl8h52/juJCSgib8qDtyyM4vys7KyQDMBGgMZRg09vbTMLfVSPrulrtAuRxTB8YQKv7eER/tx/xNzcgoKSFTefSb0gV0t63J++d6OKurg2SNC6vXuCsACavdRglgOwvCsTNCFOqxpwS02+Of+CdpbTDiGhSIgA/TIvV5XWaG8dftnW7VMxq5m0uCkC/suBoKaTz0qCE3VXRkGM6yz+d7qvatjqEi8Mw9H/56aXNbZeZ8XTb8z33TYtITZYPMxLa3Re3d6aGgoNjbWmF2tVj5RF08Gx3TG23T5s7USeDcW0hb3BbjT23gxO3XJM53gYEkAZF7113kez8v0JugBD09P+KSLg8Nz751UXcaloJDzWfvW6szk6uY/eEpkexsxDgcLLRiPCDLEfV3V1NS+shlv5AbslNXV1Q0MDCDr7Wfn0YGdfwxCBftOUfLosFNI2eJp2HQH/1tZ4C1aEiRMUxsb27Co24iKk9ImBgLRZMQ' .
  'wKCEaCydG/pp7oz3x3IfaIMtQHOGvQjufsPuzcib9+MScJ+Olbd1LrLBPgkJ4GxQsMq8JhpQROCP9rEqdl8eJ3rm47x5pvUIjfg/dcdpVH8vOXhDTYDCXaRAxGdK7JsWrk0Nos6tNT03FUkvarXT3wF/GjJLirHi06GD6/F0JH7Bg4ed3m/sCKc+wljNoPC2Kc+uIQVG98FhP5dTer/wTiiFjJhuig4J63V5+TovkkfbfAUlPtm8kdiFHssstdH1FT+t4NKmmc+FqPHp3plPQdaHZfc31IGS26CjC6eJgcUzqZmRzNDNiNWo48EzLmdZnMIwYUA6TxQzTjBUeetSdg/FmKKXaVZ8ExyYZhxoNh5RxJRaf0cXuJdp2NyYjgMXMmOGC97X0k+f5VQaJMBoPBC4sawVlh8nFZRrs6Mz+cBAuF/3UV/DfG7ympYDd443hE/zbCRamyFQ7OfO0zHMzQ9Qzgl7UMCDDlIOEFM4LwwaIxFkc3zqi7Acowu684PhwO6gO9025d7wQ1jnEvGhfdZL8q66+vpjn5sgH/s7nbOWvXaampiRu9uqjLDNYStSyd1bIzMWBL6/n9V7FoIO1xviDvqJ75VvSU6WEtO1WDi4uJxBLkhXpXmgFFTY3C0h3FRdvIu1zDFu9tkEE6NqrGz4+OQmWMucgLy8t/d3me9gZfEMdcaY15BxNaN7u525pKc59i8jexAYecTLTkjJ/tRsM2j83f78q5MWCK/c0Pliu4HtzU4iuiv6jX5g/IVTCU4iKXajIKwgQVo+kynyERpKBJhJzLExlyZInko0ehQJd9c4kG4ywH1QTzk5OpE4MwObozXYar3Hn/bXzBr2Ap7+/gjAXHZE5CynIxz+F4VjMxE3YYch3HhcxcklvTE2zHdk8BXZ2dkANoYtgLKosK4MdJBqsiBIUlJcPCY+2ouYYHi7/e9/+Zeh3ASTP2dvb2+ndyK4K8rn/KzlyPrP2y8NAaYXIMm03Ggm/2l5gvqLR9fruHoV6Cb30epCqJQRUrOUQCT4HpeXlVisx6Rdfcg/jSuglA2539LvMOjwU/P1bfjIS4wS1B26BWIXF+BlGnMkLqh3QFoDCyn40qyCZidKsKgAXK7qAnE3QNmd3sGtqrhAAONuZU1CrJCxZ85aupoAykD1FwM1sNOU9ivn3uqW4JBufAV8QGqb4Jed96y5B5OfXG0+V7ku1S9Skch8jQFMGLvfasu4+j4Px5a5SyAkoIn7tnzuh7FBJYL+rdPkin2bsL7ZdLLR4amlrl13FBNJcWzOUjxOzaAtQuYJSjPQqmogOlZa2eTKghokZ0eOzz/dqycybl4sLJe5bPbyBJyqslvWcxEjgvaHhC+0SuBhciDreZGn3JltcXAQA8CYfXlzwMVmcL7zAQx7BPf14lm/Mxlo87THcZ6rTocwk+EnnvnchD9m+S2WOpT++s7j7gQ74LRb+QYaU9daPBDBXvqBXS05Onp' .
  'MxLRXkiPxX6HcXEqqpqTlTbVrTdR/1ga8VcRU7sy1TBYQRLU8+eVAIenjZA/AAMIEwRnRT4WAB2QhMx3AblL1VTOHn2ngzza9r+PZFv33FEDJA4spvR+82uz9bPDeHfpJz87kcSlTGfVLYEOP0Lafglzz+pPvmKhUNTtUucdRklM6s9g2CXXl5sv5w0DeF8PWOc45WVvKGMAOygPJOhNFOafpLJxVGTEFcV8n1REMuxEDikGA8b1fSZSPeXViVkB1bWIheR67IRSomtr3unXR4Riihjb3lb2o/VpLu8X/r+Y2nX0QOBzo8Bg5YII+JfuCpVUqJRwp+eASXqj4jZNrNnqahRyPHP7G2pwKINdBLvesxQ3lFVLPjyDEJCEgN3P74F1seSZObrRVwf6Cl5xIe9bVffLXbrXRstFeT1WDgQQTLmUMaa7fLy7n467dZfzJ6gHNEdPAFQpQ6qpyJxPvi02fdsQBZDrQpWQX0BlP8RFagbH++e6cU/elzqRNfSrkUQ4wUmYTwD1PR8X+ztBt52SfuXwS+qX7XpYBxpZ6R76aS7OZsd9lI8WsOsfQjC1X5CfVVSslUHAAA3v/+CwBh8ploi6EpY43KWH5DFN8j7abD9xPr5YPXKvfSk78PVKZhe1jX0ooPGMf1+DxTt2GU4IUATEENWi1n8fb/AKQZBtY=';

  $img['comment.png'] = 'eNrrDPBz5+WS4mJgYOD19HAJAtLcQMzFwQYkkz4saQZSLOmOvo4MDOtPmLN2vQTyJUtcI0qC89NKyhOLUhkcU/KTUhU8cxPTU4NSE1MqC0+m2gAVNXq6OIZU3Er68/+//eGbm9P/GwswHDt2rIcn/dycn/48M+zr/9WLzTj3/f194zl2pVyHXYKNjS1ms0YeOntX1tnwhMCarvK3dzxtJ0smLly4ZEnLohcis37fvXtXUtKhy1FCROv9vb3MyRPOJz46MYPz7q1eaXMjFRUVgyTenJTJhTM5DjMwsjF4rJwklafh3wd0DIOnq5/LOqeEJgBqOVnx';

  $img_out = gzuncompress(base64_decode($img[$name]));
  header ('Content-type: image/png');
  header ('Content-length: '. strlen($img_out));
  echo $img_out;
  exit (0);
}

// ---------

?>
