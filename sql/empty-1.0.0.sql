CREATE TABLE IF NOT EXISTS `glpi_plugin_readmarks_marks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `users_id` INT UNSIGNED NOT NULL,
  `itemtype` VARCHAR(100) NOT NULL DEFAULT 'Ticket',
  `items_id` INT UNSIGNED NOT NULL,
  `last_read_date` TIMESTAMP NULL DEFAULT NULL,
  `date_mod` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unicity` (`users_id`, `itemtype`, `items_id`),
  KEY `item` (`itemtype`, `items_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
