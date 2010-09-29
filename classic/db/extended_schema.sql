-- Extended schema for use with
-- other software (e.g. net_eventd)
-- ------------------------------------
-- This schema provides a more simple
-- way to work with events through mysql

-- MySQL dump 10.11
--
-- Host: localhost    Database: eventdb
-- ------------------------------------------------------
-- Server version	5.0.32-Debian_7etch5-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `uid` int(11) NOT NULL auto_increment,
  `event_fk` int(11) NOT NULL default '0',
  `type` varchar(50) NOT NULL default '',
  `author` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `comment` blob NOT NULL,
  `crdate` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`uid`),
  KEY `EVENT_FK` (`event_fk`),
  CONSTRAINT `EVENT_FK` FOREIGN KEY (`event_fk`) REFERENCES `events` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='InnoDB free: 3993600 kB; InnoDB free: 3979264 kB; (`event_fk';

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `uid` int(11) NOT NULL auto_increment,
  `type` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `host` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `facility` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `priority` varchar(10) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `level` varchar(10) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `tag` varchar(10) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `program` varchar(50) character set latin1 collate latin1_german2_ci NOT NULL default '',
  `datetime` datetime NOT NULL default '0000-00-00 00:00:00',
  `message` blob NOT NULL,
  `acknowledged` tinyint(1) unsigned NOT NULL default '0',
  `disabled` tinyint(1) unsigned NOT NULL default '0',
  `aggregate_count` int(11) NOT NULL default '0',
  PRIMARY KEY  (`uid`),
  KEY `i_filter_main` (`type`,`facility`,`host`,`priority`,`level`,`program`,`datetime`,`message`(767))
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COMMENT='InnoDB free: 3998720 kB; InnoDB free: 3995648 kB; InnoDB fre';

--
-- Temporary table structure for view `v_events_ack`
--

DROP TABLE IF EXISTS `v_events_ack`;
/*!50001 DROP VIEW IF EXISTS `v_events_ack`*/;
/*!50001 CREATE TABLE `v_events_ack` (
  `uid` int(11),
  `type` varchar(50),
  `host` varchar(50),
  `facility` varchar(50),
  `priority` varchar(10),
  `level` varchar(10),
  `tag` varchar(10),
  `program` varchar(50),
  `datetime` datetime,
  `message` blob,
  `acknowledged` tinyint(1) unsigned
) */;

--
-- Temporary table structure for view `v_events_all`
--

DROP TABLE IF EXISTS `v_events_all`;
/*!50001 DROP VIEW IF EXISTS `v_events_all`*/;
/*!50001 CREATE TABLE `v_events_all` (
  `uid` int(11),
  `type` varchar(50),
  `host` varchar(50),
  `facility` varchar(50),
  `priority` varchar(10),
  `level` varchar(10),
  `tag` varchar(10),
  `program` varchar(50),
  `datetime` datetime,
  `message` blob,
  `acknowledged` tinyint(1) unsigned
) */;

--
-- Temporary table structure for view `v_events_noack`
--

DROP TABLE IF EXISTS `v_events_noack`;
/*!50001 DROP VIEW IF EXISTS `v_events_noack`*/;
/*!50001 CREATE TABLE `v_events_noack` (
  `uid` int(11),
  `type` varchar(50),
  `host` varchar(50),
  `facility` varchar(50),
  `priority` varchar(10),
  `level` varchar(10),
  `tag` varchar(10),
  `program` varchar(50),
  `datetime` datetime,
  `message` blob,
  `acknowledged` tinyint(1) unsigned
) */;

--
-- Temporary table structure for view `v_select_facility`
--

DROP TABLE IF EXISTS `v_select_facility`;
/*!50001 DROP VIEW IF EXISTS `v_select_facility`*/;
/*!50001 CREATE TABLE `v_select_facility` (
  `facility` varchar(50)
) */;

--
-- Temporary table structure for view `v_select_host`
--

DROP TABLE IF EXISTS `v_select_host`;
/*!50001 DROP VIEW IF EXISTS `v_select_host`*/;
/*!50001 CREATE TABLE `v_select_host` (
  `host` varchar(50)
) */;

--
-- Temporary table structure for view `v_select_priority`
--

DROP TABLE IF EXISTS `v_select_priority`;
/*!50001 DROP VIEW IF EXISTS `v_select_priority`*/;
/*!50001 CREATE TABLE `v_select_priority` (
  `priority` varchar(10)
) */;

--
-- Temporary table structure for view `v_select_type`
--

DROP TABLE IF EXISTS `v_select_type`;
/*!50001 DROP VIEW IF EXISTS `v_select_type`*/;
/*!50001 CREATE TABLE `v_select_type` (
  `type` varchar(50)
) */;

