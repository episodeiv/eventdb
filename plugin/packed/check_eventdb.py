#! /usr/bin/python
# eventdb (https://www.netways.org/projects/eventdb)
# Copyright (C) 2011 NETWAYS GmbH
#
# This program is free software; you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation; either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <http://www.gnu.org/licenses/>.


import struct
import socket


import os.path
import fcntl
import  time, re
import getopt, pprint, sys, re, urllib
import pickle
import select
import tempfile
import sqlalchemy
import time
import socket
import signal
import os.path
import os
import sys
import random
from optparse import OptionParser


import sqlalchemy
from sqlalchemy import create_engine


# defines the behaviour when (detectable) race conditions occur

DEBUG = False
# defines the default log position
DAEMON_DEFAULT_LOG = "/usr/local/icinga/var/eventdb/eventdb_daemon.log"
DEFAULT_LOG = DAEMON_DEFAULT_LOG
# defines the behaviour when (detectable) race conditions occur
DAEMON_CONCURRENCY_BEHAVIOURS = {
    # Remove pid files from other daemons that were created after startup
    "Aggressive" : 0x1,
    # Abort if a pid file is encountered during startup
    "Servile" : 0x2
}
CONCURRENCY_BEHAVIOURS = DAEMON_CONCURRENCY_BEHAVIOURS
"""
Defines which logs to show. Change for debugging
"""
DEBUG_SEVERITIES = {
    "DEBUG" : False,
    "INFO" : False,
    "WARNING" : True,
    "ERROR" : True
}


