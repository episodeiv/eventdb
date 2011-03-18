CREATE TABLE `event` (
  `id` bigint(20) unsigned NOT NULL auto_increment,
  `host_name` varchar(255) character set ascii collate ascii_bin NOT NULL,
  `host_address` binary(16) NOT NULL,
  `type` int(11) NOT NULL,
  `facility` int(11) ,
  `priority` int(11) NOT NULL,
  `program` varchar(50) character set ascii NOT NULL,
  `message` varchar(4096) default NULL,
  `ack` tinyint(1) default '0',
  `created` datetime default NULL,
  `modified` datetime default NULL,
  PRIMARY KEY  (`id`),
  KEY `idx_id` (`id`,`host_name`,`host_address`,`type`,`program`,`priority`,`facility`,`ack`,`created`,`modified`),
  KEY `order_created` (`created`),
  KEY `order_host_name` (`host_name`),
  KEY `order_prioNr` (`priority`),
  KEY `order_facNr` (`facility`),
  KEY `order_modified` (`modified`),
  KEY `order_ack` (`ack`)
   
) ENGINE=InnoDB;

CREATE TABLE `comment` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `event_id` bigint(20) unsigned NOT NULL,
  `type` int(10) unsigned default '0',
  `message` varchar(1024) default NULL,
  `created` datetime default NULL,
  `modified` datetime default NULL,
  `user` varchar(64) NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `fk_event_id` (`event_id`),
  KEY `idx_all` (`id`,`event_id`,`user`),
  CONSTRAINT `db_comments_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB

