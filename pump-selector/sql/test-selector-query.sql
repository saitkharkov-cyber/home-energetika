SET @required_head_m = 100.00;
SET @required_flow_l_min = 45.00;
SET @required_diameter_mm = NULL;
SET @required_voltage = '380';

SELECT
  best_price.`result_type`,
  best_price.`product_id`,
  best_price.`max_head_m`,
  best_price.`max_flow_l_min`,
  best_price.`pump_diameter_mm`,
  best_price.`voltage`,
  best_price.`brand_priority`,
  best_price.`head_reserve`,
  best_price.`flow_reserve`,
  best_price.`total_reserve`,
  best_price.`price`
FROM (
  SELECT
    'best_price' AS `result_type`,
    psp.`product_id`,
    psp.`max_head_m`,
    psp.`max_flow_l_min`,
    psp.`pump_diameter_mm`,
    psp.`voltage`,
    psp.`brand_priority`,
    (psp.`max_head_m` - @required_head_m) AS `head_reserve`,
    (psp.`max_flow_l_min` - @required_flow_l_min) AS `flow_reserve`,
    ((psp.`max_head_m` - @required_head_m) + (psp.`max_flow_l_min` - @required_flow_l_min)) AS `total_reserve`,
    p.`price`
  FROM `oc_pump_selector_product` psp
  INNER JOIN `oc_product` p
    ON p.`product_id` = psp.`product_id`
  WHERE psp.`is_eligible` = 1
    AND psp.`max_head_m` >= @required_head_m
    AND psp.`max_flow_l_min` >= @required_flow_l_min
    AND (@required_diameter_mm IS NULL OR psp.`pump_diameter_mm` <= @required_diameter_mm)
    AND psp.`voltage` = @required_voltage
    AND p.`price` > 0
    AND p.`quantity` > 0
    AND p.`status` = 1
  ORDER BY
    p.`price` ASC,
    (psp.`max_head_m` - @required_head_m) ASC,
    (psp.`max_flow_l_min` - @required_flow_l_min) ASC,
    psp.`product_id` ASC
  LIMIT 1
) best_price

UNION ALL

SELECT
  optimal_choice.`result_type`,
  optimal_choice.`product_id`,
  optimal_choice.`max_head_m`,
  optimal_choice.`max_flow_l_min`,
  optimal_choice.`pump_diameter_mm`,
  optimal_choice.`voltage`,
  optimal_choice.`brand_priority`,
  optimal_choice.`head_reserve`,
  optimal_choice.`flow_reserve`,
  optimal_choice.`total_reserve`,
  optimal_choice.`price`
FROM (
  SELECT
    'optimal_choice' AS `result_type`,
    psp.`product_id`,
    psp.`max_head_m`,
    psp.`max_flow_l_min`,
    psp.`pump_diameter_mm`,
    psp.`voltage`,
    psp.`brand_priority`,
    (psp.`max_head_m` - @required_head_m) AS `head_reserve`,
    (psp.`max_flow_l_min` - @required_flow_l_min) AS `flow_reserve`,
    ((psp.`max_head_m` - @required_head_m) + (psp.`max_flow_l_min` - @required_flow_l_min)) AS `total_reserve`,
    p.`price`
  FROM `oc_pump_selector_product` psp
  INNER JOIN `oc_product` p
    ON p.`product_id` = psp.`product_id`
  WHERE psp.`is_eligible` = 1
    AND psp.`max_head_m` >= @required_head_m
    AND psp.`max_flow_l_min` >= @required_flow_l_min
    AND (@required_diameter_mm IS NULL OR psp.`pump_diameter_mm` <= @required_diameter_mm)
    AND psp.`voltage` = @required_voltage
    AND p.`price` > 0
    AND p.`quantity` > 0
    AND p.`status` = 1
  ORDER BY
    ((psp.`max_head_m` - @required_head_m) + (psp.`max_flow_l_min` - @required_flow_l_min)) ASC,
    p.`price` ASC,
    psp.`product_id` ASC
  LIMIT 1
) optimal_choice

UNION ALL

SELECT
  premium.`result_type`,
  premium.`product_id`,
  premium.`max_head_m`,
  premium.`max_flow_l_min`,
  premium.`pump_diameter_mm`,
  premium.`voltage`,
  premium.`brand_priority`,
  premium.`head_reserve`,
  premium.`flow_reserve`,
  premium.`total_reserve`,
  premium.`price`
FROM (
  SELECT
    'premium' AS `result_type`,
    psp.`product_id`,
    psp.`max_head_m`,
    psp.`max_flow_l_min`,
    psp.`pump_diameter_mm`,
    psp.`voltage`,
    psp.`brand_priority`,
    (psp.`max_head_m` - @required_head_m) AS `head_reserve`,
    (psp.`max_flow_l_min` - @required_flow_l_min) AS `flow_reserve`,
    ((psp.`max_head_m` - @required_head_m) + (psp.`max_flow_l_min` - @required_flow_l_min)) AS `total_reserve`,
    p.`price`
  FROM `oc_pump_selector_product` psp
  INNER JOIN `oc_product` p
    ON p.`product_id` = psp.`product_id`
  WHERE psp.`is_eligible` = 1
    AND psp.`max_head_m` >= @required_head_m
    AND psp.`max_flow_l_min` >= @required_flow_l_min
    AND (@required_diameter_mm IS NULL OR psp.`pump_diameter_mm` <= @required_diameter_mm)
    AND psp.`voltage` = @required_voltage
    AND psp.`brand_priority` > 0
    AND p.`price` > 0
    AND p.`quantity` > 0
    AND p.`status` = 1
  ORDER BY
    psp.`brand_priority` DESC,
    (psp.`max_head_m` - @required_head_m) ASC,
    (psp.`max_flow_l_min` - @required_flow_l_min) ASC,
    p.`price` ASC,
    psp.`product_id` ASC
  LIMIT 1
) premium;
