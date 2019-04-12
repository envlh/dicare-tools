CREATE TABLE `import_properties` (
  `propertyA` bigint(20) unsigned NOT NULL,
  `propertyB` bigint(20) unsigned NOT NULL,
  `intersection_cardinality` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`propertyA`,`propertyB`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `import_property` (
  `id` bigint(20) unsigned NOT NULL,
  `cardinality` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `parameter` (
  `key` varchar(64) CHARACTER SET ascii NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `properties` (
  `propertyA` bigint(20) unsigned NOT NULL,
  `propertyB` bigint(20) unsigned NOT NULL,
  `intersection_cardinality` bigint(20) unsigned NOT NULL,
  `jaccard_index` decimal(10,9) unsigned NOT NULL,
  PRIMARY KEY (`propertyA`,`propertyB`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `property` (
  `id` bigint(20) unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `label` varchar(250) NOT NULL,
  `cardinality` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `type_cardinality` (`type`,`cardinality`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `query` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `query_hash` binary(20) NOT NULL,
  `endpoint_id` varchar(4) CHARACTER SET latin1 NOT NULL,
  `last_update` datetime NOT NULL,
  `query` text NOT NULL,
  `response` mediumblob NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `query_endpoint_uq` (`query_hash`,`endpoint_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
