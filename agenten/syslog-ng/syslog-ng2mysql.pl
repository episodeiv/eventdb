#!/usr/bin/perl
use constant IPV6 => true;
use DBI;
use Socket qw(inet_aton);


if(IPV6) {
	use NetAddr::IP::Util qw(
		ipanyto6
		ipv6_aton
	);
}
my $fifo = "/usr/local/icinga/var/rw/syslog-ng.pipe";

my $db      = "eventdb";
my $dbhost  = "localhost";
my $dbuser  = "eventdb";
my $dbpass  = "eventdb";
my $dbtable = "event";

my $dbh = DBI->connect("DBI:mysql:$db:$dbhost", $dbuser, $dbpass)
    or die('Unable to connect to icinga-web\'s database.');

my $db_insert = $dbh->prepare("insert into $dbtable set " .
                              'type=?, host_name=?, ' .
							  'host_address=?, '.
                              'facility=?, priority=?, ' . 
                              'created=?, modified=?, ' .
                              'program=?, message=?');

while (1) {
    open( FIFO, $fifo );
    while (<FIFO>) {

	 
		my $type = 0;
        my ($host, $host_address, $prio, 
            $date, $time, $prg, $msg
           ) = split( /\t/, $_, 8 );
		$facility = int($prio/8);
		$priority = $prio%8;

		if(IPV6) {
			if($host_address =~ m/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/) {
				$host_address = "::ffff:".$host_address;	
			}
			
			$host_address = ipv6_aton($host_address);
			print Dumper($host_address);
		} else { 
			$host_address = inet_aton($host_address);
        }
		# by Traps den tatsaechlichen Hostnamen sichern
        if ( $msg =~ m/snmptt\[\d+\]/ ) {
            if ( $msg =~ m/ ([-0-9a-zA-Z.]*) - / ) {
                $host = $1;
                $msg =~ s/snmptt\[\d+\]/snmptrap/;
                $msg =~ s/ $host //;
                $type = 1;
            }
        }
		

        my $datetime = $date . " " . $time ;

        $db_insert->execute($type, $host,$host_address, $facility, $priority,
                            $datetime, $datetime, $prg, $msg
                           ) if ($host ne "");

    }
    close(FIFO);
}

$dbh->disconnect();
