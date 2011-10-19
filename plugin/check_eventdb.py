#! /usr/bin/python 

import getopt, time, pprint, sys, re, urllib
from optparse import OptionParser

DEBUG = False

class DatabaseException(Exception):
    def __init__(self,m):
        self.message = m
'''
'   User defined type that hides database-implementation 
'   from executing code
'''
class DBHandler:
    def __importPackages(self):
        if(self.__type == "mysql"):
            import MySQLdb,_mysql_exceptions
        if(self.__type == "oracle"):
            import cx_Oracle
        
    def __init__(self,type):
        self.__type = type                
        self.__importPackages()                
        self.__cursor = False
        self.__connection = False

    def connect(self,host,user,password,database,port=None):
        self.__host = host
        self.__port = port
        self.__database = database
        self.__user = user
        self.__password = password
        if(self.__type == "mysql"):
            self.__connectMysql() 
        elif(self.__type == "oracle"):
            self.__connectOracle()
        
        if(self.__cursor == None):
            raise DatabaseException("Couldn't connect to db")
   
    def __connectMysql(self):
        import MySQLdb,_mysql_exceptions
        self.__connection = MySQLdb.connect(
            host=self.__host,
            user=self.__user,
            passwd=self.__password,
            db=self.__database,
            port=self.__port
        )
        self.__cursor = self.__connection.cursor();

    def __connectOracle(self):
        import cx_Oracle
       
        dsnString = cx_Oracle.makedsn(self.__host,self.__port, self.__database)
        self.__connection = cx_Oracle.connect(
                    user=self.__user,
                    password=self.__password,
                    dsn=dsnString)
      
        self.__cursor = self.__connection.cursor() 
        self.__cursor.execute('ALTER SESSION SET NLS_DATE_FORMAT = "YYYY-MM-DD HH24:MI:SS"')

    def execute(self,query):
	if(DEBUG):
	    print query
        if(self.__type == "mysql"):
            return self.__executeMysql(query) 
        elif(self.__type == "oracle"):
            return self.__executeOracle(query)
          
    def __executeMysql(self,queryString): 
        self.__cursor.execute(queryString)
        result = self.__cursor.fetchall()
        return result

    def __executeOracle(self,queryString):
        self.__cursor.execute(queryString)  
        result = self.__cursor.fetchall();
        return result


    def __del__(self):
        if(hasattr(self,"__cursor") and self.__cursor):
            self.__cursor.close()
        if(hasattr(self,"__connection") and self.__connection):
            self.__connection.close()

    

def main():   
    options = parseArguments()
    options = handleArguments(options)
    
    if(options.print_cv):
        print getCVFilter(options)
        return
    
    try:
        validateArguments(options)    
    
    except Exception, e:
        pluginExit("UNKNOWN","Invalid Arguments",e,options) 
    
    try:
        result = dbQuery(options)  
        if(result):
            checkResult(result[0],result[1],options,result[2])    
    except SystemExit, s:
        raise
    except Exception, e:
        pluginExit("UNKNOWN","An error occured",e,options)


def handleArguments(options):
    a = options.logtype;
    if(a == "syslog"):
        a = 0
    elif(a == "snmptrap"):
        a = 1
    elif(a == "mail"):
        a = 2
    options.logtype = a
    options.startfrom = 0 
    #convert maxage if given
    if(options.maxage):
        convertMaxage(options)
    
    #check if startindex from previous run exists
    if(options.perfdata):
        updateStartIndex(options)
    
    if(options.facility):
        options.facility = options.facility.split(",")
    if(options.priority):
        options.priority = options.priority.split(",")
    
    #set default ports
    if(options.db_port == None):
        if(options.db_type == "mysql"):
            options.db_port = 3306
        elif(options.db_type == "oracle"):
            options.db_port = 1521


    return options

   
def validateArguments(options):
    if(options.warning == -1 or options.critical == -1):
        raise Exception("warning or critical parameter missing")
   

'''
'   When given the startindex of the last check, this function updates the 
'   options.startfrom parameter to the position of the last check (from the checks perfdata)
'''
def updateStartIndex(options):
    idxMatch = re.findall(r'count=(\d+)',options.perfdata);   
    if(len(idxMatch) > 0):
        options.startfrom = int(idxMatch[0]) 
    return options
 