--
-- Dumping routines for database 'eventdb'
--
DELIMITER ;;
/*!50003 DROP PROCEDURE IF EXISTS `spr_acknowledge` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 PROCEDURE `spr_acknowledge`(IN event_id INT, IN ctype varchar(50), IN author varchar(50), IN message BLOB, IN ack INT(2))
    MODIFIES SQL DATA
BEGIN

START TRANSACTION;

INSERT INTO comments(event_fk, `type`, author, `comment`, crdate)
VALUES(event_id, ctype, author, message, now());

UPDATE events set acknowledged=ack
where 
	uid = event_id 
	and 	NOT acknowledged = ack
LIMIT 1;

COMMIT;

END */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
/*!50003 DROP PROCEDURE IF EXISTS `spr_get_comments` */;;
/*!50003 SET SESSION SQL_MODE=""*/;;
/*!50003 CREATE*/ /*!50020 DEFINER=`root`@`localhost`*/ /*!50003 PROCEDURE `spr_get_comments`(IN param INT)
SELECT * from comments
where event_fk = param */;;
/*!50003 SET SESSION SQL_MODE=@OLD_SQL_MODE*/;;
DELIMITER ;

--
-- Final view structure for view `v_events_ack`
--

/*!50001 DROP TABLE IF EXISTS `v_events_ack`*/;
/*!50001 DROP VIEW IF EXISTS `v_events_ack`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_events_ack` AS select `events`.`uid` AS `uid`,`events`.`type` AS `type`,`events`.`host` AS `host`,`events`.`facility` AS `facility`,`events`.`priority` AS `priority`,`events`.`level` AS `level`,`events`.`tag` AS `tag`,`events`.`program` AS `program`,`events`.`datetime` AS `datetime`,`events`.`message` AS `message`,`events`.`acknowledged` AS `acknowledged` from `events` where (`events`.`acknowledged` = 1) */;

--
-- Final view structure for view `v_events_all`
--

/*!50001 DROP TABLE IF EXISTS `v_events_all`*/;
/*!50001 DROP VIEW IF EXISTS `v_events_all`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_events_all` AS select `events`.`uid` AS `uid`,`events`.`type` AS `type`,`events`.`host` AS `host`,`events`.`facility` AS `facility`,`events`.`priority` AS `priority`,`events`.`level` AS `level`,`events`.`tag` AS `tag`,`events`.`program` AS `program`,`events`.`datetime` AS `datetime`,`events`.`message` AS `message`,`events`.`acknowledged` AS `acknowledged` from `events` */;

--
-- Final view structure for view `v_events_noack`
--

/*!50001 DROP TABLE IF EXISTS `v_events_noack`*/;
/*!50001 DROP VIEW IF EXISTS `v_events_noack`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_events_noack` AS select `events`.`uid` AS `uid`,`events`.`type` AS `type`,`events`.`host` AS `host`,`events`.`facility` AS `facility`,`events`.`priority` AS `priority`,`events`.`level` AS `level`,`events`.`tag` AS `tag`,`events`.`program` AS `program`,`events`.`datetime` AS `datetime`,`events`.`message` AS `message`,`events`.`acknowledged` AS `acknowledged` from `events` where (`events`.`acknowledged` = 0) */;

--
-- Final view structure for view `v_select_facility`
--

/*!50001 DROP TABLE IF EXISTS `v_select_facility`*/;
/*!50001 DROP VIEW IF EXISTS `v_select_facility`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_select_facility` AS select distinct `events`.`facility` AS `facility` from `events` order by `events`.`facility` */;

--
-- Final view structure for view `v_select_host`
--

/*!50001 DROP TABLE IF EXISTS `v_select_host`*/;
/*!50001 DROP VIEW IF EXISTS `v_select_host`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_select_host` AS select distinct `events`.`host` AS `host` from `events` order by `events`.`host` */;

--
-- Final view structure for view `v_select_priority`
--

/*!50001 DROP TABLE IF EXISTS `v_select_priority`*/;
/*!50001 DROP VIEW IF EXISTS `v_select_priority`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_select_priority` AS select distinct `events`.`priority` AS `priority` from `events` order by `events`.`priority` */;

--
-- Final view structure for view `v_select_type`
--

/*!50001 DROP TABLE IF EXISTS `v_select_type`*/;
/*!50001 DROP VIEW IF EXISTS `v_select_type`*/;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_select_type` AS select distinct `events`.`type` AS `type` from `events` order by `events`.`type` */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2008-03-25 12:35:41
