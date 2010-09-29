#!/usr/bin/perl

use DBI;

my $fifo = "/usr/local/icinga/var/rw/syslog-ng.pipe";

my $db      = "icinga_web";
my $dbhost  = "localhost";
my $dbuser  = "icinga_web";
my $dbpass  = "icinga_web";
my $dbtable = "event_db_event";

my $dbh = DBI->connect("DBI:mysql:$db:$dbhost", $dbuser, $dbpass)
    or die('Unable to connect to icinga-web\'s database.');

my $db_insert = $dbh->prepare("insert into $dbtable " .
                              'set event_type=?, event_host=?, ' .
                              'event_facility=?, event_priority=?, ' .
                              'event_level=?, event_tag=?, ' .
                              'event_created=?, event_modified=?, ' .
                              'event_program=?, event_message=?');

while (1) {
    open( FIFO, $fifo );
    while (<FIFO>) {
        my $type = "syslog";
        my ($host, $facility, $priority, $level, $tag,
            $date, $time, $prg, $msg
           ) = split( /\t/, $_, 9 );

        # by Traps den tatsaechlichen Hostnamen sichern
        if ( $msg =~ m/snmptt\[\d+\]/ ) {
            if ( $msg =~ m/ ([-0-9a-zA-Z.]*) - / ) {
                $host = $1;
                $msg =~ s/snmptt\[\d+\]/snmptrap/;
                $msg =~ s/ $host //;
                $type = "snmptrap";
            }
        }

        my $datetime = $date . " " . $time ;

        $db_insert->execute($type, $host, $facility, $priority,
                            $level, $tag, $datetime, $datetime, $prg, $msg
                           ) if ($host ne "");

    }
    close(FIFO);
}

$dbh->disconnect();
