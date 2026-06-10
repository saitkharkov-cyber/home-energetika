ALTER TABLE `oc_pump_selector_product`
  ADD COLUMN `name` VARCHAR(255) NOT NULL DEFAULT '' AFTER `is_eligible`,
  ADD COLUMN `manufacturer` VARCHAR(255) NOT NULL DEFAULT '' AFTER `name`,
  ADD COLUMN `image` VARCHAR(255) NOT NULL DEFAULT '' AFTER `manufacturer`,
  ADD COLUMN `product_price` DECIMAL(15,4) NOT NULL DEFAULT 0 AFTER `image`,
  ADD COLUMN `quantity` INT(11) NOT NULL DEFAULT 0 AFTER `product_price`,
  ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT 1 AFTER `quantity`;

ALTER TABLE `oc_pump_selector_product`
  ADD INDEX `idx_selector_filter` (`status`, `voltage`, `pump_diameter_mm`, `max_head_m`, `max_flow_l_min`),
  ADD INDEX `idx_selector_price` (`product_price`);

-- Add this index only if there is no existing equivalent brand_priority index.
-- The earlier schema may already have idx_pump_selector_brand_priority.
-- ALTER TABLE `oc_pump_selector_product`
--   ADD INDEX `idx_selector_brand` (`brand_priority`);
