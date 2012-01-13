import pickle
import select
import tempfile
import time
import socket
import signal
import os.path
import os
import sys

DAEMON_DEFAULT_LOG = "/usr/local/icinga/var/eventdb/eventdb_daemon.log"
# defines the behaviour when (detectable) race conditions occur
DAEMON_CONCURRENCY_BEHAVIOURS = {
    # Remove pid files from other daemons that were created after startup
    "Aggressive" : 0x1,
    # Abort if a pid file is encountered during startup
    "Servile" : 0x2
}



class Daemon(object):

    def _log(self,text,severity="DEBUG"):
       
        self.__logFile = open(self.__logFileTarget,'a')
        self.__logFile.write("(%i)[%s] - [%s] %s \n" % (os.getpid(),time.asctime(), severity, text))
        self.__logFile.close()

    def __closeLog(self):
        if self.__logFile == None:
            return
        self.__logFile.close()
        self.__logFile = None


    def __init__(self,pid,spawn,log = DAEMON_DEFAULT_LOG,behaviour = "Aggressive",timeout = 1000):
        self.__timeout = timeout
        self.__logFileTarget = log
        self.__behaviour = DAEMON_CONCURRENCY_BEHAVIOURS[behaviour]
        self.__pidName = pid
        self.__allocDefaultVars()
        if self.__checkPIDFile() == False and spawn == True:
            self.__spawn()

    def __allocDefaultVars(self):
        self.__logFile = None
        self.__socket = None
        self.__socketName = None
        self.__pidFile = None
        self.__pidExists = False
        self.__daemonPid = -1

    def connect(self):
        if self.__socketName == False or os.path.exists(self.__socketName) == False:
            return False
        try :
            self._log("Connecting to daemon")
            self.__socket = socket.socket(socket.AF_UNIX,socket.SOCK_STREAM)
            self.__socket.connect_ex(self.__socketName)
            self._log("Connection succeeded")
            
            return True
        except Exception, e:
            self._log("Connection to daemon failed : %s" % str(e))
            return False

    def _getSocket(self):
        return self.__socket

    def request(self,object):
        return false
        

    def __checkPIDFile(self):
        try :
            self._log("Checking for existing pid-file at %s " % self.__pidName)
            pid = self.__pidName
            if pid == -1:
                return False
            if os.path.isfile(pid) == False:
                self._log("No PID file found")
                return False

            self._log("PID file found, opening")
            fd = open(pid);

            self._log("Reading process id of already running daemon")
            self.__daemonPid = int(fd.readline())
            self._log("Daemon pid is %i" % self.__daemonPid)

            self._log("Reading socket location of already running daemon")

            self.__socketName = str(fd.readline())
            self._log("Daemon communication socket location is %s" % self.__socketName)

            #check PID existence
            self._log("Checking if pid %i is running " % self.__daemonPid)
            os.kill(self.__daemonPid, 0)
            self.__pidExists = True

            #PID exists but no socket, remove pid file
            self._log("Daemon is still running, checking for socket")
            if os.path.exists(self.__socketName) == False:
                self._log("Socket is not existing")
                os.unlink(pid)
                return False
            return True
        except OSError, o: #PID does not exist,
            self._log("OS Exception occured (looks like pid doesn't exist): %s " % (o))
            return False
        except TypeError, t:
            self._log("TypeError occured: %s" % (t) )
            return False

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
                    self._log("Daemon spawned")
                    self.__openSocket()
                    self.__writePIDFile()
                    self.__main(parentPID)

                except Exception, e:
                    try :
                        # Notify the parent process (the check plugin) that daemonization is finished (or failed)
                        os.kill(parentPID,signal.SIGUSR1)
                    except Exception, e:
                        pass
                    self._log(e,"Error")
                    
                self.__cleanup()
                self._log("Daemon finished")
            else:
                # Wait until the daemon is ready
                signal.signal(signal.SIGUSR1, self.__onDaemonReadySignal)
                signal.pause()
                return self.__checkPIDFile()
        except OSError, o:
            return False

    """
        Handler to ignore SIGUSR1
    """
    def __onDaemonReadySignal(self,sig1,sig):
        return

    def __openSocket(self):

        self.__socketName = tempfile.mktemp(".sock","edb_")
        self._log("Creating socket at %s "% (self.__socketName))

        try:
            self.__socket = socket.socket(socket.AF_UNIX,socket.SOCK_STREAM)
            self.__socket.bind(self.__socketName)
            os.chmod(self.__socketName, 0600)
        except OSError, _os:
            self._log("Couldn't create socket: "+str(_os),"Error")
            raise

    """
        Writes socket location and process id to the pid file
    """
    def __writePIDFile(self):
        pid = os.getpid()
        self._log("Attempting to create PID file at %s" % (self.__pidName))
        try:
            tmpFileName = tempfile.mktemp();
            self._log("Temporary file created at %s " % tmpFileName)

            pidFile = open(tmpFileName,"w+")
            pidFile.write("%i\n" % pid)
            pidFile.write("%s" % self.__socketName)
            pidFile.close()
            self._log("Wrote socket and pid to temporary file")

            # Check if another daemon was created during startup
            if os.path.isfile(self.__pidName) == True:
                if self.__behaviour == DAEMON_CONCURRENCY_BEHAVIOURS["Aggressive"]:
                    self._log("Encountered other daemon, removing foreign PID file (aggressive behaviour)","Warning")
                    os.unlink(self.__pidName)
                else :
                    self._log("Encountered other daemon, aborting (servile behaviour)","Error")
                    os.unlink(tmpFileName)
                    return False

            self._log("Moving temporary PID file %s to PID location %s " % (tmpFileName, self.__pidName))
            os.rename(tmpFileName,self.__pidName)
        except Exception, e :            
            os.unlink(tmpFileName)
            raise e
        
        self._log("PID file created successfully")


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
                self._log("Listening on %i sockets " % len(self.__clients))
                (r,w,x) = select.select(self.__clients,[],[],self.__timeout)

                if len(r) == 0:
                    self._log("No requests came, shutting down")
                    finished = True
                else:
                    self._log("Processing request..")
                    for ob in r:
                        if ob == self.__socket:
                            self.__addClient()
                        else :
                            self.__handleRequest(ob)
            
        except Exception, e:
            self._log("An error occured in daemons main(): %s" % e,"Error")
        self._log("Closing socket")
        self.__socket.close()

    def __addClient(self):
        (socket, address) = self.__socket.accept()
        self._log("New client connected from %s" % address)
        self.__clients.append(socket)

    def __removeClient(self,socket):
        self._log("Client disconnected")
        self.__clients.remove(socket)

    def __handleRequest(self,socket):

        self._log("Client request")
        received = socket.recv(4096)
        if len(received) == 0:
            self.__removeClient(socket)
            return
        self.__onRequest(received, socket)

    def __onRequest(self,data,socket):
        return


    