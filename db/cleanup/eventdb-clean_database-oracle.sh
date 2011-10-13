#!/bin/bash

###### config begin

###### ORACLE enviromnent setting (comment them out if your profile defines them)
ORACLE_HOME=/usr/lib/oracle/xe/app/oracle/product/10.2.0/server
export ORACLE_HOME
ORACLE_SID=XE
export ORACLE_SID
NLS_LANG=`$ORACLE_HOME/bin/nls_lang.sh`
export NLS_LANG
PATH=$ORACLE_HOME/bin:$PATH
export PATH

# backupdir
#BACKUPDIR=/var/backups/eventdb/
BACKUPDIR=/tmp/


# oracle connection
# or run as root
SQLPLUS=sqlplus
USER=eventdb
PASSWORD=eventdb
DB=XE

# calculate threshold for purge (in days)
MAXAGE=14

# date that will be appended to the file
OLDDATE=`date --date "$MAXAGE days"  "+%Y-%m-%d"` 

# clean all (leave empty) or only acknowledged items
#ACKNOWLEDGED=1 
ACKNOWLEDGED=0

###### config end
###### main
# create backup dir
[[ -d $BACKUPDIR ]] || mkdir -p $BACKUPDIR


FILENAME="eventdb-$OLDDATE.csv"
#### execute the sql command
$SQLPLUS $USER/$PASSWORD@$DB  <<ENDOFSQL

whenever sqlerror exit sql.sqlcode;

EXEC backup_eventdb('$BACKUPDIR','$FILENAME',$MAXAGE,$ACKNOWLEDGED);

DELETE FROM event WHERE ROUND(CURRENT_DATE)-ROUND(created) > $MAXAGE AND ack >= $ACKNOWLEDGED;

ENDOFSQL

#Check the return code from SQL Plus
if [ $? != 0 ]
then
	echo "ERROR: The backup failed. ErrorCode: $ERRORCODE"
	exit 1
fi

if [ -f "$BACKUPDIR/$FILENAME" ] 
then
	bzip2 "$BACKUPDIR/$FILENAME"
	if [ $? == 0 ]
		then rm "$BACKUPDIR/$FILENAME"
	fi
else
	echo "No events found"
fi	

## Try to compress the result (if a result exists)


