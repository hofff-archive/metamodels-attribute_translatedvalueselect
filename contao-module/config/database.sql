-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the Contao    *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

CREATE TABLE `tl_metamodel_translatedvalueselect` (

  `att_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `language` varchar(2) NOT NULL,

  `tstamp` int(10) unsigned NOT NULL,
  `value` int(10) unsigned NOT NULL,

  PRIMARY KEY  (`att_id`, `item_id`, `language`),
  KEY `att_value` (`att_id`, `value`),
  KEY `att_lang` (`att_id`, `language`),

) ENGINE=MyISAM DEFAULT CHARSET=utf8;
