/*
Manual Standardization Toolkit
06 — Post-check одной характеристики

Заменить:
  {{CATEGORY_ID}}
  {{LANGUAGE_ID}}
  {{CANONICAL_ATTRIBUTE_ID}}
  {{ALIAS_ATTRIBUTE_IDS}}
  {{NONCANONICAL_CONDITION}}

Пример NONCANONICAL_CONDITION:
  TRIM(CAST(pa.text AS CHAR)) NOT IN ('220', '380')

Если aliases отсутствуют, блок проверки aliases нужно удалить вручную.
*/

SELECT
    COUNT(*) AS canonical_rows,
    COUNT(DISTINCT pa.product_id) AS canonical_products,

    COUNT(*) - COUNT(DISTINCT pa.product_id) AS duplicate_rows,

    SUM(
        CASE
            WHEN TRIM(CAST(pa.text AS CHAR)) = '' THEN 1
            ELSE 0
        END
    ) AS empty_rows,

    SUM(
        CASE
            WHEN {{NONCANONICAL_CONDITION}} THEN 1
            ELSE 0
        END
    ) AS noncanonical_rows

FROM oc_product_attribute AS pa
INNER JOIN oc_product_to_category AS p2c
    ON p2c.product_id = pa.product_id
   AND p2c.category_id = {{CATEGORY_ID}}
WHERE pa.attribute_id = {{CANONICAL_ATTRIBUTE_ID}}
  AND pa.language_id = {{LANGUAGE_ID}};


/*
Остатки aliases.
*/

SELECT
    pa.attribute_id,
    COUNT(*) AS alias_rows,
    COUNT(DISTINCT pa.product_id) AS alias_products
FROM oc_product_attribute AS pa
INNER JOIN oc_product_to_category AS p2c
    ON p2c.product_id = pa.product_id
   AND p2c.category_id = {{CATEGORY_ID}}
WHERE pa.attribute_id IN ({{ALIAS_ATTRIBUTE_IDS}})
  AND pa.language_id = {{LANGUAGE_ID}}
GROUP BY pa.attribute_id
ORDER BY pa.attribute_id;


/*
Товары одновременно с каноном и alias.
*/

SELECT
    c.product_id,
    pd.name AS product_name,
    TRIM(CAST(c.text AS CHAR)) AS canonical_value,
    GROUP_CONCAT(
        DISTINCT CONCAT(a.attribute_id, ':', TRIM(CAST(a.text AS CHAR)))
        ORDER BY a.attribute_id
        SEPARATOR ' | '
    ) AS alias_values
FROM oc_product_attribute AS c
INNER JOIN oc_product_to_category AS p2c
    ON p2c.product_id = c.product_id
   AND p2c.category_id = {{CATEGORY_ID}}
INNER JOIN oc_product_attribute AS a
    ON a.product_id = c.product_id
   AND a.language_id = {{LANGUAGE_ID}}
   AND a.attribute_id IN ({{ALIAS_ATTRIBUTE_IDS}})
LEFT JOIN oc_product_description AS pd
    ON pd.product_id = c.product_id
   AND pd.language_id = {{LANGUAGE_ID}}
WHERE c.attribute_id = {{CANONICAL_ATTRIBUTE_ID}}
  AND c.language_id = {{LANGUAGE_ID}}
GROUP BY
    c.product_id,
    pd.name,
    TRIM(CAST(c.text AS CHAR))
ORDER BY c.product_id;
