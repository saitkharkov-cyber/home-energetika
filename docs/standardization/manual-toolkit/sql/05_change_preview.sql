/*
Manual Standardization Toolkit
05 — Каркас preview перед изменением

ЭТОТ ЗАПРОС НЕЛЬЗЯ ВЫПОЛНЯТЬ БЕЗ РУЧНОЙ АДАПТАЦИИ.

Заменить:
  {{CATEGORY_ID}}
  {{LANGUAGE_ID}}
  {{SOURCE_ATTRIBUTE_ID}}
  {{TARGET_ATTRIBUTE_ID}}
  {{NEW_VALUE_EXPRESSION}}
  {{SOURCE_FILTER_CONDITION}}

Пример выражения:
  CASE
      WHEN TRIM(CAST(src.text AS CHAR)) = '230' THEN '220'
      WHEN TRIM(CAST(src.text AS CHAR)) = '400' THEN '380'
      ELSE NULL
  END

Пример фильтра:
  TRIM(CAST(src.text AS CHAR)) IN ('230', '400')
*/

SELECT
    src.product_id,
    pd.name AS product_name,
    src.attribute_id AS source_attribute_id,
    TRIM(CAST(src.text AS CHAR)) AS source_value,
    {{TARGET_ATTRIBUTE_ID}} AS target_attribute_id,

    (
        {{NEW_VALUE_EXPRESSION}}
    ) AS proposed_value,

    TRIM(CAST(dst.text AS CHAR)) AS existing_target_value,

    CASE
        WHEN dst.product_id IS NULL THEN 'target_missing'
        WHEN TRIM(CAST(dst.text AS CHAR)) = (
            {{NEW_VALUE_EXPRESSION}}
        ) THEN 'target_same'
        ELSE 'target_conflict'
    END AS preview_status

FROM oc_product_attribute AS src

INNER JOIN oc_product_to_category AS p2c
    ON p2c.product_id = src.product_id
   AND p2c.category_id = {{CATEGORY_ID}}

LEFT JOIN oc_product_attribute AS dst
    ON dst.product_id = src.product_id
   AND dst.attribute_id = {{TARGET_ATTRIBUTE_ID}}
   AND dst.language_id = {{LANGUAGE_ID}}

LEFT JOIN oc_product_description AS pd
    ON pd.product_id = src.product_id
   AND pd.language_id = {{LANGUAGE_ID}}

WHERE src.attribute_id = {{SOURCE_ATTRIBUTE_ID}}
  AND src.language_id = {{LANGUAGE_ID}}
  AND (
      {{SOURCE_FILTER_CONDITION}}
  )

ORDER BY
    preview_status,
    src.product_id;
