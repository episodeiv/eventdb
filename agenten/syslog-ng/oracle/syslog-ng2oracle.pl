#!/usr/bin/perl
use constant IPV6 => true;
use DBI;
use Socket qw(inet_aton);
use NetAddr::IP::Util qw(
	ipanyto6
	ipv6_aton
);

my $fifo = "/usr/local/icinga/var/rw/syslog-ng.pipe";

my $db      = "XE";
my $dbhost  = "localhost";
my $dbuser  = "eventdb";
my $dbpass  = "eventdb";
my $dbtable = "event";
my $dbport = 1521;
my $dbh;
my $db_insert;

while (1) {
    open( FIFO, $fifo );
    while (<FIFO>) {

	 
		my $type = 0;
        my ($host, $host_address, $prio, 
            $date, $time, $prg, $msg
           ) = split( /\t/, $_, 8 );
		$facility = int($prio/8);
		$priority = $prio%8;

		
		if($host_address =~ m/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/) {
			$host_address = "::ffff:".$host_address;	
		}
			
		$host_address = ipv6_aton($host_address);
			

	
       
		# Save real hostname if trap	
        if ( $msg =~ m/snmptt\[\d+\]/ ) {
            if ( $msg =~ m/ ([-0-9a-zA-Z.]*) - / ) {
                $host = $1;
                $msg =~ s/snmptt\[\d+\]/snmptrap/;
                $msg =~ s/ $host //;
                $type = 1;
            }
        }

		if($host ne "") {
		
            if($dbh = DBI->connect_cached("DBI:Oracle:SID=".$db.";host=".$dbhost.";port=".$dbport, $dbuser, $dbpass)) {
		# Tell oracle which dateformat to use
		$dbh->prepare('ALTER SESSION SET NLS_DATE_FORMAT = "YYYY-MM-DD HH24:MI:SS"')->execute();
		$db_insert = $dbh->prepare("insert into $dbtable" .
                              '(type, host_name, ' .
							  'host_address, '.
                              'facility, priority, ' . 
                              'created, modified, ' .
                              'program, message) VALUES (?,?,utl_raw.cast_to_raw(?),?,?,?,?,?,?)');
 
               my $datetime = $date . " " . $time ;

                $db_insert->execute($type, $host,$host_address, $facility, $priority,
                            $datetime, $datetime, $prg, $msg
                           ) or warn $DBI::errstr;
		    } else {
                warn("Could not establish db connection");
                sleep(5);
            }
        }
    }
    close(FIFO);
}

$dbh->disconnect();