"""
ConnPoolDaemon

Tries to connect to a daemon running in the background, If none is found, it spawns
one by himself and connects to it (if possible). Allows to use resources (here database
connections) that can't easuky be persisted over different processes.

"""
class ConnPoolDaemon(object):

    def __log(self,text,severity="DEBUG"):
        severity = severity.upper()
        if severity not in DEBUG_SEVERITIES:
            return

        if DEBUG_SEVERITIES[severity] == False:
            return

        self.__logFile = open(self.__logFileTarget,'a')
        self.__logFile.write("(%i)[%s] - [%s] %s \n" % (os.getpid(),time.asctime(), severity, text))
        self.__logFile.close()

    def __closeLog(self):
        if self.__logFile == None:
            return
        self.__logFile.close()
        self.__logFile = None


    def __init__(self,pid,spawn,log = DAEMON_DEFAULT_LOG,behaviour = "Aggressive",timeout = 10):

        self.__timeout = timeout
        self.__logFileTarget = log

        try :
            self.__behaviour = DAEMON_CONCURRENCY_BEHAVIOURS[behaviour]
            self.__pidName = pid
            self.__allocDefaultVars()
            if self.__checkPIDFile() == False and spawn == True:
                self.__spawn()
        except Exception, e:
            self.__log("Couldn't create ConnPoolDaemon : %s " % e, "Error")

    def __allocDefaultVars(self):
        self.__logFile = None
        self.__socket = None
        self.__socketName = None
        self.__pidFile = None
        self.__pidExists = False
        self.__daemonPid = -1
        self.__connectionPool = {}

    """
    Connects to an (already existing) daemon
    Returns a boolean that indicates whether connection succeed
    """
    def connect(self):
        if self.__socketName == None or os.path.exists(self.__socketName) == False:
            return False
        try :
            self.__log("Connecting to daemon")
            self.__socket = socket.socket(socket.AF_UNIX,socket.SOCK_STREAM)
            self.__socket.connect_ex(self.__socketName)
            self.__log("Connection succeeded")

            return True
        except Exception, e:
            self.__log("Connection to daemon failed : %s" % str(e), "Error")
            return False

    def _getSocket(self):
        return self.__socket

    """
    Checks whether a process id file exists and loads its content (process id of
    the daemon and socket location) into the objects attributes
    """
    def __checkPIDFile(self):
        fileopen = False
        fd = None
        try :
            self.__log("Checking for existing pid-file at %s " % self.__pidName)
            pid = self.__pidName
            if pid == -1:
                return False
            if os.path.isfile(pid) == False:
                self.__log("No PID file found")
                return False

            self.__log("PID file found, opening")

            fd = open(pid,"r");
            fileopen = True
            self.__log("Reading process id of already running daemon")
            self.__daemonPid = fd.readline()

            self.__log("Daemon pid is %s" % self.__daemonPid)

            self.__log("Reading socket location of already running daemon")

            self.__socketName = str(fd.readline())
            self.__log("Daemon communication socket location is %s" % self.__socketName)
            fd.close()
            fileopen = False
            #check PID existence
            self.__log("Checking if pid %s is running " % self.__daemonPid)
            os.kill(int(self.__daemonPid), 0)
            self.__pidExists = True

            #PID exists but no socket, remove pid file
            self.__log("Daemon is still running, checking for socket")
            if os.path.exists(self.__socketName) == False:
                self.__log("Socket %s is not existing",self.__socketName)
                
                return False
            return True
        except OSError, o: #PID does not exist,
            if fileopen == True:
                fd.close()
            self.__log("OS Exception occured (looks like pid doesn't exist): %s " % (o))
            return False
        except TypeError, t:
            if fileopen == True:
                fd.close()
            self.__log("TypeError occured: %s" % (t) )
            return False

    """
    Spawns a new daemon which can accept connections from check-plugins
    """
    def __spawn(self):
        
        # important, because file descriptors will be closed on daemonization and
        # the log method must reopen it again on first log entry
        self.__closeLog()
        parentPID = os.getpid()
        try:
            pid = os.fork();
            if pid == 0:
                try:
                    # daemonize
                    self.__createDaemon()
                    
                    lockFile = open(self.__pidName+".lock","w+")
                    fcntl.lockf(lockFile.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)

                    self.__pidFile = open(self.__pidName,"w+")
                    self.__writePIDFile()

                    # from here on, we're in the daemon process
                    self.__log("Daemon spawned")
                    self.__openSocket()
                    self.__pidFile.close()
                    self.__main(parentPID)
                    os.unlink(self.__pidName+".lock")
                except Exception, e:
                    pass
                    #self.__log(e,"Error")

                self.__cleanup()
                

                self.__log("Daemon finished", "info")
            else:
                return False
        except OSError, o:
            return False

    """
    Handler to ignore SIGUSR1 (which is used for notifying the plugin about successful or aborted
    daemon creation)
    """
    def __onDaemonReadySignal(self,sig1,sig):
        return

    def __openSocket(self):
        #".sock","edb_",os.path.dirname(self.__pidName)

        self.__socketName = tempfile.mktemp(".sock","edb_",os.path.dirname(self.__pidName))
        self.__log("Creating socket at %s "% (self.__socketName), "Info")

        try:
            self.__socket = socket.socket(socket.AF_UNIX,socket.SOCK_STREAM)
            self.__socket.bind(self.__socketName)
            os.chmod(self.__socketName, 0600)
            self.__pidFile.write("%s" % self.__socketName)

        except OSError, _os:
            self.__log("Couldn't create socket: "+str(_os),"Error")
            raise

    """
        Writes socket location and process id to the pid file
    """
    """
        Writes socket location and process id to the pid file
    """
    def __writePIDFile(self):

        
        self.__log("Attempting to create PID file at %s" % (self.__pidName))

        pid = os.getpid()
        self.__pidFile.write("%i\n" % pid)
       
        self.__log("Wrote socket and pid to pidfile")
        self.__log("PID file created successfully")


    """
        Detach a process from the controlling terminal and run it in the
        background as a daemon.
        Taken from http://code.activestate.com/recipes/278731/

        Thanks to Chad J. Schroeder, who wrote this code
    """
    def __createDaemon(self,cwd = "/",umask = 0):

       try:
          pid = os.fork()
       except OSError, e:
          raise Exception, "%s [%d]" % (e.strerror, e.errno)

       if (pid == 0):
          os.setsid()
          try:
             pid = os.fork()	# Fork a second child.
          except OSError, e:
             raise Exception, "%s [%d]" % (e.strerror, e.errno)

          if (pid == 0):
             os.chdir(cwd)
             os.umask(umask)
          else:
             os._exit(0)
       else:
          os._exit(0)
       #close file descriptors
       import resource		# Resource usage information.
       maxfd = resource.getrlimit(resource.RLIMIT_NOFILE)[1]
       if (maxfd == resource.RLIM_INFINITY):
          maxfd = 1024

       # Iterate through and close all file descriptors.
       for fd in range(0, maxfd):
          try:
             os.close(fd)
          except OSError:	# ERROR, fd wasn't open to begin with (ignored)
             pass

       os.open("/dev/null", os.O_RDWR)	# standard input (0)
       os.dup2(0, 1)			# standard output (1)
       os.dup2(0, 2)			# standard error (2)

       return(0)

    def __cleanup(self):
        # Remove pid file
        try :
            if self.__pidName != None:
                os.unlink(self.__pidName)
        except Exception:
            pass
        # Remove socket
        try :
            if self.__socketName != None:
                os.unlink(self.__socketName)
        except Exception:
            pass

    # Main method of the daemon process
    def __main(self,parentPID):
        self.__clients = [self.__socket]
        self.__socket.listen(1)


        finished = False
        try :
            while finished == False:
                self.__log("Listening on %i sockets " % len(self.__clients))
                (r,w,x) = select.select(self.__clients,[],[],self.__timeout)

                if len(r) == 0:
                    self.__log("No requests came, shutting down")
                    finished = True
                else:
                    self.__log("Processing request..")
                    for ob in r:
                        if ob == self.__socket:
                            self.__addClient()
                        else :
                            self.__handleRequest(ob)

        except Exception, e:
            self.__log("An error occured in daemons main(): %s" % e,"Error")
        self.__log("Closing socket")
        self.__socket.close()

    def __addClient(self):
        (socket, address) = self.__socket.accept()
        self.__log("New client connected from %s" % address)
        self.__clients.append(socket)

    def __removeClient(self,socket):
        self.__log("Client disconnected")
        self.__clients.remove(socket)

    def __handleRequest(self,socket):

        self.__log("Client request")
        received = socket.recv(4096)
        if len(received) == 0:
            self.__removeClient(socket)
            return

        self.__onRequest(received, socket)

    """
        Interface-method for clients to request an action from the server
    """
    def request(self,reqObj):
        data = pickle.dumps(reqObj)
        self.__socket.send(data)
        (r,w,x) = select.select([self.__socket],[],[],2)
        if len(r) == 0:
            self.__log("Response timeout, aborting", "Warning")
            return False

        result = r[0].recv(4098);
        result = pickle.loads(result)
        self.__log("As an object : %s" % result )

        if result["success"] == True:
            return result["result"]
        elif "exception" in result:
             Exception(result["exception"])
        return False

    """
        Will be executed by the daemon when a request comes in
    """
    def __onRequest(self,data,socket):
        try:

            reqObj = pickle.loads(data)
            self.__log("Got db request %s " %reqObj)
            handler = self.__getConnectionFromPool(reqObj["options"])

            if handler == False:
                socket.send(pickle.dumps({
                    "success" : False,
                    "result" : "DB Connection failed"
                }))
            result = []
            resultProxy = handler.execute(reqObj["query"])


            if resultProxy == False:
                socket.send(pickle.dumps({
                    "success": False,
                    "exception": "Could not connect"
                }))
                return True

            # make it pickleable
            for row in resultProxy:
                entry = []
                for rowEntry in row:
                    entry.append(rowEntry)
                result.append(entry)

            self.__log("Sending result to client %s (nr %i, myNr %i) " % (result,socket.fileno(),self.__socket.fileno()))

            socket.send(pickle.dumps({
                "success" : True,
                "result" : result
            }))
            return False

        except Exception , e:
            self.__log("Exception occured in _onRequest() : %s " % e, "Error")
            socket.send(pickle.dumps({
                "success": False,
                "exception": str(e)
            }))
        return False

    """
        Returns (and if necessary allocates) a database connection in the daemon
        The DSN of the connection is used to check if a connection already exists
    """
    def __getConnectionFromPool(self,reqObj):
        uri = DBHandler.getURLString(
            reqObj["driver"],
            reqObj["user"],
            reqObj["password"],
            reqObj["host"],
            reqObj["port"],
            reqObj["database"]
        )
        self.__log("Requesting connection for %s from pool " % uri)
        if uri in self.__connectionPool:
            self.__log("Pool already contains connection")
            return self.__connectionPool[uri]
        else:
            self.__log("Connection not yet established, creating one", "Info")
            self.__connectionPool[uri] = DBHandler()
            self.__log("Connecting")

            self.__connectionPool[uri].connect(
                driver = reqObj["driver"],
                user = reqObj["user"],
                password = reqObj["password"],
                host = reqObj["host"],
                port = reqObj["port"],
                database = reqObj["database"],
                poolsize = 3
            )
              

            self.__log("Connection created", "Info")
            return self.__connectionPool[uri]


