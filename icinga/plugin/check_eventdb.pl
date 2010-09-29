#! /usr/bin/perl -w
#
# check_eventdb.pl - nagios plugin
#
# $Id: check_eventdb.pl 69 2009-10-26 12:31:45Z wpreston $
#
# Copyright (C) 2007 Gerd Mueller / Netways GmbH
# Copyright (C) 2008,2009 NETWAYS GmbH
# with additions by Rainer Brinkmoeller
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
#

# Changes:
# 
# 2009-06-11: Martin Fuerstenau <mf_at_maerber.de>
#             - Added the ability to check program filed from the database. It can be
#               be delivered by all agents but wasn't used yet 
#             - Added $opt_dbuser and $opt_dbpasswd in "default values".
#               So there is no need to add a lot of users/password in the Nagios command
#               definition file
# 2010-09-26: Eric Lippmann <eric.lippmann@netways.de>
#             - Modified plugin to be compatible with icinga eventdb cronk

use strict;
use Getopt::Long;
use File::Basename;
use DBI;

use vars qw(
  $opt_msg
  $opt_host
  $opt_priority
  $opt_help
  $opt_db
  $opt_dbtable
  $opt_dbuser
  $opt_dbpasswd
  $opt_dbhost
  $opt_critical
  $opt_warning
  $opt_label
  $opt_type
  $opt_facility
  $opt_url
  $opt_maxage
  $opt_resetregexp
  $opt_program
  $opt_perfdata
  $dbh
  $sth
  $result
  $update
  $matches
  $message
  $last
  $startfrom
  @sql
  @params
  @urlparams
  $PROGNAME
  $VERSION
);

use subs qw(
	add_part
	myexit
	print_usage
);

$PROGNAME = basename($0);
$VERSION  = '1.5';

# default values
$opt_db       = 'icinga_web';
$opt_dbtable  = 'event_db_event';
$opt_dbhost   = 'localhost';
$opt_dbuser   = 'icinga_web';
$opt_dbpasswd = 'icinga_web';
$opt_host     = "";
$opt_msg      = "";
$opt_priority = "";
$opt_label    = "";
$opt_type     = "";
$opt_facility = "";
$opt_resetregexp     = "";
$opt_program = "";
$opt_perfdata = "";
$startfrom    = "0";
$opt_maxage   = '';

#GetOptions
Getopt::Long::Configure('bundling');
GetOptions(
	"msg=s"        => \$opt_msg,
	"m=s"          => \$opt_msg,
	"l=s"          => \$opt_label,
	"label=s"      => \$opt_label,
	"host=s"       => \$opt_host,
	"H=s"          => \$opt_host,
	"t=s"          => \$opt_type,
	"type=s"       => \$opt_type,
	"f=s"          => \$opt_facility,
	"facility=s"   => \$opt_facility,
	"priority=s"   => \$opt_priority,
	"p=s"          => \$opt_priority,
	"resetregexp=s"=> \$opt_resetregexp,
	"r=s"          => \$opt_resetregexp,
	"program=s"    => \$opt_program,
	"P=s"          => \$opt_program,
	"help"         => \$opt_help,
	"h"            => \$opt_help,
	"db=s"         => \$opt_db,
	"dbtable=s"    => \$opt_dbtable,
	"dbuser=s"     => \$opt_dbuser,
	"dbpassword=s" => \$opt_dbpasswd,
	"dbhost=s"     => \$opt_dbhost,
	"critical=i"   => \$opt_critical,
	"c=i"          => \$opt_critical,
	"warning=i"    => \$opt_warning,
	"w=i"          => \$opt_warning,
	"url=s"        => \$opt_url,
	"u=s"          => \$opt_url,
	"maxage=s"     => \$opt_maxage,
	"perfdata=s"   => \$opt_perfdata,
);

if ( !$opt_critical || !$opt_warning || $opt_help ) {
	print_usage();
}

