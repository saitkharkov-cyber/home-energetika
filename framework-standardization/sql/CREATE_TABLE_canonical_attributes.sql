-- Draft SQL for creating canonical attributes registry.
-- Project: Home Energetika / Framework Standardization
-- Table name uses OpenCart DB prefix placeholder: {DB_PREFIX}
-- Charset/collation follows current OpenCart database: utf8 / utf8_general_ci
-- Do not run on production without replacing {DB_PREFIX} with the real DB prefix.

CREATE TABLE `{DB_PREFIX}canonical_attributes` (
  `canonical_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `canonical_code` VARCHAR(64) NOT NULL,

  `target_attribute_id` INT UNSIGNED NOT NULL,
  `target_attribute_name` VARCHAR(255) NOT NULL,
  `target_attribute_group_id` INT UNSIGNED NOT NULL,
  `target_attribute_group_name` VARCHAR(255) NOT NULL,

  `status` ENUM('draft', 'active') NOT NULL DEFAULT 'draft',
  `locked` TINYINT(1) NOT NULL DEFAULT 0,

  `comment` TEXT NULL,

  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,

  PRIMARY KEY (`canonical_id`),

  UNIQUE KEY `uniq_canonical_code` (`canonical_code`),
  UNIQUE KEY `uniq_target_attribute_id` (`target_attribute_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;