'''
connPoolDaemonProxy.py

'''
class ConnPoolDaemonProxy(object):

    def __init__(self,pid,logFile = DAEMON_DEFAULT_LOG, behaviour = "Aggressive", timeout = 5):
        self.__cursor = False
        self.__pid = pid
        self.__logFile = logFile
        self.__behaviour = behaviour
        self.__timeout = timeout


    def execute(self,query):
        try :
            result = self.__daemon.request(
                {
                    "query" : query,
                    "options" : {
                        "driver" : self.__driver,
                        "host" : self.__host,
                        "port" : self.__port,
                        "database" : self.__database,
                        "user" : self.__user,
                        "password" : self.__password
                    }
                }
            )
            return result
        except:
        #if result == False:
        #    raise Exception("Requesting daemon failed")
            return False

    def connect(self,driver,host="localhost",user=None,password=None,database=None,port=None,poolsize=3):
        self.__daemon = ConnPoolDaemon(
            self.__pid,
            True,
            self.__logFile,
            self.__behaviour,
            self.__timeout
        )
        # Just store those values, they are needed in the execute method
        self.__driver = driver;
        self.__host = host
        self.__port = port
        self.__database = database
        self.__user = user
        self.__password = password


        if(self.__daemon.connect() == False):
            raise Exception("Couldn't connect to daemon")


