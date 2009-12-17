#!/usr/bin/perl

use DBI;

my $fifo = "/usr/local/nagios/var/rw/syslog-ng.pipe";

my $db      = "eventdb";
my $dbhost  = "localhost";
my $dbuser  = "eventdbrw";
my $dbpass  = "eventdbrw";
my $dbtable = "events";

my $dbh = DBI->connect( "DBI:mysql:$db:$dbhost", $dbuser, $dbpass )
  or die("no connect to syslog db!");

my $db_insert =
  $dbh->prepare( "insert into $dbtable "
	  . "set type=?, host=?,facility=?,priority=?,level=?,tag=?,datetime=?,program=?,message=?"
  );

while (1) {
	open( FIFO, $fifo );
	while (<FIFO>) {
		my $type = "syslog";
		my (
			$host, $facility, $priority, $level, $tag,
			$date, $time,     $prg,      $msg
		  )
		  = split( /\t/, $_, 9 );

		# by Traps den tatsaechlichen Hostnamen sichern
		if ( $msg =~ m/snmptt\[\d+\]/ ) {
			if ( $msg =~ m/ ([-0-9a-zA-Z.]*) - / ) {
				$host = $1;
				$msg =~ s/snmptt\[\d+\]/snmptrap/;
				$msg =~ s/ $host //;
				$type = "snmptrap";
			}
		}

		$db_insert->execute( $type, $host, $facility, $priority, $level, $tag,
			$date . " " . $time, $prg, $msg )
		  if ( $host ne "" );

	}
	close(FIFO);
}

$dbh->disconnect();


