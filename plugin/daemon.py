from tempfile import TemporaryFile
import tempfile
import time

import os.path
import os
import sys

DAEMON_DEFAULT_LOG = "/usr/local/icinga/var/eventdb/eventdb_daemon.log"

class Daemon:

    def __log(self,text,severity="DEBUG"):
        if self.__logFile == None:
            self.__logFile = open(self.__logFileTarget,'a')
        self.__logFile.write("%s: [%s] %s \n" % (time.asctime(), severity, text))


    def __init__(self,pid,spawn,log = DAEMON_DEFAULT_LOG):

        self.__logFileTarget = log
        self.__logFile = None
        self.__processPipe = None
        self.__pidExists = False
        if self.__checkPID(pid) == False and spawn == True:
            self.__spawn(pid)

    def __checkPID(self,pid):
        try :
            if pid == -1:
                return False
            if os.path.isfile(pid) == False:
                return False
            fd = open(pid);
            self.__processPID = int(fd.readLine())
            self.__processPipe = str(fd.readLine())

            #check PID existence
            kill(self.__processPID, 0)
            self.__pidExists = True

            #PID exists but no pipe, remove pid file
            if os.path.isfile(self.__processPipe) == False:
                os.unlink(pid)
                return False
            return True
        except OSError, o: #PID does not exist,
            return False
        except TypeError, t:
            return False

    def __spawn(self,pid):

        try:
            pid = os.fork();
            if pid == 0:
                try:
                    self.__createDaemon()
                    self.__log("Daemon created")
                    self.__openPipe()
                    self.__writePID()

                except Exception, e:
                    self.__log(e,"Error")
                    self.__cleanup()
                    
                self.__log("Daemon finished")
            else:
                return pid
        except OSError, o:
            return False

    def __openPipe(self):

        self.__processPipe = tempfile.mktemp("_pipe","edb_")
        self.__log("Creating Pipe at %s "% (self.__processPipe))
        try:
            os.mkfifo(self.__processPipe,0600)
        except OSError, _os:
            self.__log("Couldn't create pipe: "+_os,"Error")
            self.__cleanup()
            raise
       
        
    def __writePID(self,pid):
        os.getpid()

    def __createDaemon(self,cwd = "/",umask = 0):
       """
            Detach a process from the controlling terminal and run it in the
            background as a daemon.
            Taken from http://code.activestate.com/recipes/278731/

            Thanks to Chad J. Schroeder, who wrote this code
       """

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
        #if hasattr(self, "__pipe") and self.__pipe:
        #    os.close(self.__pipe);
        if self.____processPipe != None:
            os.unlink(self.__processPipe)

    