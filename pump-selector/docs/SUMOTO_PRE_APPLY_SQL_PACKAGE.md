# SUMOTO Pre-Apply SQL Package

## Purpose

This document defines the reviewed SQL package for SUMOTO production apply.

It is not a proof of apply and not a production execution log.

No SQL should be applied before:

- production backup is created;
- files are reviewed;
- apply order is confirmed;
- cache rebuild method is confirmed.

## Current deploy decision

Decision: `READY_FOR_REVIEWED_MIGRATION`

Based on:

- fresh production dump verification completed;
- 98 SUMOTO `product_id` candidates confirmed;
- `manufacturer_id = 58 / Sumoto` confirmed for all 98 candidates;
- `language_id = 1` confirmed;
- target attributes `12`, `13`, `15`, `44` confirmed;
- `oc_pump_selector_product` structure confirmed;
- all 4 SUMOTO SQL files use `REPLACE INTO` x98.

## SQL package files

Apply these files in this order:

1. `catalog-standardization/sql/generated/sumoto/sumoto_head_attribute_98.sql`
2. `catalog-standardization/sql/generated/sumoto/sumoto_flow_attribute_98.sql`
3. `catalog-standardization/sql/generated/sumoto/sumoto_voltage_attribute_98.sql`
4. `catalog-standardization/sql/generated/sumoto/sumoto_diameter_attribute_98.sql`

Expected operation type for each file:

- `REPLACE INTO oc_product_attribute`
- 98 statements per file
- `language_id = 1`

Target attributes:

- `sumoto_head_attribute_98.sql` -> `attribute_id = 12`
- `sumoto_flow_attribute_98.sql` -> `attribute_id = 13`
- `sumoto_voltage_attribute_98.sql` -> `attribute_id = 15`
- `sumoto_diameter_attribute_98.sql` -> `attribute_id = 44`

## Additional catalog fix

Apply only if approved during final review:

    INSERT IGNORE INTO oc_product_to_category (product_id, category_id)
    VALUES
      (4260, 11900213),
      (4260, 11900321);

Reason:

`product_id = 4260` is a valid active Sumoto pump and is included in SUMOTO generated SQL, but currently has only:

- `11900323 = Погружные 4-х дюймовые насосы Sumoto`

Missing parent links:

- `11900213 = Скважинные насосы`
- `11900321 = Насосы SUMOTO`

## Do not apply

Do not apply or deploy as part of this SQL package:

- `pump-selector/module/catalog/controller/extension/module/pump_selector_test.php`
- `pump-selector/sql/seed-from-attributes.sql`
- old local DB dumps
- local `_local/` files
- any `framework-standardization` files
- customer/order/session/user/cart/api related tables
- full old local database copy

## Required production backup

Before SQL apply, create production backup.

Minimum backup scope:

- `oc_product_attribute`
- `oc_product_to_category`
- `oc_pump_selector_product`

Recommended backup scope:

- full production database backup before any SQL apply.

## Apply order

1. Confirm production backup exists.
2. Confirm SQL package files are current from Git.
3. Apply `sumoto_head_attribute_98.sql`.
4. Apply `sumoto_flow_attribute_98.sql`.
5. Apply `sumoto_voltage_attribute_98.sql`.
6. Apply `sumoto_diameter_attribute_98.sql`.
7. Apply catalog fix for `product_id = 4260`, if approved.
8. Rebuild `oc_pump_selector_product` cache.
9. Run smoke tests.

## Post-apply verification

After apply, verify:

- 98 rows exist for `attribute_id = 12`.
- 98 rows exist for `attribute_id = 13`.
- 98 rows exist for `attribute_id = 15`.
- 98 rows exist for `attribute_id = 44`.
- all 98 rows use `language_id = 1`.
- all 98 product candidates remain `manufacturer_id = 58 / Sumoto`.
- `product_id = 4260` has parent category links if catalog fix was applied.
- `oc_pump_selector_product` was rebuilt after attribute SQL apply.
- selector smoke tests pass.

## Smoke tests

Check after cache rebuild:

- SUMOTO products appear in selector data.
- Sumoto products receive `brand_priority = 8`.
- inactive product `1821` does not appear as active selectable item if selector respects product status.
- product `4260` appears under expected parent categories if catalog fix was applied.
- head values are parsed.
- flow values are parsed.
- voltage values are parsed.
- diameter values are parsed.
- no unrelated customer/order/session/user tables were changed.

## Final decision after review

Choose one before production apply:

- `APPROVED_FOR_PRODUCTION_APPLY`
- `NEEDS_SQL_REVIEW_FIX`
- `NEEDS_RUNTIME_REVIEW_FIX`
- `BLOCKED_BY_BACKUP`
- `BLOCKED_BY_DATA_MISMATCH`