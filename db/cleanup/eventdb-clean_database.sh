#!/bin/bash

###### config begin

# backupdir
#BACKUPDIR=/var/backups/eventdb/
BACKUPDIR=/drbd/backups/eventdb/

# mysql connection as someone
#MYSQL="mysql --user=eventdb --passwordmypassword eventdb"
# or run as root
MYSQL="mysql eventdb"

# calculate threshold for purge
OLDDATE=`date --date '-2 weeks' "+%Y-%m-%d %H:%M:%S"`

# clean all (leave empty) or only acknowledged items
#ACKNOWLEDGED="AND acknowledged"
ACKNOWLEDGED=""

###### config end



###### main

# create backup dir
[[ -d $BACKUPDIR ]] || mkdir -p $BACKUPDIR

# backup
$MYSQL --execute="SELECT * INTO OUTFILE '$BACKUPDIR/$OLDDATE.txt' FIELDS TERMINATED BY '\t' FROM event WHERE created < '$OLDDATE' $ACKNOWLEDGED;"

# clean
$MYSQL --execute="DELETE FROM event WHERE created < '$OLDDATE' $ACKNOWLEDGED;"
$MYSQL --execute="optimize table event;"
