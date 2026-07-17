/*
Manual Standardization Toolkit
04 — Пересечение канона и aliases

Заменить:
  {{CATEGORY_ID}}
  {{LANGUAGE_ID}}
  {{CANONICAL_ATTRIBUTE_ID}}
  {{ALIAS_ATTRIBUTE_IDS}}

Пример:
  {{ALIAS_ATTRIBUTE_IDS}} -> 57, 79, 99

Важно:
- запрос сравнивает TRIM(raw value);
- смысловая совместимость и нормализация этим запросом не доказываются.
*/

SELECT
    p.product_id,
    pd.name AS product_name,
    c.canonical_value,
    s.alias_values,
    CASE
        WHEN c.product_id IS NOT NULL
         AND s.product_id IS NULL
            THEN 'canonical_only'

        WHEN c.product_id IS NULL
         AND s.product_id IS NOT NULL
            THEN 'alias_only'

        WHEN c.product_id IS NOT NULL
         AND s.product_id IS NOT NULL
         AND FIND_IN_SET(c.canonical_value, s.alias_values_csv) > 0
            THEN 'both_same'

        WHEN c.product_id IS NOT NULL
         AND s.product_id IS NOT NULL
            THEN 'both_conflict'

        ELSE 'neither'
    END AS overlap_status

FROM (
    SELECT DISTINCT p2c.product_id
    FROM oc_product_to_category AS p2c
    WHERE p2c.category_id = {{CATEGORY_ID}}
) AS p

LEFT JOIN (
    SELECT
        pa.product_id,
        MAX(TRIM(CAST(pa.text AS CHAR))) AS canonical_value
    FROM oc_product_attribute AS pa
    WHERE pa.attribute_id = {{CANONICAL_ATTRIBUTE_ID}}
      AND pa.language_id = {{LANGUAGE_ID}}
    GROUP BY pa.product_id
) AS c
    ON c.product_id = p.product_id

LEFT JOIN (
    SELECT
        pa.product_id,
        GROUP_CONCAT(
            DISTINCT CONCAT(pa.attribute_id, ':', TRIM(CAST(pa.text AS CHAR)))
            ORDER BY pa.attribute_id
            SEPARATOR ' | '
        ) AS alias_values,
        GROUP_CONCAT(
            DISTINCT TRIM(CAST(pa.text AS CHAR))
            ORDER BY TRIM(CAST(pa.text AS CHAR))
            SEPARATOR ','
        ) AS alias_values_csv
    FROM oc_product_attribute AS pa
    WHERE pa.attribute_id IN ({{ALIAS_ATTRIBUTE_IDS}})
      AND pa.language_id = {{LANGUAGE_ID}}
    GROUP BY pa.product_id
) AS s
    ON s.product_id = p.product_id

LEFT JOIN oc_product_description AS pd
    ON pd.product_id = p.product_id
   AND pd.language_id = {{LANGUAGE_ID}}

WHERE c.product_id IS NOT NULL
   OR s.product_id IS NOT NULL

ORDER BY
    overlap_status,
    p.product_id;
