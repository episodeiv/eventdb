'''
Class that encapsulates the filters that affect the database check
'''
import  time, re
class CheckFilter():

    def __init__(self):
        self.logtype = 0
        self.facility = ""
        self.priority = ""
        self.startfrom = 0
        self.maxage = ""
        self.message = ""
        self.startTimestamp = None
        self.program = ""
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