'''
'   Returns the CustomVariable definition required to add special eventDB filter to the icinga-web 
'   cronk
'
'''
def getCVFilter(opts):
    strTpl = "_edb_filter            {'msg': %s, 'sourceExclusion': [%s],'priorityExclusion': [%s], 'facilityExclusion': [%s], 'startTime': %s }"
    msgFilter = "''"
    sourceExclusion = ""
    priorityExclusion = ""
    facilityExclusion = ""
    timespan = -1
    if opts.message != "":
        msgFilter = "{type:'exact','message' : '%s', isRegexp: false}" % opts.message
    if opts.logtype != "":
        arr = ['0','1','2']
        del arr[opts.logtype]
        
        sourceExclusion = ",".join(arr)
    if isinstance(opts.priority,list):
        allPrios = ['0','1','2','3','4','5','6','7'];
        priorityExclusion = ",".join(list(set(allPrios)-set(opts.priority)))
    if isinstance(opts.facility,list):
        allFacs = [];
        for i in range(24):
            allFacs.append(str(i))

        facilityExclusion = ",".join(list(set(allFacs)-set(opts.facility)))
    if hasattr(opts,"start_ts") and opts.start_ts != '':
        timespan = opts.start_ts

    return strTpl % (msgFilter,sourceExclusion, priorityExclusion,facilityExclusion,timespan) 


'''
'   Helper function to convert timespan definitions like 3m, 2h to timespans
'''
def convertMaxage(options): 
    curTime = time.time()
    matches = re.match(r"(\d*?)(d|h|m)",options.maxage)
    matchGroups = matches.groups()
    if(len(matchGroups) != 2):  
        raise Exception("Invalid maxage format")
    
    timeOffset = int(matchGroups[0])
    # modify timestamp to represent the maximum age
    if(matchGroups[1] == 'd'):
        curTime = curTime-timeOffset*86400
    elif(matchGroups[1] == 'h'):
        curTime = curTime-timeOffset*3600
    elif(matchGroups[1] == 'm'):
        curTime = curTime-timeOffset*60
    options.start_ts = curTime
    tmLocal = time.localtime(curTime) 
    options.maxage = "%02d-%02d-%02d %02d:%02d:%02d" % tmLocal[0:6]
    return options

'''
'   Creates and returns an instance of DBHandler for the passed arguments
'   
'''
def setupDB(options):
     db = DBHandler(options.db_type)
     db.connect(
        options.db_host,
        options.db_user,
        options.db_password,
        options.db_name,
        options.db_port
     )
     return db

def dbQuery(options):
    try: 
        db = setupDB(options) 
        query = buildQuery(options)
        cursor = db.execute(query) 
         
        values = [0,0] 
        for row in cursor:
            if(len(row) != 2):
                raise DatabaseException("SQL Query failed, returned wrong values")
            values = [row[0],row[1]]
        if(values[1] == None):
            values[1] = 0 
        cursor = db.execute("SELECT message FROM %s WHERE id = %d" % (options.db_table, values[1]))
        for row in cursor:
            values.append(row[0])
            return values
        
        pluginExit('OK',"0 matches found.\n","matches=0 count=%dc" % (options.startfrom),options);

    except SystemExit, e:
        raise
    except Exception, e:
        pluginExit('UNKNOWN', e,"",options)


def checkResult(count, last,options,msg = ""):
  
    #strip newlines from message
    if(msg != ""):
        msg= msg.replace('\n',' ')
    if(count >= options.critical):
        if(options.resetregex and re.search(options.resetregex,msg)):
            pluginExit('OK',"%d matches found\nMatches found already reseted." % (count), 'matches=%d count=%dc' % (count,last),options) 
        else:
            pluginExit('CRITICAL',("%d matches found\n"+msg) % (count), 'matches=%d count=%dc' % (count,last),options) 
    elif(count >= options.warning):
        
        if(options.resetregex  and re.search(options.resetregex,msg)):
            pluginExit('OK',"%d matches found\nMatches found already reseted."% (count), 'matches=%d count=%dc' % (count,last),options) 
        else:
            pluginExit('WARNING',('%d matches found \n,'+msg) % (count), 'matches=%d count=%dc' % (count,last),options) 
    else:
        pluginExit('OK',"%d matches found."%(count),"matches=%d count=%dc"%(count,last),options)

    pluginExit('UNKNOWN', '0 matches found.\n','Default exit',options)