'''
dbhandler.py

'''
class DatabaseException(Exception):
    def __init__(self,m):
        self.message = m

class DBHandler(object):

    def __init__(self):
        self.__cursor = False
        self.__connection = False

    def connect(self,driver,host="localhost",user=None,password=None,database=None,port=None,poolsize=0):
        self.__driver = driver;
        self.__host = host
        self.__port = port
        self.__database = database
        self.__user = user
        self.__password = password
        self.__poolsize = poolsize
        self.__connectWithEngine()

        if(self.__cursor == None):
            raise DatabaseException("Couldn't connect to db")
        return self.__connection

    @staticmethod
    def getURLString(self,driver,host="localhost",user=None,password=None,database=None,port=None):
        #Some versions of sqlalchemy didn't work, so we'll do it by hand
        url = str(driver)+"://"
        if(user != None):
            url += str(user)
        if(password != None):
            url += ":"+str(password)
        url += "@"+str(host)

        if port != None:
            url += ":"+str(port)
        if database != None:
            url += "/"+str(database)
        return url
    '''
        url = sqlalchemy.engine.url.URL(
            driver,
            user,
            password,
            host,
            port,
            database
        );
        return sqlalchemy.engine.url.make_url(url)'''

    def __connectWithEngine(self):
        url =  sqlalchemy.engine.url.URL(
            self.__driver,
            self.__user,
            self.__password,
            self.__host,
            self.__port,
            self.__database
        );
        #use pooling if an appropriate poolsize is given
        if self.__poolsize <= 1:
            self.__engine = create_engine(url)
        else:
            self.__engine = create_engine(url,pool_size = self.__poolsize)

        self.__connection = self.__engine.connect()
        if self.__connection:
            self.__executeDriverQuirks()

    def __executeDriverQuirks(self):
        if self.__driver == "oracle":
            self.__connection.execute('ALTER SESSION SET NLS_DATE_FORMAT = "YYYY-MM-DD HH24:MI:SS"')

    def execute(self,query):
        if(DEBUG):
            print query
        if self.__connection == False:
            return False

        return self.__connection.execute(query)


    def __del__(self):
        if(hasattr(self,"__connection") and self.__connection):
            self.__connection.close()


