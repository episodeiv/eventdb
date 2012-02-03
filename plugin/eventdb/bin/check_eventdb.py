import struct
import socket
#! /usr/bin/python

from twisted.python.compat import inet_pton
import os.path

import getopt, pprint, sys, re, urllib
import eventdb

from eventdb.checkfilter import *
from eventdb.dbhandler import *
from optparse import OptionParser
from eventdb.connPoolDaemonProxy import *

class CheckStatusException(Exception):
    def __init__(self,status,output,perfdata = ""):
        self.status = status
        self.output = output
        self.perfdata = perfdata


class EventDBPlugin(object):
    def __init__(self,arguments = None,noExit = False):
        self.__noExit = noExit;
        self.__requestStrategies = [DBHandler()]
        if arguments == None:
            self.__options = self.__parseArguments()
        elif isinstance(arguments,object):
            self.__options = arguments;
  
        self.__prepareArguments()
        self.__runCheck()

  
    def __prepareArguments(self):
        self.__handleArguments()
        if(self.__options.print_cv):
            print self.__getCVFilter()
            return
        try:
            self.__validateArguments()


        except Exception, e:
            self.__pluginExit("UNKNOWN","Invalid Arguments",e)


    def __runCheck(self):
        options = self.__options
        result = False

        try:
            if options.daemon_pid != False:
                self.__requestStrategies.insert(
                    0,
                    ConnPoolDaemonProxy(
                        options.daemon_pid,
                        options.daemon_log,
                        options.daemon_behaviour,
                        options.daemon_lifetime
                    )
                )
            if result == False :
                result = self.__dbQuery()
            if(result):
                self.__checkResult(result[2],result[3],result[0],result[1],result[4])

        except CheckStatusException, cs:
            raise
        except SystemExit, s:
            raise
        except Exception, e:
            self.__pluginExit("UNKNOWN","An error occured",e)


    def __handleArguments(self):
        options = self.__options
        
        self.__checkFilter = CheckFilter()
        self.__checkFilter.setLogtype(options.logtype)
        self.__checkFilter.setMaxage(options.maxage)
        self.__checkFilter.setPerfdata(options.perfdata)
        self.__checkFilter.setFacility(options.facility)
        self.__checkFilter.setPriority(options.priority)
        self.__checkFilter.program = options.program
        self.__checkFilter.message = options.message
        self.__checkFilter.host = options.host
        self.__checkFilter.ipaddress = options.ipaddress
        if(self.__options.facility):
            self.__options.facility = self.__options.facility.split(",")
        if(self.__options.priority):
            self.__options.priority = self.__options.priority.split(",")



    def __validateArguments(self):
        if(self.__options.warning == -1 or self.__options.critical == -1):
            raise Exception("warning or critical parameter missing")

    '''
    '   Returns the CustomVariable definition required to add special eventDB filter to the icinga-web
    '   cronk
    '
    '''
    def __getCVFilter(self):
        opts = self.__options
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
    '   Creates and returns an instance of DBHandler for the passed arguments
    '
    '''
    def __setupDB(self,strategy):
        db = strategy
        db.connect(
            self.__options.db_type,
            self.__options.db_host,
            self.__options.db_user,
            self.__options.db_password,
            self.__options.db_name,
            self.__options.db_port
        )
        return db
   

    def __dbQuery(self):
        for strategy in self.__requestStrategies:

            try :
                db = self.__setupDB(strategy)
                query = self.__buildQuery()

                cursor = db.execute(query)

                values = [0,0]

                
                for row in cursor:
                    if(len(row) != 4):
                        raise DatabaseException("SQL Query failed, returned wrong values")
                    values = [row[0],row[1],row[2],row[3]]
                
                if(values[1] == None):
                    values[1] = 0
                cursor = db.execute("SELECT message FROM %s WHERE id = %d" % (self.__options.db_table, values[1]))
                for row in cursor:
                    values.append(row[0])
                    return values

                self.__pluginExit('OK',"0 critcal and 0 warning matches found.\n","matches=0 count=%dc" % (self.__checkFilter.startfrom));

            except SystemExit, e:
                raise
            except CheckStatusException, cs:
                raise
            except Exception, e:
                if strategy == self.__requestStrategies[-1]:
                    self.__pluginExit('UNKNOWN', "",e)


    def __checkResult(self,warnings,criticals,count, last,msg = ""):

        #strip newlines from message
        if(msg != "" and isinstance(msg,str)):
            msg= msg.replace('\n',' ')
        if(criticals >= self.__options.critical):
            if(self.__options.resetregex and re.search(self.__options.resetregex,msg)):
                return self.__pluginExit(
                    'OK',
                    "%d critical and %d warning matches found\nMatches found already reseted." % (criticals,warnings),
                    'matches=%d count=%dc' % (count,last)
                )
            else:
                return self.__pluginExit(
                    'CRITICAL',
                    ("%d critical and %d warning matches found\n"+msg) % (criticals,warnings),
                    'matches=%d count=%dc' % (count,last)
                )
        elif(warnings >= self.__options.warning):
            if(self.__options.resetregex  and re.search(self.__options.resetregex,msg)):
                return self.__pluginExit(
                    'OK',
                    "%d critical and %d warning matches found\nMatches found already reseted."% (criticals,warnings),
                    'matches=%d count=%dc' % (count,last)
                )
            else:
                return self.__pluginExit(
                    'WARNING',
                    ('%d critical and %d warning matches found \n,'+msg) % (criticals,warnings),
                    'matches=%d count=%dc' % (count,last)
                )
        else:
            return self.__pluginExit(
                'OK',
                "%d critical and %d warning matches found."%(criticals,warnings),
                "matches=%d count=%dc"%(count,last)
            )

        return self.__pluginExit('UNKNOWN', '0 matches found.\n','Default exit')



    def __buildQuery(self):
        o = self.__options

        filter = self.__checkFilter
        warningCount = self.__getCountField(o.prio_warning,"count_warning");
        criticalCount = self.__getCountField(o.prio_critical,"count_critical");
        queryBase = "SELECT COUNT(id) as count, MAX(id) as last , "+warningCount+", "+criticalCount+" from %s where id > %d "
       
        queryBase = queryBase % (o.db_table,filter.startfrom)
        urlParams = {}
        if(filter.host != ""):
            queryBase += self.__getWherePart("upper(host_name)",filter.host.upper())
            urlParams["host[]"] = filter.host

        if(filter.message != ""):
            queryBase += self.__getWherePart("message",filter.message)
            urlParams["message"] = filter.message

        if(filter.priority != ""):
            queryBase += self.__getWherePart("priority",filter.priority)
            urlParams["priority[]"] = filter.priority

        if(filter.facility != ""):
            queryBase += self.__getWherePart("facility",filter.facility)
            urlParams["facility[]"] = filter.facility

        if(filter.logtype != ""):
            queryBase += self.__getWherePart("type",filter.logtype)
            urlParams["type[]"] = filter.logtype

        if(filter.maxage != ""):
            queryBase += self.__getWherePart("created",filter.maxage, ">=")
            urlParams["created[]"] = filter.maxage

        if(filter.program != ""):
            queryBase += self.__getWherePart("program",filter.program)
            urlParams["program[]"] = filter.program

        if(filter.ipaddress != ""):
            queryBase += self.__getWherePart("host_address",self.__convertIp(filter.ipaddress))
            urlParams["address[]"] = filter.ipaddress

        queryBase += self.__getWherePart("ack",0)
        urlParams["ack"] = "0"
        o.urlParams = urlParams

        return queryBase;
    
    def __convertIp(self,address):
        p = re.compile("\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}")
        if p.search(address) != None :
            address = "::ffff:"+address
        
        address = inet_pton(socket.AF_INET6, address)
        
        if self.__options.db_type == "oracle":
            imedAddress = ""
            for byte in address :
                imedAddress += "%0.2X" % ord(byte)
            print imedAddress
            return imedAddress
        return address


    def __getCountField(self,values, field):
        if(values == ""):
            return " COUNT(id) AS "+field+" "
        caseQuery = "CASE WHEN PRIORITY IN ("+values+") THEN 1 ELSE 0 END"
        return " SUM("+caseQuery+") AS "+field+" "


    def __getWherePart(self,field,value,op = "=",agg = "AND"):
        
        if(isinstance(value,list)):
            op = "IN"
            value = "("+",".join(value)+")"
        else:
            value = str(value)
            if(re.search(r"\*|\%",value) != None):
                op = "LIKE"
                value = re.sub(r"(\*)","%",value)
        tpl = agg+" "+field+" "+op+" "
        if(value.isdigit()):
            value = str(int(value))
        elif op != 'IN':
            value = "'"+value+"' "
        else:
            value = value+" "
        tpl += value

        return " "+tpl+" "


    def __pluginExit(self,status,text,perfdata):
        statusCode = {'UNKNOWN' : 3, 'OK' : 0, 'WARNING' : 1, 'CRITICAL' : 2}
        out = ""
        if(self.__options.url != "" and hasattr(self.__options,"urlParams")):
            params = urllib.urlencode(self.__options.urlParams);
            link = '<a href="%s?%s">%s</a>' % (self.__options.url,params,text)
            out += "%s: %s %s" % (status, self.__options.label, link)
        else:
            out += "%s %s %s" % (status,self.__options.label,text)

        out += '|%s' % perfdata
        out += "\nmessage filter: %s" % self.__options.message
        out += "\nreset regexp: %s" % self.__options.resetregex
        out += chr(10)
        
        if self.__noExit == False:
            print out
            sys.exit(statusCode[status])
        raise CheckStatusException(statusCode[status],str(text),str(perfdata))

        

    def __parseArguments(self):
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
        parser.add_option("-W","--warning-priorities",dest="prio_warning",default="",
                        help="A comma seperated set of priorities which will be used for determine the warning state" ),
        parser.add_option("-C","--critical-priorities",dest="prio_critical",default="",
                        help="A comma seperated set of priorities which will be used for determine the warning state" ),
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
                        help="User for db login",default="eventdb")
        parser.add_option("--dbpassword",dest="db_password",
                        help="Password for db login",default="eventdb")
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
        parser.add_option("--ip",dest="ipaddress", help="Filter by ip address", default="")
        parser.add_option("-w","--warning",dest="warning",type="int",help="Number of matches to result in warning state",default="-1")
        parser.add_option("-c","--critical",dest="critical",type="int",help="Number of matches to result in critical state",default="-1")
        parser.add_option("--cventry",dest="print_cv", default=False,action="store_true",
                        help="returns the custom variable entry for this call (needed in order to use icinga-web cronk integration)")
        parser.add_option("--daemon_pid", dest="daemon_pid", default=False,
                        help="Location of eventdb_daemon.pid which will be used for connection pooling. If no daemon is currently running, the plugin will spawn one.")
        parser.add_option("--daemon_log", dest="daemon_log", default=DEFAULT_LOG)
        parser.add_option("--daemon_behaviour", dest="daemon_behaviour", choices=["Aggressive","Servile"], default="Aggressive",
                        help="Defines how this daemon behaves when another daemon is started at (exactly) the same time."+
                        "(Aggressive[=Default]: Other daemons will be removed"+
                        ",Servile: Abort daemonization when another daemon is encountered)")
        parser.add_option("--daemon_lifetime", dest="daemon_lifetime", default=5,type="int",
                        help="Defines how long daemonized connections life (in seconds) when there's no request")
        (options, args) = parser.parse_args()
        return options;


  


def main():
    EventDBPlugin()

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

