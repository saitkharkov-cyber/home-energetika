/*
Manual Standardization Toolkit
01 — Инвентаризация атрибутов прямого охвата категории

Заменить:
  {{CATEGORY_ID}}
  {{LANGUAGE_ID}}
*/

SELECT
    pa.attribute_id,
    ad.name AS attribute_name,
    a.attribute_group_id,
    agd.name AS attribute_group_name,
    COUNT(*) AS attribute_rows,
    COUNT(DISTINCT pa.product_id) AS distinct_products,
    ROUND(
        COUNT(DISTINCT pa.product_id) * 100.0 /
        NULLIF((
            SELECT COUNT(DISTINCT p2c_total.product_id)
            FROM oc_product_to_category AS p2c_total
            WHERE p2c_total.category_id = {{CATEGORY_ID}}
        ), 0),
        2
    ) AS category_coverage_percent
FROM oc_product_attribute AS pa
INNER JOIN oc_product_to_category AS p2c
    ON p2c.product_id = pa.product_id
   AND p2c.category_id = {{CATEGORY_ID}}
INNER JOIN oc_attribute AS a
    ON a.attribute_id = pa.attribute_id
LEFT JOIN oc_attribute_description AS ad
    ON ad.attribute_id = pa.attribute_id
   AND ad.language_id = {{LANGUAGE_ID}}
LEFT JOIN oc_attribute_group_description AS agd
    ON agd.attribute_group_id = a.attribute_group_id
   AND agd.language_id = {{LANGUAGE_ID}}
WHERE pa.language_id = {{LANGUAGE_ID}}
GROUP BY
    pa.attribute_id,
    ad.name,
    a.attribute_group_id,
    agd.name
ORDER BY
    distinct_products DESC,
    pa.attribute_id;