# convert $opt_maxage, if defined
if ($opt_maxage ne '') {
	# first, make a timestamp
	my $timestamp = time();

	# then split integer from string
	my $int = (split(/(d|h|m)/, $opt_maxage))[0];

	# and subduct from timestamp
	$timestamp = $timestamp - $int * 86400 if $opt_maxage =~ /d/;	# days
	$timestamp = $timestamp - $int * 3600  if $opt_maxage =~ /h/;   # hours
	$timestamp = $timestamp - $int * 60    if $opt_maxage =~ /m/;   # minutes

	# format timestamp to mysql datetime
	my ($sec,$min,$hour,$mday,$mon,$year) = (localtime($timestamp))[0,1,2,3,4,5];
	$opt_maxage = sprintf("%02d-%02d-%02d %02d:%02d:%02d", $year+1900,$mon+1,$mday,$hour,$min,$sec);
}

# count from the last match
if ($opt_perfdata) {
	$opt_perfdata =~ /count=(\d*)/;
	$startfrom = $1 if ($1 and ($1 > 0));
}


# Connecting to Database
$dbh = DBI->connect( "DBI:mysql:database=$opt_db;host=$opt_dbhost",
	$opt_dbuser, $opt_dbpasswd, { PrintError => 0 } )
  || myexit( 'UNKNOWN', "can't connect to database!" );

# Count matches
push @sql, sprintf('SELECT COUNT(event_id) as count, MAX(event_id) as last from %1$s where event_id>%2$d', $opt_dbtable, $startfrom);

add_part('event_host', 1, $opt_host)
	if ($opt_host ne '');

add_part('event_message', 0, $opt_msg)
	if ($opt_msg ne '');

add_part('event_priority', 1, $opt_priority)
	if ($opt_priority ne '');

add_part('event_type', 1, $opt_type)
	if ($opt_type ne '');

add_part('event_facility', 1, $opt_facility)
	if ($opt_facility ne '');

add_part('event_created', 1, $opt_maxage, ">=")
	if ($opt_maxage ne '');

add_part('event_program', 0, $opt_program)
	if ($opt_program ne '');

add_part('event_ack', 0, '0');


# preparing the query
$sth = $dbh->prepare(join(' ', @sql))
  || myexit( 'UNKNOWN', "Can't prepare the query: ". $dbh->errstr );

# executing the query
$result = $sth->execute(@params)
  || myexit( 'UNKNOWN', "Can't execute the query: ". $sth->errstr );

$matches = 0;
$message = "";
if ($result) {
	while(my @row = $sth->fetchrow_array()) {
		($matches, $last) = @row;
		$message = $row[0];
	}

	$sth->finish();
}
if (!($last) or (!($last > 0))) {
	$last = $startfrom;
}


$dbh->disconnect()
  || myexit( 'UNKNOWN', "Can't disconnect: ". $dbh->errstr );;


if ( $matches >= $opt_critical ) {
    if ( $opt_resetregexp eq '' ) {
    	myexit( 'CRITICAL', $message, "matches=$matches count=$last"."c" );
    }
    elsif ( $message =~ m/($opt_resetregexp)/ ) {
    	myexit( 'OK', "Matches found already reseted.", "matches=$matches count=$last"."c" );
    }
    else {
    	myexit( 'CRITICAL', $message, "matches=$matches count=$last"."c" );
    }
	#myexit( 'CRITICAL', $matches . " matches found!", "matches=$matches count=$last"."c" );
}
elsif ( $matches >= $opt_warning ) {
    if ( $opt_resetregexp eq '' ) {
    	myexit( 'WARNING', $message, "matches=$matches count=$last"."c" );
    }
    elsif ( $message =~ m/($opt_resetregexp)/ ) {
    	myexit( 'OK', "Matches found already reseted.", "matches=$matches count=$last"."c" );
    }
    else {
    	myexit( 'WARNING', $message, "matches=$matches count=$last"."c" );
    }
	#myexit( 'WARNING', $matches . " matches found.", "matches=$matches count=$last"."c" );
}
else {
	myexit( 'OK', $matches . " matches found.", "matches=$matches count=$last"."c" );
}

