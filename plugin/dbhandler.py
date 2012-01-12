DEBUG = False
import sqlalchemy
from sqlalchemy import create_engine
class DatabaseException(Exception):
    def __init__(self,m):
        self.message = m
'''
'   User defined type that hides database-implementation
'   from executing code
'''
class DBHandler:

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
        self.__executeDriverQuirks()

    def __executeDriverQuirks(self):
        if self.__driver == "oracle":
            self.__connection.execute('ALTER SESSION SET NLS_DATE_FORMAT = "YYYY-MM-DD HH24:MI:SS"')

    def execute(self,query):
        if(DEBUG):
            print query
        return self.__connection.execute(query)


    def __del__(self):
        if(hasattr(self,"__connection") and self.__connection):
            self.__connection.close()

