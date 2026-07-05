# SUMOTO Production Apply Runbook

## Назначение

Этот документ описывает порядок действий в день production apply для SUMOTO.

Документ не является подтверждением, что apply уже выполнен.

Цель:

- не смешать SUMOTO deploy с другими задачами;
- применить только reviewed SQL/runtime package;
- не использовать старую локальную SUMOTO DB как source of truth;
- выполнить backup до любых production-изменений;
- пересобрать cache после SQL apply;
- выполнить smoke tests после deploy.

## Текущий статус перед apply

SUMOTO status: `READY_FOR_REVIEWED_MIGRATION`

Runtime review decision: `PASS_WITH_CONDITIONS`

Production apply status: `NOT_APPLIED_YET`

Оставшиеся обязательные условия:

- production backup создан;
- SQL package финально reviewed;
- runtime package финально reviewed;
- catalog fix для `product_id = 4260` approved или явно postponed;
- rebuild token доступен deploy operator;
- smoke tests готовы.

## Что входит в SUMOTO apply

### Runtime files

Деплоить только:

- `pump-selector/module/catalog/controller/extension/module/pump_selector.php`
- `pump-selector/module/catalog/model/extension/module/pump_selector.php`
- `pump-selector/module/catalog/model/extension/module/pump_selector_cache_builder.php`
- `pump-selector/module/catalog/view/theme/revolution/template/extension/module/pump_selector.tpl`
- `pump-selector/module/catalog/view/theme/revolution/image/home-water.webp`
- `pump-selector/module/catalog/view/theme/revolution/image/plig-ins.webp`
- `pump-selector/module/catalog/view/theme/revolution/image/pump.webp`

### SQL files

Применять только эти SQL-файлы:

1. `catalog-standardization/sql/generated/sumoto/sumoto_head_attribute_98.sql`
2. `catalog-standardization/sql/generated/sumoto/sumoto_flow_attribute_98.sql`
3. `catalog-standardization/sql/generated/sumoto/sumoto_voltage_attribute_98.sql`
4. `catalog-standardization/sql/generated/sumoto/sumoto_diameter_attribute_98.sql`

Ожидаемый тип операций:

- `REPLACE INTO oc_product_attribute`
- 98 statements per file
- `language_id = 1`

Target attributes:

- `12` = `Максимальный напор`
- `13` = `Максимальная производительность`
- `15` = `Напряжение`
- `44` = `Диаметр насоса`

### Optional catalog fix

Применять только если approved:

    INSERT IGNORE INTO oc_product_to_category (product_id, category_id)
    VALUES
      (4260, 11900213),
      (4260, 11900321);

Назначение:

- добавить `product_id = 4260` в родительские категории:
  - `11900213 = Скважинные насосы`
  - `11900321 = Насосы SUMOTO`

## Что не входит в SUMOTO apply

Не деплоить и не применять:

- `pump-selector/module/catalog/controller/extension/module/pump_selector_test.php`
- `pump-selector/sql/seed-from-attributes.sql`
- `pump-selector/sql/seed-reset-and-reload.sql`
- `pump-selector/sql/test-selector-query.sql`
- `pump-selector/sql/uninstall.sql`
- `pump-selector/sql/install.sql`, если `oc_pump_selector_product` уже существует и структура подтверждена
- старые local DB dumps
- local `_local/` files
- любые `framework-standardization` файлы
- customer/order/session/user/cart/api таблицы
- полную старую локальную БД

## Backup перед apply

Перед любыми изменениями на production создать backup.

Минимальный backup scope:

- `oc_product_attribute`
- `oc_product_to_category`
- `oc_pump_selector_product`

Рекомендуемый backup scope:

- полный production database backup.

Apply нельзя начинать, если backup не создан.

Decision при отсутствии backup:

- `BLOCKED_BY_BACKUP`

## Pre-apply проверки

Перед apply подтвердить:

- открыт правильный production site;
- открыта правильная production DB;
- DB prefix подтверждён;
- production backup создан;
- runtime package содержит только approved files;
- SQL files взяты из актуального Git `main`;
- `sumoto_diameter_attribute_98.sql` содержит `REPLACE INTO`, а не `UPDATE`;
- `oc_pump_selector_product` существует;
- rebuild route/token известен;
- catalog fix для `4260` approved или postponed;
- deploy operator понимает rollback point.

## Порядок apply