myexit( 'UNKNOWN', 'Default exit' );

sub urlencode {
	my $str = "@_";
	$str =~ s/([^A-Za-z0-9])/sprintf("%%%02X", ord($1))/seg;
	return $str;
}

sub myexit {
	my $time;
	my $date;

	my ( $status, $text, $perfdata ) = @_;

	my %STATUS_CODE =
	  ( 'UNKNOWN' => '-1', 'OK' => '0', 'WARNING' => '1', 'CRITICAL' => '2' );

	my $out = undef;
	if ($opt_url) {
		$out .= sprintf('%1$s: %2$s %3$s', $status, $opt_label, 
		'<a href="' . $opt_url . '?' . join('&', @urlparams) . '">' . $text . '</a>');
	} else {	
		$out .= sprintf('%1$s: %2$s %3$s', $status, $opt_label, $text);
	}	
	$out .= sprintf('|%1$s', $perfdata) if ($perfdata);
	$out .= "\nmessage filter: $opt_msg";
	$out .= "\nreset regexp: $opt_resetregexp";
	$out .= chr(10);

	print $out;

	exit $STATUS_CODE{$status};
}

sub add_part {
	my ($field, $is_array, $value, $op, $agg) = @_;

	$agg = 'AND' if (!defined $agg);
	$op = '=' if (!defined $op);

	if ($value =~ m/\*|\%/ && lc($op) ne 'regexp') {
		$op = 'LIKE';
		$value =~ s/\*/\%/g;
	}

	push @sql, sprintf(
		'%1$s %2$s %3$s ?',
		$agg, $field, $op
	);

	if ($is_array == 1) {$field .= '[]'}
	push @urlparams, "$field=" . urlencode($value);
	push @params, $value;

	return 1;
}

sub print_usage {
	print <<EOU;
    Usage: $PROGNAME [ -H host ] [ -p priority ] [ -t type ] [ -m msg ] [ -P program ] [ --db db ] [ --dbtabe dbtable ] [ --dbuser dbuser ] [ --dbpassword dbpassword ] [ --dbhost dbhost ] [ -l label ] [ --perfdata '\$SERVICEPERFDATA\$' ] [ --maxage STRING ] [ -r resetregexp ] -w warn -c crit

    Options:

    -H --host
        Hostname as logged by agent.
    -p --priority
        Priority as logged by agent.
    -m --msg
        Message as logged by agent (SQL Format wildcards).
    -t --type
    	The logtype (e.g. syslog, snmptrap, mail).
    -P --program
        Program as logged by agent.
    --db STRING
        Database (default: eventdb)
    --dbtable STRING
        Tablename (default: events)
    --dbuser STRING
        Databaseuser (default: none)
    --dbpassword STRING
        Databasepassword (default: none)
    --dbhost STRING
        Databaseserver (default: localhost)
    -l --label STRING
        label for plugin output
    -u --url STRING
        URL for EventDB link in plugin output
    --maxage STRING
        Max age of EventDB entry (eg. 1m, 2h, 3d)
    -r --resetregexp STRING
        Regular Expression for message entry in eventdb to change each state back to OK.
    --perfdata STRING
        performance data from the last check (e.g. \$SERVICEPERFDATA\$)
    -w --warning INTEGER
        number matches to result in warning status
    -c --critical INTEGER
        number of matches to result in critical status

EOU

	myexit( "UNKNOWN", $PROGNAME );
}


# define service{
#         use                             generic-service
#         host_name                       localhost
#         service_description             eventdb
#         # critical for all status other than 4.
#         check_command                   check_eventdb!1!1!%Interesting Status:%!-r Status: 4
#         }
# 'check_eventdb' command definition
# 
# define command{
#         command_name            check_eventdb
#         command_line            $USER1$/contrib/check_eventdb.pl --dbuser eventdbrw --dbpassword eventdbrw --url "/nagios/eventdb/index.php" -w $ARG1$ -c $ARG2$ -m "$ARG3$" "$ARG4$"
# }

