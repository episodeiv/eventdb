# To change this template, choose Tools | Templates
# and open the template in the editor.

from check_eventdb import CheckStatusException
from check_eventdb import EventDBPlugin
import re
import unittest
import check_eventdb


DBSETTINGS = {
    "user": 'eventdb_test',
    "password": 'test',
    "table": 'event',
    "use": 'oracle',
    "oracleSQL": {
        "setup":      'oracle/setup.sql',
        "teardown":   'oracle/teardown.sql'
    },
    "mysqlSQL": {
        "setup":      'mysql/setup.sql',
        "teardown":   'mysql/teardown.sql'
    }
}

class argTpl:
    def __init__(self):
         self.facility = ''
         self.db_password = 'test'
         self.perfdata = ''
         self.warning = 5
         self.message = ''
         self.maxage = ''
         self.label = ''
         self.priority = ''
         self.program = ''
         self.prio_warning = ''
         self.db_port = None
         self.resetregex = ''
         self.host = ''
         self.db_name = 'XE'
         self.db_table = 'event'
         self.prio_critical = ''
         self.url = ''
         self.db_type = 'oracle'
         self.print_cv = False
         self.logtype = 'syslog'
         self.db_user = 'eventdb_test'
         self.db_host = 'localhost'
         self.daemon_pid = False
         self.critical = 10


