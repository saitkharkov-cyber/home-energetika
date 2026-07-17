/*
Manual Standardization Toolkit
02 — Распределение значений выбранного атрибута

Заменить:
  {{CATEGORY_ID}}
  {{LANGUAGE_ID}}
  {{ATTRIBUTE_ID}}

GROUP_CONCAT ограничивает вывод примеров.
*/

SELECT
    TRIM(CAST(pa.text AS CHAR)) AS raw_value,
    COUNT(*) AS attribute_rows,
    COUNT(DISTINCT pa.product_id) AS distinct_products,
    GROUP_CONCAT(
        DISTINCT CONCAT(
            pa.product_id,
            ': ',
            LEFT(pd.name, 120)
        )
        ORDER BY pa.product_id
        SEPARATOR ' | '
    ) AS product_examples
FROM oc_product_attribute AS pa
INNER JOIN oc_product_to_category AS p2c
    ON p2c.product_id = pa.product_id
   AND p2c.category_id = {{CATEGORY_ID}}
LEFT JOIN oc_product_description AS pd
    ON pd.product_id = pa.product_id
   AND pd.language_id = {{LANGUAGE_ID}}
WHERE pa.attribute_id = {{ATTRIBUTE_ID}}
  AND pa.language_id = {{LANGUAGE_ID}}
GROUP BY TRIM(CAST(pa.text AS CHAR))
ORDER BY
    attribute_rows DESC,
    raw_value;
