
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
from dbhandler import *

# defines the default log position
DAEMON_DEFAULT_LOG = "/usr/local/icinga/var/eventdb/eventdb_daemon.log"
# defines the behaviour when (detectable) race conditions occur
DAEMON_CONCURRENCY_BEHAVIOURS = {
    # Remove pid files from other daemons that were created after startup
    "Aggressive" : 0x1,
    # Abort if a pid file is encountered during startup
    "Servile" : 0x2
}

"""
Defines which logs to show. Change for debugging
"""
DEBUG_SEVERITIES = {
    "DEBUG" : False,
    "INFO" : True,
    "WARNING" : True,
    "ERROR" : True
}

__all__ = ['ConnPoolDaemon','DAEMON_CONCURRENCY_BEHAVIOURS','DAEMON_DEFAULT_LOG']
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
        if self.__socketName == False or os.path.exists(self.__socketName) == False:
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
        try :
            self.__log("Checking for existing pid-file at %s " % self.__pidName)
            pid = self.__pidName
            if pid == -1:
                return False
            if os.path.isfile(pid) == False:
                self.__log("No PID file found")
                return False

            self.__log("PID file found, opening")
            fd = open(pid);

            self.__log("Reading process id of already running daemon")
            self.__daemonPid = int(fd.readline())
            self.__log("Daemon pid is %i" % self.__daemonPid)

            self.__log("Reading socket location of already running daemon")

            self.__socketName = str(fd.readline())
            self.__log("Daemon communication socket location is %s" % self.__socketName)

            #check PID existence
            self.__log("Checking if pid %i is running " % self.__daemonPid)
            os.kill(self.__daemonPid, 0)
            self.__pidExists = True

            #PID exists but no socket, remove pid file
            self.__log("Daemon is still running, checking for socket")
            if os.path.exists(self.__socketName) == False:
                self.__log("Socket is not existing")
                os.unlink(pid)
                return False
            return True
        except OSError, o: #PID does not exist,
            self.__log("OS Exception occured (looks like pid doesn't exist): %s " % (o))
            return False
        except TypeError, t:
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

                    # from here on, we're in the daemon process
                    self.__log("Daemon spawned")
                    self.__openSocket()
                    self.__writePIDFile()
                    self.__main(parentPID)

                except Exception, e:
                    try :
                        # Notify the parent process (the check plugin) that daemonization is finished (or failed)
                        os.kill(parentPID,signal.SIGUSR1)
                    except Exception, e:
                        pass
                    self.__log(e,"Error")
                    
                self.__cleanup()
                self.__log("Daemon finished", "info")
            else:
                # Wait until the daemon is ready
                signal.signal(signal.SIGUSR1, self.__onDaemonReadySignal)
                signal.pause()
                return self.__checkPIDFile()
        except OSError, o:
            return False

    """
    Handler to ignore SIGUSR1 (which is used for notifying the plugin about successful or aborted
    daemon creation)
    """
    def __onDaemonReadySignal(self,sig1,sig):
        return

    def __openSocket(self):

        self.__socketName = tempfile.mktemp(".sock","edb_")
        self.__log("Creating socket at %s "% (self.__socketName), "Info")

        try:
            self.__socket = socket.socket(socket.AF_UNIX,socket.SOCK_STREAM)
            self.__socket.bind(self.__socketName)
            os.chmod(self.__socketName, 0600)
        except OSError, _os:
            self.__log("Couldn't create socket: "+str(_os),"Error")
            raise

    """
        Writes socket location and process id to the pid file
    """
    def __writePIDFile(self):
        pid = os.getpid()
        self.__log("Attempting to create PID file at %s" % (self.__pidName))
        try:
            tmpFileName = tempfile.mktemp();
            self.__log("Temporary file created at %s " % tmpFileName)

            pidFile = open(tmpFileName,"w+")
            pidFile.write("%i\n" % pid)
            pidFile.write("%s" % self.__socketName)
            pidFile.close()
            self.__log("Wrote socket and pid to temporary file")

            # Check if another daemon was created during startup
            if os.path.isfile(self.__pidName) == True:
                if self.__behaviour == DAEMON_CONCURRENCY_BEHAVIOURS["Aggressive"]:
                    self.__log("Encountered other daemon, removing foreign PID file (aggressive behaviour)","Warning")
                    os.unlink(self.__pidName)
                else :
                    self.__log("Encountered other daemon, aborting (servile behaviour)","Error")
                    os.unlink(tmpFileName)
                    return False

            self.__log("Moving temporary PID file %s to PID location %s " % (tmpFileName, self.__pidName))
            os.rename(tmpFileName,self.__pidName)
        except Exception, e :            
            os.unlink(tmpFileName)
            raise e
        
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

        try:
            # Notify the parent process (the check plugin) that daemonization is finished
            os.kill(parentPID,signal.SIGUSR1)
        except Exception, e:
            # Don't care if the process isn't alive anymore
            pass

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
            raise Exception(result["exception"])
        return False

    """
        Will be executed by the daemon when a request comes in
    """
    def __onRequest(self,data,socket):

        try:
            reqObj = pickle.loads(data)
            self.__log("Got db request %s " %reqObj)
            handler = self.__getConnectionFromPool(reqObj["options"])
            result = []
            resultProxy = handler.execute(reqObj["query"])
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

        except Exception , e:
            self.__log("Exception occured in _onRequest() : %s " % e, "Error")
            socket.send(pickle.dumps({
                "success": False,
                "exception": str(e)
            }))
        return

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
