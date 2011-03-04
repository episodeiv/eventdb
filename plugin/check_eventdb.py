#! /usr/bin/python 

import getopt, time, pprint, sys, re, MySQLdb, urllib, _mysql_exceptions
class DatabaseException(BaseException):
    def __init__(self,m):
        self.message = m

opts = {
    'critical': -1,
    'warning' : -1,
    'help' : None,
    'db' : 'eventdb',
    'dbtable' : 'event',
    'dbhost' : 'localhost',
    'dbuser' : 'eventdb',
    'dbpasswd' : 'eventdb',
    'host' : '',
    'msg' : '',
    'priority' : '',
    'label' : '',
    'url' : '',
    'type' : '',
    'facility' : '',
    'resetregexp' : '',
    'program' : '',
    'perfdata' : '',
    'startfrom' : 0,
    'urlParams' : {},
    'start_ts' : '',
    'maxage' : ''
}

optDef = 'm:l:H:t:f:r:p:P:hc:w:u:'
longOptDef = [
    'msg=',
    'label=',
    'host=',
    'type=',
    'facility=',
    'priority=',
    'resetregexp=',
    'program=',
    'help',
    'db=',
    'dbtable=',
    'dbuser=',
    'dbpassword)',
    'dbhost=',
    'critical=',
    'warning=',
    'url',
    'maxage=',
    'perfdata=',
    'cventry'
]

def main():
    showCV = 0
    try:
        arg_opts, args = getopt.getopt(sys.argv[1:],optDef,longOptDef)
       
        for o, a in arg_opts:
            if o in ('--msg','-m'):
                opts['msg'] = a
            if o in ('--label','-l'):
                opts['label'] = a
            if o in ('--host','-H'):
                opts['host'] = a
            if o in ('--type','-t'): 
                if(a == "syslog"):
                    a = 0
                elif(a == "snmptrap"):
                    a = 1
                elif(a == "mail"):
                    a = 2
                else:
                    raise BaseException("Invalid type")
                opts['type'] = a
            if o in ('--facility','-f'):
                opts['facility'] = a.split(',')
            if o in ('--priority','-p'):
                opts['priority'] = a.split(',')
            if o in ('--resetregexp','-r'):
                opts['resetregexp'] = a
            if o in ('--program','-P'):
                opts['program'] = a
            if o in ('--help','-h'): 
                usage()
                return 
               
            if o == '--db':
                opts['db'] = a
            if o == '--dbtable':
                opts['dbtable'] = a
            if o == '--dbuser':
                opts['dbuser'] = a
            if o == '--dbpassword':
                opts['dbpassword'] = a
            if o in ('-c','--critical'): 
                opts['critical'] = int(a)
            if o in ('-u','--url'): 
                opts['url'] = a    
            if o in ('-w','--warning'):
                opts['warning'] = int(a)
            if o == '--maxage':
                opts['maxage'] = a
                convertMaxage()
            if o == '--perfdata':
                opts['perfdata'] = a
                updateStartIndex()
            if o == '--cventry':
                showCV = 1       
    except BaseException, e:
        usage()

    if(showCV == 1):
        print getCVFilter()
        return 
    try:
        if(opts["critical"] == -1  or  opts["warning"] == -1):
            raise BaseException("Invalid arguments")    
    except BaseException, e: 
        usage() 
        pluginExit("UNKNOWN","Invalid Arguments",e) 

    result = dbQuery()  
    if(result):
        checkResult(result[0],result[1],result[2])


def updateStartIndex():
   
    idxMatch = re.findall(r'count=(\d+)',opts['perfdata']);   
    if(len(idxMatch) > 0):
        opts['startfrom'] = int(idxMatch[0]) 
    
 
