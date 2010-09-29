#!/usr/bin/perl

use DBI;

my $fifo = "/var/run/syslog.pipe";

my $db      = "eventdb";
my $dbhost  = "localhost";
my $dbuser  = "eventdb";
my $dbpass  = "eventdb";
my $dbtable = "events";

my $dbh = DBI->connect( "DBI:mysql:$db:$dbhost", $dbuser, $dbpass )
  or die("no connect to syslog db!");

my $db_insert =
  $dbh->prepare( "insert into $dbtable "
	  . "set type=?, host=?,facility=?,priority=?,level=?,tag=?,program=?,message=?"
  );


while (1) {
	open( FIFO, $fifo );
	while (<FIFO>) {
		my (
			$host, $facility, $priority, $level, $tag,
			$date, $prg,      $msg, $oid
		  );

		$line =$_;

		if ( $line =~  m/(\w +\d+ [0-9:]+) [^ ]+ snmptt\[\d+\]: ([.0-9]+) ([^ ]+) "(.*)" ([^ ]+) - (.*)/ ) {
			$oid = $2;
			$priority = $3;
			$facility = $4;
			$host = lc($5);
			$msg = $6." (OID: ".$oid.")";

			$level = $facility;

			$prg = "snmptrap";
			$type = "snmptrap";
			$tag = "snmptrap";
	
		}

		$db_insert->execute( $type, $host, $facility, $priority, $level, $tag, $prg, $msg ) if ( $host ne "" );

	}
	close(FIFO);
}

$dbh->disconnect();


