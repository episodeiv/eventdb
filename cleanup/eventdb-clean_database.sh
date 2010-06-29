#!/bin/bash

###### config begin

#BACKUPDIR=/var/backups/eventdb/
BACKUPDIR=/drbd/backups/eventdb/

# mysql connection as someone
MYSQL="mysql --user=eventdb --passwordmypassword eventdb"
# or run as root
MYSQL="mysql eventdb"

# calculate threshold for purge
OLDDATE=‘date --date ’-2 weeks’ "+%Y-%m-%d %H:%M:%S"‘

###### config end



###### main

# create backup dir
[[ -d $BACKUPDIR ]] || mkdir -p $BACKUPDIR

# backup
$MYSQL --execute="SELECT * INTO OUTFILE ’$BACKUPDIR/$OLDDATE.txt’ FIELDS TERMINATED BY ’\t’ FROM events WHERE datetime < ’$OLDDATE’ AND acknowledged;"

# clean
#$MYSQL --execute="DELETE FROM events WHERE datetime < ’$OLDDATE’ AND acknowledged;"
$MYSQL --execute="DELETE FROM events WHERE datetime < ’$OLDDATE’;"
$MYSQL --execute="optimize table events;"
