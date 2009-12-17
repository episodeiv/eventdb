#!/bin/bash

DATE=`date +%Y-%m-%d-$$`
PURGEDATE=`date --date '1 week ago' "+%Y-%m-%d %H:%M:%S"`

# store old data
mysql eventdb --execute="SELECT * INTO OUTFILE '/var/backups/syslog_save/$DATE.csv' FIELDS TERMINATED BY '\t' FROM events WHERE datetime < '$PURGEDATE';"
bzip2 /var/backups/syslog_save/$DATE.csv

# delete old data
mysql eventdb --execute="DELETE FROM events WHERE datetime < '$PURGEDATE';"

# optimize table
mysql eventdb --execute="optimize table events;"
