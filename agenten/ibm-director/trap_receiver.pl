#!/usr/bin/perl -w

# alle Variablen muessen definiert sein
use strict;

# Datenbankinterface
use DBI;

# Programmname und ersten Parameter speichern 
my $program = $0;
my $type    = $ARGV[0];

# Variablen fuer die Verbindung zur Datenbank
my $db      = "eventdb";
my $dbhost  = "localhost";
my $dbuser  = "root";
my $dbpass  = "";
my $dbtable = "events";

# connect to database
my $dbh = DBI->connect( "DBI:mysql:$db:$dbhost", $dbuser, $dbpass )
  or die("no connect to eventdb!");

# insert vorbereiten
my $db_insert =
  $dbh->prepare( "insert into $dbtable "
          . "set type=?, host=?,facility=?,priority=?,level=?,tag=?,datetime=now(),program=?,message=?"); 

my %trap;

# Zuordnung von Oids zu Spalten der Tabelle events in der Datenbank
my %oids=('.1.3.6.1.4.1.2.6.159.202.2'=> 'priority' ,
          '.1.3.6.1.4.1.2.6.159.202.4'=> 'host',
          '.1.3.6.1.4.1.2.6.159.202.5'=> 'message',
          '.1.3.6.1.4.1.2.6.159.202.6'=> 'facility');

# open (LOG,">>/var/log/trap_receiver.log");
# print LOG "---\n";
while(<STDIN>) {
 chomp();
 # print LOG $_;

 # Zeilen des Traps aufteilen in Oid und Wert
 my ($oid,$value) = split(/ /,$_,2);

 # Anfuehrungsstriche vom Wert entfernen
 $value =~ s/^"(.*)"$/$1/;

 # und interessante Werte merken
 $trap{$oids{$oid}}=$value if($oids{$oid});
}
# close (LOG);

# domains vom hostnamen entfernen (alles ab den ersten Punkt wird entfernt) 
$trap{'host'}=$1 if($trap{'host'} =~ m/([^.]*)/);

# in Datenbank eintragen
$db_insert->execute( $type, 
                     $trap{'host'}, 
                     $trap{'facility'}, 
                     $trap{'priority'}, 
                     "", 
                     "",
                     $program, 
                     $trap{'message'});

# Datenbankverbindung trennen
$dbh->disconnect();
