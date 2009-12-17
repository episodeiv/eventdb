#!/bin/bash

# calculate threshold for purge
OLDDATE=‘date --date ’-2 weeks’ "+%Y-%m-%d %H:%M:%S"‘

# create backup dir
[[ -d /var/backups/eventdb/ ]] || mkdir -p /var/backups/eventdb/

# mysql connection
MYSQL="mysql --user=eventdb --passwordmypassword eventdb"

# backup
$MYSQL --execute="SELECT * INTO OUTFILE ’/var/backups/eventdb/$OLDDATE.txt’ FIELDS TERMINATED BY ’\t’ FROM events WHERE datetime < ’$OLDDATE’ AND acknowledged;"

# clean
$MYSQL --execute="DELETE FROM events WHERE datetime < ’$OLDDATE’ AND acknowledged;"
$MYSQL --execute="optimize table events;"
