#! /usr/bin/perl -w
#
# check_syslog-ng.pl - nagios plugin
#
# $Id: check_eventdb.pl 69 2009-10-26 12:31:45Z wpreston $
#
# Copyright (C) 2007 Gerd Mueller / Netways GmbH
# Copyright (C) 2008,20097 NETWAYS GmbH
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
  $opt_program
  $opt_perfdata
  $dbh
  $sth
  $result
  $update
  $matches
  $last
  $startfrom
  @sql
  @params
  $PROGNAME
  $VERSION
);

use subs qw(
	add_part
	myexit
	print_usage
);

$PROGNAME = basename($0);
$VERSION  = '1.3';

# default values
$opt_db       = "eventdb";
$opt_dbtable  = "events";
$opt_dbhost   = "localhost";
$opt_dbuser   = "eventdb";
$opt_dbpasswd = "eventdb";
$opt_host     = "";
$opt_msg      = "";
$opt_priority = "";
$opt_label    = "";
$opt_type     = "";
$opt_facility = "";
$opt_program = "";
$opt_perfdata = "";
$startfrom    = "0";

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
	"perfdata=s"   => \$opt_perfdata,
);

if ( !$opt_critical || !$opt_warning || $opt_help ) {
	print_usage();
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
push @sql, sprintf('SELECT COUNT(uid) as count, MAX(uid) as last from %1$s where uid>%2$d', $opt_dbtable, $startfrom);

add_part('host', $opt_host)
	if ($opt_host ne '');

add_part('message', $opt_msg)
	if ($opt_msg ne '');

add_part('priority', $opt_priority)
	if ($opt_priority ne '');

add_part('type', $opt_type)
	if ($opt_type ne '');

add_part('facility', $opt_facility)
	if ($opt_facility ne '');

add_part('program', $opt_program)
	if ($opt_program ne '');

add_part('acknowledged', '0');


# preparing the query
$sth = $dbh->prepare(join(' ', @sql))
  || myexit( 'UNKNOWN', "Can't prepare the query: ". $dbh->errstr );

# executing the query
$result = $sth->execute(@params)
  || myexit( 'UNKNOWN', "Can't execute the query: ". $sth->errstr );

$matches = 0;
if ($result) {
	while(my @row = $sth->fetchrow_array()) {
		($matches, $last) = @row;
	}

	$sth->finish();
}
if (!($last) or (!($last > 0))) {
	$last = $startfrom;
}


$dbh->disconnect()
  || myexit( 'UNKNOWN', "Can't disconnect: ". $dbh->errstr );;


if ( $matches >= $opt_critical ) {
	myexit( 'CRITICAL', $matches . " matches found!", "matches=$matches count=$last"."c" );
}
elsif ( $matches >= $opt_warning ) {
	myexit( 'WARNING', $matches . " matches found.", "matches=$matches count=$last"."c" );
}
else {
	myexit( 'OK', $matches . " matches found.", "matches=$matches count=$last"."c" );
}

myexit( 'UNKNOWN', 'Default exit' );

sub myexit {
	my $time;
	my $date;

	my ( $status, $text, $perfdata ) = @_;

	my %STATUS_CODE =
	  ( 'UNKNOWN' => '-1', 'OK' => '0', 'WARNING' => '1', 'CRITICAL' => '2' );

	my $out = undef;
	$out .= sprintf('%1$s: %2$s %3$s', $status, $opt_label, $text);
	$out .= sprintf('|%1$s', $perfdata) if ($perfdata);
	$out .= chr(10);

	print $out;

	exit $STATUS_CODE{$status};
}

sub add_part {
	my ($field, $value, $op, $agg) = @_;

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

	push @params, $value;

	return 1;
}

sub print_usage {
	print <<EOU;
    Usage: $PROGNAME [ -H host ] [ -p priority ] [ -t type ] [ -m msg ] [ -P program ] [ --db db ] [ --dbtabe dbtable ] [ --dbuser dbuser ] [ --dbpassword dbpassword ] [ --dbhost dbhost ] [ -l label ] [ --perfdata '\$SERVICEPERFDATA\$' ]  -w warn -c crit

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
    --dbpassword STRING
        Databasepassword (default: none)
    --dbhost STRING
        Databaseserver (default: localhost)
    -l --label STRING
        label for plugin output
    --perfdata STRING
        performance data from the last check (e.g. \$SERVICEPERFDATA\$)
    -w --warning INTEGER
        number matches to result in warning status
    -c --critical INTEGER
        number of matches to result in critical status

EOU

	myexit( "UNKNOWN", $PROGNAME );
}

