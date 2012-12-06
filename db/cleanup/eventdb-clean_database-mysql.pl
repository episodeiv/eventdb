#!/usr/bin/perl
use constant IPV6 => true;
use DBI;
use Socket qw(inet_aton);
use NetAddr::IP::Util qw(
	ipanyto6
	ipv6_aton
);


my $db      = "eventdb";
my $dbhost  = "localhost";
my $dbuser  = "eventdb";
my $dbpass  = "eventdb";
my $dbtable = "event";
my $dbport = 3306;
my $backupFolder = "/tmp/";

my $dbh;
my $db_insert;

# max age in days 
my $maxage = 14;
my $onlyAcks = 0;

my $date = `date --date '-$maxage days' "+%Y-%m-%d"`; 
open FILE, ">".$backupFolder."eventdb-".$date or die $!;

if($dbh = DBI->connect("DBI:mysql:$db:$host", $dbuser, $dbpass)) {
		
	my $events = $dbh->prepare('SELECT * FROM event WHERE created < ROUND(CURRENT_DATE)-'.$maxage.' AND ack >= '.$onlyAcks);
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
	$dbh->prepare('DELETE FROM  event WHERE created < ROUND(CURRENT_DATE)-'.$maxage.' AND ack >= '.$onlyAcks)->execute();
}
close FILE;
$file = $backupFolder."eventdb-".$date;
`bzip2 $file`;
if($?) {
	
	`rm $file`;
}