class CheckFilter(object):

    def __init__(self):
        self.logtype = 0
        self.facility = ""
        self.priority = ""
        self.startfrom = 0
        self.maxage = ""
        self.message = ""
        self.startTimestamp = None
        self.program = ""
        self.ipaddress = ""
        self.host = ""

    def setLogtype(self,type):
        typemap = {
            "syslog" : 0,
            "snmptrap" : 1,
            "mail" : 2
        }
        if not type in typemap :
            raise Exception("Invalid type provided for log-source")
        self.logtype = typemap[type]

    def setIpAddress(self,address):
        self.ipaddress = address

    def setFacility(self,facility):
        if facility == "":
            return
        if isinstance(facility, list) :
            self.facility = list
        else :
            self.facility = facility.split(",")

    def setPriority(self, priority):
        if priority == "":
            return
        if isinstance(priority, list) :
            self.priority = list
        else :
            self.priority = priority.split(",")

    def setStartfrom(self,value):
        if not isinstance(value, int):
            raise Exception("Invalid type for startfrom, number expected")
        if value < 0 :
            raise Exception("Invalid startfrom value, must be a positive integer")

        self.startfrom = value


    '''
    Sets the maxage by converting the relative maxage format like 4d, 2m, 20h to
    an absolute database timestamp (YYYY-MM-DD HH:MI:SS)
    '''
    def setMaxage(self,maxage):
        if maxage == "":
            return
        curTime = time.time()
        matches = re.match(r"(\d*?)(d|h|m)",maxage)
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

        self.startTimestamp = curTime
        tmLocal = time.localtime(curTime)
        self.maxage = "%02d-%02d-%02d %02d:%02d:%02d" % tmLocal[0:6]

    def setPerfdata(self,perfdata):
        if perfdata == None:
            return
        idxMatch = re.findall(r'count=(\d+)',perfdata);
        if(len(idxMatch) > 0):
            self.startfrom = int(idxMatch[0])


'''
check_eventb.py

'''
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

        try:
           pass
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

                if cursor == False:
                    raise DatabaseException("Query failed")

                for row in cursor:
                    if(len(row) != 4):
                        raise DatabaseException("SQL Query failed, returned wrong values")
                    values = [row[0],row[1],row[2],row[3]]

                if(values[1] == None):
                    values[1] = 0

                cursor = db.execute("SELECT message FROM %s WHERE id = %d" % (self.__options.db_table, values[1]))

                if cursor == False:
                    raise DatabaseException("Query failed")

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

        address = socket.inet_pton(socket.AF_INET6, address)

        if self.__options.db_type == "oracle":
            imedAddress = ""
            for byte in address :
                imedAddress += "%0.2X" % ord(byte)
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
                value = re.sub(r"(\%)","%%",value)
                value = re.sub(r"(\*)","%%",value)

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
            try:
                text = text.replace('|', '')
                text = text.replace('\n', ' ')
            except Exception:
                pass
            out += "%s %s %s" % (status,self.__options.label,text)

        out += '|%s' % perfdata
        out += "\nmessage filter: %s" % self.__options.message
        out += "\nreset regexp: %s" % self.__options.resetregex

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
        parser.add_option("-I", "--ip",dest="ipaddress", help="Filter by ip address", default="")
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

