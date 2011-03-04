#!/bin/bash

DATE=`date +%Y-%m-%d-$$`
PURGEDATE=`date --date '1 week ago' "+%Y-%m-%d %H:%M:%S"`

# store old data
mysql eventdb --execute="SELECT * INTO OUTFILE '/var/backups/syslog_save/$DATE.csv' FIELDS TERMINATED BY '\t' FROM event WHERE created < '$PURGEDATE';"
bzip2 /var/backups/syslog_save/$DATE.csv

# delete old data
mysql eventdb --execute="DELETE FROM event WHERE created < '$PURGEDATE';"

# optimize table
mysql eventdb --execute="optimize table event;"
