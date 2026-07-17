/*
Manual Standardization Toolkit
07 — Финальный аудит категории

Заменить:
  {{CATEGORY_ID}}
  {{LANGUAGE_ID}}

Также вручную заполнить блок expected:
  attribute_id + expected_rows

И правила noncanonical_rows.

Шаблон не использует CTE.
*/

SELECT
    u.attribute_id,
    ca.canonical_code,
    ca.target_attribute_name AS canonical_name,
    actual.actual_name,
    expected.expected_rows,
    COALESCE(actual.actual_rows, 0) AS actual_rows,
    COALESCE(actual.distinct_products, 0) AS distinct_products,
    COALESCE(actual.duplicate_rows, 0) AS duplicate_rows,
    COALESCE(actual.empty_rows, 0) AS empty_rows,
    COALESCE(actual.noncanonical_rows, 0) AS noncanonical_rows,

    CASE
        WHEN ca.target_attribute_id IS NULL
            THEN 'UNEXPECTED_ATTRIBUTE'
        WHEN actual.attribute_id IS NULL
            THEN 'MISSING_ATTRIBUTE'
        WHEN actual.actual_name <> ca.target_attribute_name
            THEN 'NAME_MISMATCH'
        WHEN expected.expected_rows IS NULL
            THEN 'EXPECTED_COUNT_NOT_SET'
        WHEN actual.actual_rows <> expected.expected_rows
            THEN 'ROW_COUNT_MISMATCH'
        WHEN actual.duplicate_rows > 0
            THEN 'DUPLICATES'
        WHEN actual.empty_rows > 0
            THEN 'EMPTY_VALUES'
        WHEN actual.noncanonical_rows > 0
            THEN 'NONCANONICAL_VALUES'
        ELSE 'OK'
    END AS audit_status

FROM (
    SELECT target_attribute_id AS attribute_id
    FROM oc_canonical_attributes
    WHERE status = 'active'
      AND locked = 1

    UNION

    SELECT DISTINCT pa.attribute_id
    FROM oc_product_attribute AS pa
    INNER JOIN oc_product_to_category AS p2c
        ON p2c.product_id = pa.product_id
       AND p2c.category_id = {{CATEGORY_ID}}
    WHERE pa.language_id = {{LANGUAGE_ID}}
) AS u

LEFT JOIN oc_canonical_attributes AS ca
    ON ca.target_attribute_id = u.attribute_id
   AND ca.status = 'active'
   AND ca.locked = 1

LEFT JOIN (
    /*
    ЗАМЕНИТЬ ПРИМЕР ФАКТИЧЕСКИМ СПИСКОМ КАТЕГОРИИ.
    */
    SELECT 0 AS attribute_id, 0 AS expected_rows
    /* UNION ALL SELECT 12, 478 */
    /* UNION ALL SELECT 13, 483 */
) AS expected
    ON expected.attribute_id = u.attribute_id

LEFT JOIN (
    SELECT
        pa.attribute_id,
        MAX(ad.name) AS actual_name,
        COUNT(*) AS actual_rows,
        COUNT(DISTINCT pa.product_id) AS distinct_products,
        COUNT(*) - COUNT(DISTINCT pa.product_id) AS duplicate_rows,

        SUM(
            CASE
                WHEN TRIM(CAST(pa.text AS CHAR)) = '' THEN 1
                ELSE 0
            END
        ) AS empty_rows,

        SUM(
            CASE
                WHEN TRIM(CAST(pa.text AS CHAR)) = ''
                    THEN 0

                /*
                ДОБАВИТЬ КАТЕГОРИЙНЫЕ ПРАВИЛА.

                Пример числового атрибута:
                WHEN pa.attribute_id IN (12, 13, 14)
                 AND TRIM(CAST(pa.text AS CHAR))
                     NOT REGEXP '^[0-9]+([.][0-9]+)?$'
                    THEN 1

                Пример перечисления:
                WHEN pa.attribute_id = 15
                 AND TRIM(CAST(pa.text AS CHAR)) NOT IN ('220', '380')
                    THEN 1
                */

                ELSE 0
            END
        ) AS noncanonical_rows

    FROM oc_product_attribute AS pa
    INNER JOIN oc_product_to_category AS p2c
        ON p2c.product_id = pa.product_id
       AND p2c.category_id = {{CATEGORY_ID}}
    INNER JOIN oc_attribute_description AS ad
        ON ad.attribute_id = pa.attribute_id
       AND ad.language_id = pa.language_id
    WHERE pa.language_id = {{LANGUAGE_ID}}
    GROUP BY pa.attribute_id
) AS actual
    ON actual.attribute_id = u.attribute_id

ORDER BY u.attribute_id;
