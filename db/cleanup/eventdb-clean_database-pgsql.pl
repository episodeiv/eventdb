#!/usr/bin/perl
use constant IPV6 => true;
use DBI;
use Socket qw(inet_aton);
use NetAddr::IP::Util qw(
	ipanyto6
	ipv6_aton
);


my $db      = "eventdb";
my $dbhost  = "127.0.0.1";
my $dbuser  = "eventdb";
my $dbpass  = "eventdb";
my $dbtable = "event";
my $dbport  = 5432;
my $backupFolder = "/tmp/";

my $dbh;
my $db_insert;

# max age in days 
my $maxage = 14;
my $onlyAcks = 0;

my $date = `date --date '-$maxage days' "+%Y-%m-%d"`; 
open FILE, ">".$backupFolder."eventdb-".$date or die $!;

if($dbh = DBI->connect("dbi:Pg:dbname=$db;host=$dbhost;port=$dbport", $dbuser, $dbpass)) {
	# get a actual timestamp; used for export and delete (unix timestamp)
	my $timestamp = $dbh->prepare("SELECT DATE_TRUNC('SECONDS', LOCALTIMESTAMP)");
	$timestamp->execute();
	die "DB error: $timestamp->errstr" if ($timestamp->err);
	$timestamp = $timestamp->fetchrow_array;

	my $events = $dbh->prepare("SELECT * FROM event WHERE created < '$timestamp'::timestamp - interval '$maxage days' AND ack >= $onlyAcks");
	$events->execute();
      
	while (my @event = $events->fetchrow_array) {
	
		my $first = 0;
		foreach(@event) {
			if($first != 0) {
				print FILE " ; ";
			}
			my $entry = $_;
			$entry =~ s/\n//;
			print FILE $entry;
			$first = $first+1;
		}
		print FILE "\n";	
	}
	$events->finish;
	$dbh->prepare("DELETE FROM  event WHERE created < '$timestamp'::timestamp - interval '$maxage days' AND ack >= $onlyAcks")->execute();
}
close FILE;
$file = $backupFolder."eventdb-".$date;
`bzip2 $file`;
if($?) {
	
	unlink $file;
}
