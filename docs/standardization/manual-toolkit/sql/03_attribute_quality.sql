/*
Manual Standardization Toolkit
03 — Проверка качества выбранного атрибута

Заменить:
  {{CATEGORY_ID}}
  {{LANGUAGE_ID}}
  {{ATTRIBUTE_ID}}

Запрос возвращает одну сводную строку.
*/

SELECT
    {{ATTRIBUTE_ID}} AS attribute_id,
    COUNT(*) AS attribute_rows,
    COUNT(DISTINCT pa.product_id) AS distinct_products,

    SUM(
        CASE
            WHEN TRIM(CAST(pa.text AS CHAR)) = '' THEN 1
            ELSE 0
        END
    ) AS empty_rows,

    COUNT(*) - COUNT(DISTINCT pa.product_id) AS duplicate_rows,

    SUM(
        CASE
            WHEN CAST(pa.text AS CHAR) <> TRIM(CAST(pa.text AS CHAR)) THEN 1
            ELSE 0
        END
    ) AS rows_with_edge_spaces,

    SUM(
        CASE
            WHEN TRIM(CAST(pa.text AS CHAR)) REGEXP '^[0-9]+,[0-9]+$' THEN 1
            ELSE 0
        END
    ) AS decimal_comma_rows,

    SUM(
        CASE
            WHEN TRIM(CAST(pa.text AS CHAR)) REGEXP '[[:alpha:]]' THEN 1
            ELSE 0
        END
    ) AS rows_with_letters,

    SUM(
        CASE
            WHEN TRIM(CAST(pa.text AS CHAR)) REGEXP '[0-9].*[-–—].*[0-9]' THEN 1
            ELSE 0
        END
    ) AS range_like_rows

FROM oc_product_attribute AS pa
INNER JOIN oc_product_to_category AS p2c
    ON p2c.product_id = pa.product_id
   AND p2c.category_id = {{CATEGORY_ID}}
WHERE pa.attribute_id = {{ATTRIBUTE_ID}}
  AND pa.language_id = {{LANGUAGE_ID}};


/*
Дополнительная проверка:
товары, у которых один атрибут записан более одного раза.
*/

SELECT
    pa.product_id,
    pd.name AS product_name,
    COUNT(*) AS duplicate_count,
    COUNT(DISTINCT TRIM(CAST(pa.text AS CHAR))) AS distinct_value_count,
    GROUP_CONCAT(
        TRIM(CAST(pa.text AS CHAR))
        ORDER BY TRIM(CAST(pa.text AS CHAR))
        SEPARATOR ' | '
    ) AS values_found
FROM oc_product_attribute AS pa
INNER JOIN oc_product_to_category AS p2c
    ON p2c.product_id = pa.product_id
   AND p2c.category_id = {{CATEGORY_ID}}
LEFT JOIN oc_product_description AS pd
    ON pd.product_id = pa.product_id
   AND pd.language_id = {{LANGUAGE_ID}}
WHERE pa.attribute_id = {{ATTRIBUTE_ID}}
  AND pa.language_id = {{LANGUAGE_ID}}
GROUP BY
    pa.product_id,
    pd.name
HAVING COUNT(*) > 1
ORDER BY
    distinct_value_count DESC,
    duplicate_count DESC,
    pa.product_id;