def getCVFilter():
    strTpl = "_edb_filter            {'msg': %s, 'sourceExclusion': [%s],'priorityExclusion': [%s], 'facilityExclusion': [%s], 'startTime': %s }"
    msgFilter = "''"
    sourceExclusion = ""
    priorityExclusion = ""
    facilityExclusion = ""
    timespan = -1
    if opts["msg"] != "":
        msgFilter = "{type:'exact','message' : '%s', isRegexp: false}" % opts["msg"]
    if opts["type"] != "":
        arr = [0,1,2]
        del arr[opts["type"]]
        sourceExclusion = ",".join(arr)
    if isinstance(opts["priority"],list):
        allPrios = ['0','1','2','3','4','5','6','7'];
        priorityExclusion = ",".join(list(set(allPrios)-set(opts["priority"])))
    if isinstance(opts["facility"],list):
        allFacs = [];
        for i in range(24):
            allFacs.append(str(i))

        facilityExclusion = ",".join(list(set(allFacs)-set(opts["facility"])))
    if opts['start_ts'] != '':
        timespan = opts['start_ts']

    return strTpl % (msgFilter,sourceExclusion, priorityExclusion,facilityExclusion,timespan) 


def convertMaxage(): 
    curTime = time.time()
    matches = re.match(r"(\d*?)(d|h|m)",opts["maxage"])
    matchGroups = matches.groups()
    if(len(matchGroups) != 2):  
        raise BaseException("Invalid maxage format")
    
    timeOffset = int(matchGroups[0])
    # modify timestamp to represent the maximum age
    if(matchGroups[1] == 'd'):
        curTime = curTime-timeOffset*86400
    elif(matchGroups[1] == 'h'):
        curTime = curTime-timeOffset*3600
    elif(matchGroups[1] == 'm'):
        curTime = curTime-timeOffset*60
    opts['start_ts'] = curTime
    tmLocal = time.localtime(curTime) 
    opts["maxage"] = "%02d-%02d-%02d %02d:%02d:%02d" % tmLocal[0:6]



def dbQuery():
    try: 
        conn = MySQLdb.connect(opts["dbhost"],opts["dbuser"],opts["dbpasswd"],opts["db"]) 
        cursor = conn.cursor();
        if(cursor == None):
            raise DatabaseException("Couldn't connect to db")
        query = buildQuery()
        cursor.execute(query)
        values = [0,0] 
        for row in cursor:
            if(len(row) != 2):
                raise DatabaseException("SQL Query failed, returned wrong values")
            values = [row[0],row[1]]
        if(values[1] == None):
            values[1] = 0 
        cursor.execute("SELECT message FROM %s WHERE id = %d" % (opts['dbtable'], values[1]))
        for row in cursor:
            values.append(row[0])
            return values
        
        pluginExit('OK',"0 matches found.","matches=0 count=0")
    except DatabaseException, d:
        pluginExit('UNKNOWN', d)
    except _mysql_exceptions.OperationalError, e:
        pluginExit('UNKNOWN', e)

def checkResult(count, last,msg = ""):
    #strip newlines from message
    msg= msg.replace('\n',' ')
    if(count >= opts["critical"]):
        if(opts["resetregexp"] and re.search(opts["resetregexp"],msg)):
            pluginExit('OK',"Matches found already reseted.", 'matches=%d count=%dc' % (count,last)) 
        else:
            pluginExit('CRITICAL',msg, 'matches=%d count=%dc' % (count,last)) 
    elif(count >= opts['warning']):
        
        if(opts["resetregexp"]  and re.search(opts["resetregexp"],msg)):
            pluginExit('OK',"Matches found already reseted.", 'matches=%d count=%dc' % (count,last)) 
        else:
            pluginExit('WARNING',msg, 'matches=%d count=%dc' % (count,last)) 
    else:
        pluginExit('OK',"%d matches found."%(count),"matches=%d count=%dc"%(count,last))

    pluginExit('UNKNOWN', 'Default exit')



def buildQuery():
    queryBase = "SELECT COUNT(id) as count, MAX(id) as last from %s where id > %d " 
    queryBase = queryBase % (opts["dbtable"],opts['startfrom'])
    
    if(opts["host"] != ""):
        queryBase += getWherePart("host_name",opts["host"])
        opts['urlParams']["host[]"] = opts["host"]
    
    if(opts["msg"] != ""):
        queryBase += getWherePart("message",opts["msg"])
        opts['urlParams']["message"] = opts["msg"]
    
    if(opts["priority"] != ""):
        queryBase += getWherePart("priority",opts["priority"])
        opts['urlParams']["priority[]"] = opts["priority"]
    
    if(opts["facility"] != ""):
        queryBase += getWherePart("facility",opts["facility"])
        opts['urlParams']["facility[]"] = opts["facility"]
    
    if(opts["type"] != ""):
        queryBase += getWherePart("type",opts["type"])
        opts['urlParams']["type[]"] = opts["type"]
    
    if(opts["maxage"] != ""):
        queryBase += getWherePart("created",opts["maxage"], ">=")
        opts['urlParams']["created[]"] = opts["maxage"]
        
    if(opts["program"] != ""):
        queryBase += getWherePart("program",opts["program"])
        opts['urlParams']["program[]"] = opts["program"]

    queryBase += getWherePart("ack",0)
    opts['urlParams']["ack"] = "0"  
    return queryBase; 