class  CheckEventDBTestCase(unittest.TestCase):

        
    #def tearDown(self):
    #    self.foo.dispose()
    #    self.foo = None
    def test_missingCriticalValue(self):
        tpl = argTpl()
        tpl.critical = -1
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing critical value wasn't detected")
        except CheckStatusException, e:
            self.assertEqual(e.status,3, "Wrong status returned")
            self.assertEqual(e.perfdata, "warning or critical parameter missing", "Wrong message thrown : "+e.perfdata)

    def test_missingWarningValue(self):
        tpl = argTpl()
        tpl.warning = -1
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            self.assertEqual(e.status,3, "Wrong status returned")
            self.assertEqual(e.perfdata, "warning or critical parameter missing", "Wrong message thrown : "+e.perfdata)


    def test_criticalCheck(self):
        tpl = argTpl()
        tpl.warning = 1
        tpl.critical = 3

        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("No regular plugin exit")
        except CheckStatusException, e:
            self.assertEqual(e.status,2, "Wrong status (%s) returned (Message %s)" %(e.status,e.perfdata))
            matches = re.match(r"matches=(\d+) ",e.perfdata)
            matchGroups = matches.groups()
            self.assertEqual(len(matchGroups),1, "No valid return message given (total count is missing)")
            self.assertEqual(int(matchGroups[0]),8162,"Wrong result count returned (%s)" % matchGroups[0])

    def test_highestCriticalThreshold(self):
        # Test border-values
        tpl = argTpl()
        tpl.warning = 1
        tpl.critical = 8162
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("No regular plugin exit")
        except CheckStatusException, e:
            self.assertEqual(e.status,2, "Wrong status (%s) returned at uppermost critical threshold (Message %s)" %(e.status,e.perfdata))

    def test_lowestNonCriticaltThreshold(self):
        # Test border-values
        tpl = argTpl()
        tpl.warning = 1
        tpl.critical = 8163
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("No regular plugin exit")
        except CheckStatusException, e:
            self.assertNotEqual(e.status,2, "Wrong status (%s) returned at uppermost critical threshold (Message %s)" %(e.status,e.perfdata))


    def test_warningCheck(self):
        tpl = argTpl()
        tpl.warning = 1
        tpl.critical = 99999

        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("No regular plugin exit")
        except CheckStatusException, e:
            self.assertEqual(e.status,1, "Wrong status (%s) returned (Message %s)" %(e.status,e.perfdata))
            matches = re.match(r"matches=(\d+) ",e.perfdata)
            matchGroups = matches.groups()
            self.assertEqual(len(matchGroups),1, "No valid return message given (total count is missing)")
            self.assertEqual(int(matchGroups[0]),8162,"Wrong result count returned (%s)" % matchGroups[0])

    def test_highestWarningThreshold(self):
        # Test border-values
        tpl = argTpl()
        tpl.warning = 8162
        tpl.critical = 81600
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("No regular plugin exit")
        except CheckStatusException, e:
            self.assertEqual(e.status,1, "Wrong status (%s) returned at uppermost critical threshold (Message %s)" %(e.status,e.perfdata))

    def test_lowestNonWarningThreshold(self):
        # Test border-values
        tpl = argTpl()
        tpl.warning = 8163
        tpl.critical = 816300
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("No regular plugin exit")
        except CheckStatusException, e:
            self.assertNotEqual(e.status,1, "Wrong status (%s) returned at uppermost critical threshold (Message %s)" %(e.status,e.perfdata))

    def test_messageFilter(self):
          # Test border-values
        tpl = argTpl()
        tpl.message = 'Random %'
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("No regular plugin exit")
        except CheckStatusException, e:
            matches = re.match(r"matches=(\d+) ",e.perfdata)
            matchGroups = matches.groups()
            self.assertEqual(len(matchGroups),1, "No valid return message given (total count is missing)")
            self.assertEqual(int(matchGroups[0]),2687,"Wrong result count returned (%s)" % matchGroups[0])

    def test_logtypeFilter(self):
          # Test border-values
        tpl = argTpl()
        tpl.logtype = 'snmptrap'
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("No regular plugin exit")
        except CheckStatusException, e:
            matches = re.match(r"matches=(\d+) ",e.perfdata)
            matchGroups = matches.groups()
            self.assertEqual(len(matchGroups),1, "No valid return message given (total count is missing)")
            self.assertEqual(int(matchGroups[0]),8215,"Wrong result count returned (%s)" % matchGroups[0])

    def test_priorityThresholdWarning(self):
        tpl = argTpl()
        tpl.prio_critical = "1"
        tpl.message = "Random %"
        tpl.prio_warning = "3,4,5,6,7,8"
        tpl.warning = 1000
        tpl.critical = 1000
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail('No regular plugin exit')
        except CheckStatusException, e:
            matches = re.match(r"(\d+) critical and (\d+) warning",e.output)
            matchGroups = matches.groups()

            self.assertEquals(e.status, 1, "Wrong status returned: Expected warning")
            self.assertEqual(len(matchGroups),2, "No valid return message given (total count is < 2)")
            self.assertEqual(int(matchGroups[0]),359,"Wrong result crtitcal count returned (%s)" % matchGroups[0])
            self.assertEqual(int(matchGroups[1]),1694,"Wrong result warning count returned (%s)" % matchGroups[1])

    def test_priorityThresholdCritical(self):
        tpl = argTpl()
        tpl.prio_warning = "1"
        tpl.message = "Random %"
        tpl.prio_critical = "3,4,5,6,7,8"
        tpl.warning = 1000
        tpl.critical = 1000
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail('No regular plugin exit')
        except CheckStatusException, e:
            matches = re.match(r"(\d+) critical and (\d+) warning",e.output)
            matchGroups = matches.groups()
            self.assertEquals(e.status, 2, "Wrong status returned: Expected critical")
            self.assertEqual(len(matchGroups),2, "No valid return message given (total count is < 2)")
            self.assertEqual(int(matchGroups[1]),359,"Wrong warning result count returned (%s)" % matchGroups[1])
            self.assertEqual(int(matchGroups[0]),1694,"Wrong crtitcal result count returned (%s)" % matchGroups[0])

    def test_resetRegexp(self):
        tpl = argTpl()
        tpl.message = "Random %"
        tpl.warning = 1000
        tpl.critical = 2000
        tpl.resetregex = "Random test Event 17\d+"
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail('No regular plugin exit')
        except CheckStatusException, e:
            self.assertEquals(e.status, 0,"Reset regexp didn't catch message")
        tpl.resetregex = "Random test Event 11\d+"
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail('No regular plugin exit')
        except CheckStatusException, e:
            self.assertEquals(e.status, 2,"Reset regexp catched non-matching message")

if __name__ == '__main__':
    unittest.main()