def buildQuery(o):
    queryBase = "SELECT COUNT(id) as count, MAX(id) as last from %s where id > %d " 
    queryBase = queryBase % (o.db_table,o.startfrom)
    urlParams = {} 
    if(o.host != ""):
        queryBase += getWherePart("upper(host_name)",o.host.upper())
        urlParams["host[]"] = o.host
    
    if(o.message != ""):
        queryBase += getWherePart("message",o.message)
        urlParams["message"] = o.message
    
    if(o.priority != ""):
        queryBase += getWherePart("priority",o.priority)
        urlParams["priority[]"] = o.priority
    
    if(o.facility != ""):
        queryBase += getWherePart("facility",o.facility)
        urlParams["facility[]"] = o.facility
    
    if(o.logtype != ""):
        queryBase += getWherePart("type",o.logtype)
        urlParams["type[]"] = o.logtype
    
    if(o.maxage != ""):
        queryBase += getWherePart("created",o.maxage, ">=")
        urlParams["created[]"] = o.maxage
        
    if(o.program != ""):
        queryBase += getWherePart("program",o.program)
        urlParams["program[]"] = o.program

    queryBase += getWherePart("ack",0)
    urlParams["ack"] = "0"  
    o.urlParams = urlParams
  
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


def pluginExit(status,text,perfdata, options):
    statusCode = {'UNKNOWN' : 3, 'OK' : 0, 'WARNING' : 1, 'CRITICAL' : 2}
    out = ""
    if(options.url != "" and hasattr(options,"urlParams")):
        params = urllib.urlencode(options.urlParams);
        link = '<a href="%s?%s">%s</a>' % (options.url,params,text)
        out += "%s: %s %s" % (status, options.label, link) 
    else:
        out += "%s %s %s" % (status,options.label,text)    
        
    out += '|%s' % perfdata
    out += "\nmessage filter: %s" % options.message
    out += "\nreset regexp: %s" % options.resetregex
    out += chr(10)
    print(out)
  
    sys.exit(statusCode[status])
 

def parseArguments():
    parser = OptionParser()
    parser.add_option("-H","--host",dest="host",
                    help="Hostname as logged by the agent", default="")
    parser.add_option("-m","--msg",dest="message",
                    help="Message as logged by the agent (SQL Format wildcards)", default="") 
    parser.add_option("-f","--facility",dest="facility", default="",
                    help="The facilities to respect, comma separated"),
    parser.add_option("-p","--priority",dest="priority",
                    help="Priority as logged by the agent", default="")
    parser.add_option("-t","--type",dest="logtype",type="choice",default="syslog",
                    help="The logtype (syslog,snmptrap,mail)",choices=["syslog","snmptrap","mail"])
    parser.add_option("-P","--program",dest="program",
                    help="Program as logged by the agent", default="")
   
    
    parser.add_option("--db",dest="db_name",
                    help="Name of the database to query",
                    default="eventdb")
    parser.add_option("--dbtype",dest="db_type",type="choice",
                    help="Type of the database to query (mysql or oracle)",
                    default="mysql",choices=["mysql","oracle"])
    parser.add_option("--dbtable",dest="db_table",
                    help="Name of the database table (usually event)",
                    default="event")
    parser.add_option("--dbuser",dest="db_user",
                    help="User for db login",default="")
    parser.add_option("--dbpassword",dest="db_password",
                    help="Password for db login",default="")
    parser.add_option("--dbhost",dest="db_host",
                    help="DB Host", default="localhost")
    parser.add_option("--dbport",dest="db_port",type="int",
                    help="DB Port", default=None)
    parser.add_option("-l","--label",dest="label",
                    help="Label for plugin output", default="")
    parser.add_option("-u","--url",dest="url",
                    help="URL for EventDB link in plugin output", default="")
    parser.add_option("--maxage",dest="maxage",
                    help="Maximum age of EventDB entry (eg. 1m, 2h, 3d)", default="")
    parser.add_option("-r","--resetregexp",dest="resetregex",
                    help="Regular Expression for message entry in eventdb to change each state back to OK", default="")
    parser.add_option("--perfdata",dest="perfdata",
                    help="Performance data from the last check (e.g. \$SERVICEPERFDATA\$)", default="")
    parser.add_option("-w","--warning",dest="warning",type="int",help="Number of matches to result in warning state",default="-1")
    parser.add_option("-c","--critical",dest="critical",type="int",help="Number of matches to result in critical state",default="-1")
    parser.add_option("--cventry",dest="print_cv", default=False,action="store_true",
                    help="returns the custom variable entry for this call (needed in order to use icinga-web cronk integration)")
    (options, args) = parser.parse_args()
    return options;    


  


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

