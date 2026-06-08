INSERT INTO `oc_pump_selector_product` (
  `product_id`,
  `max_head_m`,
  `max_flow_l_min`,
  `pump_diameter_mm`,
  `voltage`,
  `brand_priority`,
  `is_eligible`,
  `date_added`,
  `date_modified`
)
SELECT
  p.`product_id`,
  CAST(REPLACE(REPLACE(TRIM(head_attr.`text`), ',', '.'), ' ', '') AS DECIMAL(10,2)) AS `max_head_m`,
  CAST(REPLACE(REPLACE(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(flow_attr.`text`, 'л/мин', 1), '(', -1)), ',', '.'), ' ', '') AS DECIMAL(10,2)) AS `max_flow_l_min`,
  CAST(REPLACE(REPLACE(TRIM(diameter_attr.`text`), ',', '.'), ' ', '') AS DECIMAL(10,2)) AS `pump_diameter_mm`,
  CASE
    WHEN voltage_attr.`text` LIKE '%380%' THEN '380'
    WHEN voltage_attr.`text` LIKE '%220%' THEN '220'
    ELSE NULL
  END AS `voltage`,
  CASE
    WHEN TRIM(m.`name`) LIKE 'Pedrollo%' THEN 10
    WHEN TRIM(m.`name`) LIKE 'Belamos%' THEN 5
    ELSE 0
  END AS `brand_priority`,
  1 AS `is_eligible`,
  NOW() AS `date_added`,
  NOW() AS `date_modified`
FROM `oc_product` p
INNER JOIN (
  SELECT DISTINCT `product_id`
  FROM `oc_product_to_category`
  WHERE `category_id` IN (11900308, 11900309)
) p2c
  ON p2c.`product_id` = p.`product_id`
INNER JOIN (
  SELECT pa.`product_id`, MAX(pa.`text`) AS `text`
  FROM `oc_product_attribute` pa
  INNER JOIN `oc_attribute_description` ad
    ON ad.`attribute_id` = pa.`attribute_id`
    AND ad.`name` = 'Максимальный напор'
  WHERE TRIM(pa.`text`) <> ''
  GROUP BY pa.`product_id`
) head_attr
  ON head_attr.`product_id` = p.`product_id`
INNER JOIN (
  SELECT pa.`product_id`, MAX(pa.`text`) AS `text`
  FROM `oc_product_attribute` pa
  INNER JOIN `oc_attribute_description` ad
    ON ad.`attribute_id` = pa.`attribute_id`
    AND ad.`name` = 'Максимальная производительность'
  WHERE TRIM(pa.`text`) <> ''
  GROUP BY pa.`product_id`
) flow_attr
  ON flow_attr.`product_id` = p.`product_id`
INNER JOIN (
  SELECT pa.`product_id`, MAX(pa.`text`) AS `text`
  FROM `oc_product_attribute` pa
  INNER JOIN `oc_attribute_description` ad
    ON ad.`attribute_id` = pa.`attribute_id`
    AND ad.`name` = 'Диаметр насоса'
  WHERE TRIM(pa.`text`) <> ''
  GROUP BY pa.`product_id`
) diameter_attr
  ON diameter_attr.`product_id` = p.`product_id`
LEFT JOIN (
  SELECT pa.`product_id`, MAX(pa.`text`) AS `text`
  FROM `oc_product_attribute` pa
  INNER JOIN `oc_attribute_description` ad
    ON ad.`attribute_id` = pa.`attribute_id`
    AND ad.`name` = 'Напряжение'
  WHERE TRIM(pa.`text`) <> ''
  GROUP BY pa.`product_id`
) voltage_attr
  ON voltage_attr.`product_id` = p.`product_id`
LEFT JOIN `oc_manufacturer` m
  ON m.`manufacturer_id` = p.`manufacturer_id`
WHERE p.`price` > 0
  AND p.`quantity` > 0
  AND p.`status` = 1
  AND flow_attr.`text` LIKE '%(%л/мин%'
ON DUPLICATE KEY UPDATE
  `max_head_m` = VALUES(`max_head_m`),
  `max_flow_l_min` = VALUES(`max_flow_l_min`),
  `pump_diameter_mm` = VALUES(`pump_diameter_mm`),
  `voltage` = VALUES(`voltage`),
  `brand_priority` = VALUES(`brand_priority`),
  `is_eligible` = VALUES(`is_eligible`),
  `date_modified` = VALUES(`date_modified`);
