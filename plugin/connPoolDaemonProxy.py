"""
Proxy interface to allow using connPoolDaemon the same way as DBhandler

"""
from connPoolDaemon import ConnPoolDaemon, DAEMON_DEFAULT_LOG, DAEMON_CONCURRENCY_BEHAVIOURS

DEFAULT_LOG = DAEMON_DEFAULT_LOG
# defines the behaviour when (detectable) race conditions occur
CONCURRENCY_BEHAVIOURS = DAEMON_CONCURRENCY_BEHAVIOURS

class ConnPoolDaemonProxy(object):

    def __init__(self,pid,logFile = DAEMON_DEFAULT_LOG, behaviour = "Aggressive", timeout = 5):
        self.__cursor = False
        self.__pid = pid
        self.__logFile = logFile
        self.__behaviour = behaviour
        self.__timeout = timeout
        

    def execute(self,query):
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
        if result == False:
            raise Exception("Requesting daemon failed")
        return result

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
