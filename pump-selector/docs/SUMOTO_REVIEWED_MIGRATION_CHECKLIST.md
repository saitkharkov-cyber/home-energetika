# SUMOTO Reviewed Migration Checklist

## Current status

Decision: `READY_FOR_REVIEWED_MIGRATION`

Fresh production dump verification completed.

Confirmed:

- 98 SUMOTO `product_id` candidates exist on fresh production dump.
- All 98 candidates have `manufacturer_id = 58 / Sumoto`.
- `language_id = 1` confirmed as `Russian / ru-ru / status = 1`.
- Attribute contract confirmed:
  - `12` = `Максимальный напор`
  - `13` = `Максимальная производительность`
  - `15` = `Напряжение`
  - `44` = `Диаметр насоса`
- `oc_pump_selector_product` exists and has expected cache fields.
- All 4 generated SQL files now use `REPLACE INTO` x98.

## SQL files to review

Review these files before production apply:

- `catalog-standardization/sql/generated/sumoto/sumoto_head_attribute_98.sql`
- `catalog-standardization/sql/generated/sumoto/sumoto_flow_attribute_98.sql`
- `catalog-standardization/sql/generated/sumoto/sumoto_voltage_attribute_98.sql`
- `catalog-standardization/sql/generated/sumoto/sumoto_diameter_attribute_98.sql`

Expected operation type:

- `REPLACE INTO oc_product_attribute`
- 98 statements per file
- `language_id = 1`

Target attributes:

- head: `attribute_id = 12`
- flow: `attribute_id = 13`
- voltage: `attribute_id = 15`
- diameter: `attribute_id = 44`

## Additional catalog fix candidate

Review this data fix separately:

    INSERT IGNORE INTO oc_product_to_category (product_id, category_id)
    VALUES
      (4260, 11900213),
      (4260, 11900321);

Reason:

`product_id = 4260` is a valid active Sumoto pump and is included in SUMOTO generated SQL, but is missing parent category links:

- `11900213 = Скважинные насосы`
- `11900321 = Насосы SUMOTO`

Current category:

- `11900323 = Погружные 4-х дюймовые насосы Sumoto`

## Required before production apply

Do not apply SQL until all are true:

- production backup created;
- 4 SQL files reviewed;
- catalog fix for `4260` reviewed;
- no diagnostic controller included in deploy;
- no old local DB copied to production;
- no `framework-standardization` files included in SUMOTO deploy;
- runtime pump-selector files reviewed separately;
- cache rebuild method confirmed.

## Apply order

1. Create production backup.
2. Deploy reviewed pump-selector runtime files if needed.
3. Apply 4 reviewed SUMOTO attribute SQL files.
4. Apply reviewed catalog fix for `product_id = 4260`, if approved.
5. Rebuild `oc_pump_selector_product` cache.
6. Run smoke tests.

## Smoke tests

Check after apply:

- SUMOTO products appear in selector data.
- `brand_priority = 8` for Sumoto products.
- inactive product `1821` does not appear as active selectable item if selector respects product status.
- product `4260` appears under expected parent categories after catalog fix.
- pump head values are parsed.
- flow values are parsed.
- voltage values are parsed.
- diameter values are parsed.
- no SQL errors occurred during apply.
- no unrelated catalog/customer/order tables were changed.

## Final deploy decision options

After review, choose one:

- `APPROVED_FOR_PRODUCTION_APPLY`
- `NEEDS_SQL_REVIEW_FIX`
- `NEEDS_RUNTIME_REVIEW_FIX`
- `BLOCKED_BY_BACKUP`
- `BLOCKED_BY_DATA_MISMATCH`