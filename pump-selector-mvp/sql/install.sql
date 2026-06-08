CREATE TABLE IF NOT EXISTS `oc_pump_selector_product` (
  `selector_product_id` INT NOT NULL AUTO_INCREMENT,
  `product_id` INT NOT NULL,
  `max_head_m` DECIMAL(10,2) NOT NULL,
  `max_flow_l_min` DECIMAL(10,2) NOT NULL,
  `pump_diameter_mm` DECIMAL(10,2) NULL,
  `voltage` VARCHAR(20) NULL,
  `brand_priority` TINYINT DEFAULT 0,
  `is_eligible` TINYINT DEFAULT 1,
  `date_added` DATETIME,
  `date_modified` DATETIME,
  PRIMARY KEY (`selector_product_id`),
  UNIQUE KEY `uniq_product_id` (`product_id`),
  KEY `idx_max_head_m` (`max_head_m`),
  KEY `idx_max_flow_l_min` (`max_flow_l_min`),
  KEY `idx_pump_diameter_mm` (`pump_diameter_mm`),
  KEY `idx_voltage` (`voltage`),
  KEY `idx_is_eligible` (`is_eligible`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
