-- 
-- Oracle Procedure for CSV backup
-- Make sure to setup oracle correctly, so it can write to the output folder and define the output folder
-- via (for example) : 
-- CREATE OR REPLACE DIRECTORY EVENTDB_LOG AS '/usr/local/backup/';


CREATE OR REPLACE PROCEDURE backup_eventdb (folder VARCHAR2, csvfile VARCHAR2, maxage NUMBER, ackonly NUMBER)
AS
        outFile UTL_FILE.FILE_TYPE;
BEGIN
        outFile := UTL_FILE.FOPEN(folder, csvfile, 'W'); 
        FOR s IN (SELECT * FROM event WHERE ROUND(CURRENT_DATE)-ROUND(created) > maxage AND ack >= ackonly)
        LOOP      
            UTL_FILE.PUTF(outFile,'%s ; %s ; %s ;', TO_CHAR(s.CREATED,'DD-MM-YYYY HH:MI:SS'), s.HOST_NAME,  s.PROGRAM );
            UTL_FILE.PUTF(outFile,'%s ; %s ;',s.TYPE, Replace(s.MESSAGE,chr(10),''));
            UTL_FILE.PUTF(outFile,'%s ; %s ; %s ; %s ', s.FACILITY, s.PRIORITY, s.ACK, s.HOST_ADDRESS);              
            UTL_FILE.NEW_LINE(outFile);
        END LOOP;


	UTL_FILE.FCLOSE(outFile);
	
END;
/