1. Создать production backup.
2. Проверить backup.
3. Задеплоить approved runtime files `pump-selector`, если они ещё не на production.
4. Применить `sumoto_head_attribute_98.sql`.
5. Применить `sumoto_flow_attribute_98.sql`.
6. Применить `sumoto_voltage_attribute_98.sql`.
7. Применить `sumoto_diameter_attribute_98.sql`.
8. Применить catalog fix для `product_id = 4260`, если approved.
9. Выполнить cache rebuild.
10. Проверить JSON response cache rebuild.
11. Выполнить post-apply SQL verification.
12. Выполнить smoke tests.
13. Зафиксировать итоговое решение.

## Cache rebuild

Использовать catalog route:

    /index.php?route=extension/module/pump_selector/rebuildCache&token=<token_from_getRebuildToken>

Ожидаемый JSON response:

    {
      "total_scanned": 123,
      "eligible_inserted": 45
    }

Проверить:

- HTTP response is 200;
- response is valid JSON;
- нет `exception_message`;
- `total_scanned > 0`;
- `eligible_inserted > 0`.

Не использовать:

- `ModelExtensionModulePumpSelector::rebuildSelectorProducts()`

Причина:

- это TODO / zero-result placeholder;
- реальный rebuild выполняет `ModelExtensionModulePumpSelectorCacheBuilder::rebuild()` через controller method `rebuildCache()`.

## Post-apply SQL verification

После SQL apply проверить:

- есть 98 rows для `attribute_id = 12`;
- есть 98 rows для `attribute_id = 13`;
- есть 98 rows для `attribute_id = 15`;
- есть 98 rows для `attribute_id = 44`;
- все rows используют `language_id = 1`;
- все 98 product candidates остаются `manufacturer_id = 58 / Sumoto`;
- если catalog fix applied, `product_id = 4260` имеет category links:
  - `11900213`
  - `11900321`;
- после cache rebuild SUMOTO rows есть в `oc_pump_selector_product`;
- SUMOTO rows имеют `brand_priority = 8`.

## Smoke tests

После apply и cache rebuild проверить:

- страница подборщика открывается без PHP errors/warnings;
- rebuild endpoint возвращает JSON без `exception_message`;
- SUMOTO products есть в selector cache;
- SUMOTO products имеют `brand_priority = 8`;
- inactive product `1821` не отображается как active selectable item, если status filtering работает;
- product `4260` отображается в ожидаемых категориях, если catalog fix applied;
- подборщик возвращает рекомендации для `220V`;
- подборщик возвращает рекомендации для `380V`;
- сценарий с известным диаметром обсадной трубы работает;
- сценарий с неизвестным диаметром обсадной трубы работает;
- product cards показывают цену;
- product cards показывают остаток;
- product cards показывают производителя;
- product cards ведут на product page;
- Premium result может включать SUMOTO при подходящих условиях;
- customer/order/session/user/cart/api таблицы не менялись.

## Rollback notes

Если SQL apply прошёл с ошибкой или smoke tests failed:

- не применять дополнительные SQL;
- не продолжать deploy;
- сохранить error output;
- использовать production backup для rollback decision;
- не пытаться чинить production вручную без отдельного review.

Если runtime deploy вызвал ошибку:

- вернуть предыдущие runtime files из backup/deploy copy;
- не применять SQL, если SQL ещё не применялся;
- если SQL уже применялся, сначала оценить состояние cache и selector.

## Итоговые решения после apply

После выполнения выбрать одно:

- `PRODUCTION_APPLY_COMPLETED`
- `PRODUCTION_APPLY_COMPLETED_WITH_NOTES`
- `ROLLBACK_REQUIRED`
- `APPLY_ABORTED_BEFORE_SQL`
- `APPLY_ABORTED_AFTER_SQL`
- `SMOKE_TEST_FAILED`

## Что зафиксировать после apply

После production apply зафиксировать:

- дата и время apply;
- кто выполнял apply;
- backup location;
- какие runtime files deployed;
- какие SQL files applied;
- был ли applied catalog fix для `4260`;
- cache rebuild JSON response;
- post-apply verification result;
- smoke test result;
- final decision.

## Production apply result

Status: `PRODUCTION_APPLY_COMPLETED_WITH_NOTES`

Applied:

- 4 normalized SUMOTO SQL files from commit `ba19005`;
- cache rebuild completed;
- rebuild result: `total_scanned = 356`, `eligible_inserted = 187`;
- SUMOTO rows added to `oc_pump_selector_product`;
- SUMOTO `brand_priority = 8`;
- frontend smoke test passed.

Additional production note:

- temporary cache hotfix applied for Belamos/Pedrollo `max_flow_l_min`;
- reason: production rebuild restored old flow values in `m³/h` scale;
- after hotfix, mixed brand output restored: Belamos, Pedrollo, SUMOTO;
- do not run cache rebuild again until permanent flow normalization is fixed.

Final decision:

- `PRODUCTION_APPLY_COMPLETED_WITH_NOTES`