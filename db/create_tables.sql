-- SQL table creation file
-- for NETWAYS eventdb

-- Comments
CREATE TABLE `comments` (
  `uid` int(11) NOT NULL auto_increment,
  `event_fk` int(11) NOT NULL default '0',
  `author` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `type` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `comment` blob NOT NULL,
  `crdate` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`uid`),
  KEY `EVENT_FK` (`event_fk`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='InnoDB free: 3993600 kB';

-- Events
CREATE TABLE `events` (
  `uid` int(11) NOT NULL auto_increment,
  `type` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `host` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `facility` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `priority` varchar(20) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `level` varchar(10) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `tag` varchar(10) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `program` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `message` blob NOT NULL,
  `acknowledged` tinyint(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`uid`),
  KEY `TYPE` (`type`),
  KEY `HOST` (`host`),
  KEY `FACILITY` (`facility`),
  KEY `PRIORITY` (`priority`),
  KEY `MESSAGE` (`message`(512))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='InnoDB free: 3998720 kB; InnoDB free: 3995648 kB';

-- Constraints
ALTER TABLE `comments`
  ADD CONSTRAINT `EVENT_FK` FOREIGN KEY (`event_fk`) REFERENCES `events` (`uid`);