def getWherePart(field,value,op = "=",agg = "AND"):
    
    if(isinstance(value,list)):
        op = "IN"
        value = "("+",".join(value)+")"
    else: 
        value = str(value)
        if(re.search(r"\*|\%",value) != None):
            op = "LIKE"
            value = re.sub(r"(\*)","%",value)
    tpl = "%s %s %s "
    if(value.isdigit()):
        tpl += "%d "
        value = int(value)
    elif op != 'IN':
        tpl += "'%s' "
    else:
        tpl += "%s "
    return tpl % (agg,field,op,value) 


def pluginExit(status,text,perfdata = ""):
    statusCode = {'UNKNOWN' : -1, 'OK' : 0, 'WARNING' : 1, 'CRITICAL' : 2}
    out = ""
    if(opts["url"] != ""):
        params = urllib.urlencode(opts['urlParams']);
        link = '<a href="%s?%s">%s</a>' % (opts["url"],params,text)
        out += "%s: %s %s" % (status, opts['label'], link) 
    else:
        out += "%s %s %s" % (status,opts['label'],text)    
        
    out += '|%s' % perfdata
    out += "\nmessage filter: %s" % opts['msg']
    out += "\nreset regexp: %s" % opts['resetregexp']
    out += chr(10)
    print(out)
  
    sys.exit(statusCode[status])
    
   


def usage():
    print """
    Usage: $PROGNAME [ -H host ] [ -p priority ] [ -t type ] [ -m msg ] [ -P program ] [ --db db ] [ --dbtabe dbtable ] [ --dbuser dbuser ] [ --dbpassword dbpassword ] [ --dbhost dbhost ] [ -l label ] [ --perfdata '\$SERVICEPERFDATA\$' ] [ --maxage STRING ] [ -r resetregexp ] -w warn -c crit

    Options:

    -H --host
        Hostname as logged by agent.
    -p --priority
        Priority as logged by agent.
    -m --msg
        Message as logged by agent (SQL Format wildcards).
    -t --type
        The logtype (e.g. syslog, snmptrap, mail).
    -P --program
        Program as logged by agent.
    --db STRING
        Database (default: eventdb)
    --dbtable STRING
        Tablename (default: events)
    --dbuser STRING
        Databaseuser (default: none)
    --dbpassword STRING
        Databasepassword (default: none)
    --dbhost STRING
        Databaseserver (default: localhost)
    -l --label STRING
        label for plugin output
    -u --url STRING
        URL for EventDB link in plugin output
    --maxage STRING
        Max age of EventDB entry (eg. 1m, 2h, 3d)
    -r --resetregexp STRING
        Regular Expression for message entry in eventdb to change each state back to OK.
    --perfdata STRING
        performance data from the last check (e.g. \$SERVICEPERFDATA\$)
    -w --warning INTEGER
        number matches to result in warning status
    -c --critical INTEGER
        number of matches to result in critical status
    --cventry
        returns the custom variable entry for this call 
        (needed in order to use icinga-web cronk integration)
    """

if __name__ == "__main__":
    main()


# define service{
#         use                             generic-service
#         host_name                       localhost
#         service_description             eventdb
#          _edb_filter                     {..result From cventry..}   
#           # critical for all status other than 4.
#         check_command                   check_eventdb!1!1!%Interesting Status:%!-r Status: 4
#         }
# 'check_eventdb' command definition
# 
# define command{
#         command_name            check_eventdb
#         command_line            $USER1$/contrib/check_eventdb.pl --dbuser eventdbrw --dbpassword eventdbrw --url "/nagios/eventdb/index.php" -w $ARG1$ -c $ARG2$ -m "$ARG3$" "$ARG4$"
# }

