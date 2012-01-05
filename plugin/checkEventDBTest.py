# To change this template, choose Tools | Templates
# and open the template in the editor.

from check_eventdb import CheckStatusException
from check_eventdb import EventDBPlugin
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
            self.assertEqual(e.msg, "warning or critical parameter missing", "Wrong message thrown : "+e.msg)

    def test_missingWarningValue(self):
        tpl = argTpl()
        tpl.warning = -1
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            self.assertEqual(e.status,3, "Wrong status returned")
            self.assertEqual(e.msg, "warning or critical parameter missing", "Wrong message thrown : "+e.msg)


    def test_criticalCheck(self):
        tpl = argTpl()
        tpl.warning = 1
        tpl.critical = 3

        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            self.assertEqual(e.status,2, "Wrong status (%s) returned (Message %s)" %(e.status,e.msg))
            matches = re.match(r"matches=(\d+) ",e.msg)
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
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            self.assertEqual(e.status,2, "Wrong status (%s) returned at uppermost critical threshold (Message %s)" %(e.status,e.msg))

    def test_lowestNonCriticaltThreshold(self):
        # Test border-values
        tpl = argTpl()
        tpl.warning = 1
        tpl.critical = 8163
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            self.assertNotEqual(e.status,2, "Wrong status (%s) returned at uppermost critical threshold (Message %s)" %(e.status,e.msg))


    def test_warningCheck(self):
        tpl = argTpl()
        tpl.warning = 1
        tpl.critical = 99999

        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            self.assertEqual(e.status,1, "Wrong status (%s) returned (Message %s)" %(e.status,e.msg))
            matches = re.match(r"matches=(\d+) ",e.msg)
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
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            self.assertEqual(e.status,1, "Wrong status (%s) returned at uppermost critical threshold (Message %s)" %(e.status,e.msg))

    def test_lowestNonWarningThreshold(self):
        # Test border-values
        tpl = argTpl()
        tpl.warning = 8163
        tpl.critical = 816300
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            self.assertNotEqual(e.status,1, "Wrong status (%s) returned at uppermost critical threshold (Message %s)" %(e.status,e.msg))

    def test_messageFilter(self):
          # Test border-values
        tpl = argTpl()
        tpl.message = 'Random %'
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            matches = re.match(r"matches=(\d+) ",e.msg)
            matchGroups = matches.groups()
            self.assertEqual(len(matchGroups),1, "No valid return message given (total count is missing)")
            self.assertEqual(int(matchGroups[0]),2687,"Wrong result count returned (%s)" % matchGroups[0])

    def test_logtypeFilter(self):
          # Test border-values
        tpl = argTpl()
        tpl.logtype = 'snmptrap'
        try :
            EventDBPlugin(tpl,asDaemon=True,noExit=True)
            self.fail("Missing warning value wasn't detected")
        except CheckStatusException, e:
            matches = re.match(r"matches=(\d+) ",e.msg)
            matchGroups = matches.groups()
            self.assertEqual(len(matchGroups),1, "No valid return message given (total count is missing)")
            self.assertEqual(int(matchGroups[0]),8215,"Wrong result count returned (%s)" % matchGroups[0])


if __name__ == '__main__':
    unittest.main()

