# SUMOTO Runtime Pre-Apply Review

## Назначение

Этот документ фиксирует runtime-review для SUMOTO перед production apply.

Документ покрывает:

- runtime-файлы `pump-selector`;
- файлы, исключённые из deploy;
- способ пересборки cache;
- runtime smoke tests;
- блокеры и обязательные условия перед production apply.

Этот документ сам по себе не разрешает production apply.

## Текущий статус

Runtime review decision: `PASS_WITH_CONDITIONS`

SQL package decision: `READY_FOR_REVIEWED_MIGRATION`

Runtime blocker: `NONE_FOUND`

Production apply всё ещё заблокирован, пока не выполнены все обязательные pre-apply условия.

## Runtime-файлы для deploy

Деплоить только эти runtime-файлы `pump-selector`:

- `pump-selector/module/catalog/controller/extension/module/pump_selector.php`
- `pump-selector/module/catalog/model/extension/module/pump_selector.php`
- `pump-selector/module/catalog/model/extension/module/pump_selector_cache_builder.php`
- `pump-selector/module/catalog/view/theme/revolution/template/extension/module/pump_selector.tpl`
- `pump-selector/module/catalog/view/theme/revolution/image/home-water.webp`
- `pump-selector/module/catalog/view/theme/revolution/image/plig-ins.webp`
- `pump-selector/module/catalog/view/theme/revolution/image/pump.webp`

## Runtime-поведение, важное для SUMOTO

`pump_selector_cache_builder.php` поддерживает SUMOTO через matching производителя:

- manufacturer name matching `Sumoto*` получает `brand_priority = 8`.

`pump_selector.php` использует `brand_priority >= 8` для Premium-логики.

Ожидаемый результат после deploy, SQL apply и cache rebuild:

- SUMOTO products могут участвовать как Premium candidates, если условия подбора совпадают.

## Файлы, которые не деплоить

Не деплоить:

- `pump-selector/module/catalog/controller/extension/module/pump_selector_test.php`
- `pump-selector/sql/seed-from-attributes.sql`
- `pump-selector/sql/seed-reset-and-reload.sql`
- `pump-selector/sql/test-selector-query.sql`
- `pump-selector/sql/uninstall.sql`
- старые local DB dumps
- local `_local/` files
- любые `framework-standardization` файлы
- файлы старой локальной БД с SUMOTO data

Причина исключения `pump_selector_test.php`:

- это temporary diagnostic controller;
- это не production runtime.

## Решение по install.sql

`pump-selector/sql/install.sql` не нужен для текущего SUMOTO apply, если на production уже есть `oc_pump_selector_product` с подтверждённой структурой.

Fresh production dump verification подтвердил, что `oc_pump_selector_product` существует и содержит ожидаемые cache-поля:

- `product_id`
- `max_head_m`
- `max_flow_l_min`
- `pump_diameter_mm`
- `voltage`
- `brand_priority`
- `product_price`
- `quantity`
- `status`

Использовать `install.sql` только если production-таблица отсутствует или структура не проходит review.

Текущее решение:

- `install.sql` не входит в SUMOTO production apply package.

## Безопасный порядок runtime deploy и apply

1. Подтвердить наличие production backup.
2. Подтвердить, что runtime deploy package не содержит diagnostic/test files.
3. Задеплоить только approved runtime-файлы `pump-selector`.
4. Применить reviewed SUMOTO SQL files:
   - `catalog-standardization/sql/generated/sumoto/sumoto_head_attribute_98.sql`
   - `catalog-standardization/sql/generated/sumoto/sumoto_flow_attribute_98.sql`
   - `catalog-standardization/sql/generated/sumoto/sumoto_voltage_attribute_98.sql`
   - `catalog-standardization/sql/generated/sumoto/sumoto_diameter_attribute_98.sql`
5. Применить catalog fix для `product_id = 4260` только если он отдельно approved.
6. Выполнить cache rebuild.
7. Проверить JSON-результат cache rebuild.
8. Выполнить smoke tests.

## Способ cache rebuild

В коде есть catalog route для cache rebuild:

- controller: `pump-selector/module/catalog/controller/extension/module/pump_selector.php`
- method: `rebuildCache()`
- route: `extension/module/pump_selector/rebuildCache`
- protection: query parameter `token`
- token source: private method `getRebuildToken()`

Ожидаемая форма catalog URL:

    /index.php?route=extension/module/pump_selector/rebuildCache&token=<token_from_getRebuildToken>

Ожидаемая форма успешного JSON response:

    {
      "total_scanned": 123,
      "eligible_inserted": 45
    }

Важно:

- не использовать `ModelExtensionModulePumpSelector::rebuildSelectorProducts()` как rebuild method;
- в текущем runtime это TODO / zero-result placeholder;
- реальный rebuild реализован в `ModelExtensionModulePumpSelectorCacheBuilder::rebuild()`;
- `ControllerExtensionModulePumpSelector::rebuildCache()` вызывает реальный cache builder.

## Проверки cache rebuild

После вызова rebuild route проверить:

- HTTP response is 200;
- response is valid JSON;
- response has no `exception_message`;
- `total_scanned` is greater than 0;
- `eligible_inserted` is greater than 0;
- `oc_pump_selector_product` is repopulated;
- SUMOTO rows are present;
- SUMOTO rows have `brand_priority = 8`.

## Smoke tests после deploy

Выполнить после runtime deploy, SQL apply и cache rebuild.

Проверить:

- rebuild endpoint returns HTTP 200;
- rebuild endpoint returns JSON without `exception_message`;
- `eligible_inserted > 0`;
- SUMOTO products are present in `oc_pump_selector_product`;
- SUMOTO products have `brand_priority = 8`;
- inactive product `1821` does not appear as an active selectable item if status filtering is respected;
- product `4260` appears in expected parent categories if catalog fix was applied;
- selector returns recommendations for `220V`;
- selector returns recommendations for `380V`;
- selector works when casing diameter is known;
- selector works when casing diameter is unknown;
- product cards show current price;
- product cards show current quantity;
- product cards show manufacturer;
- product cards link to product page;
- Premium result can include SUMOTO when conditions match;
- no PHP errors or warnings appear on selector page;
- no customer/order/session/user/cart/api tables were changed.

## Обязательные условия перед production apply

Production apply разрешён только если выполнены все условия:

- production backup exists;
- 4 SUMOTO SQL files are finally reviewed;
- runtime deploy package is reviewed;
- runtime deploy package excludes `pump_selector_test.php`;
- catalog fix for `product_id = 4260` is separately approved or explicitly postponed;
- cache rebuild route and token are available to deploy operator;
- `framework-standardization` files are not included in this deploy;
- old local SUMOTO DB is not used as source of truth.

## Блокеры

Runtime blocker: `NONE_FOUND`

Remaining blockers before production apply:

- `BLOCKED_BY_BACKUP` until production backup is created;
- `BLOCKED_BY_FINAL_APPROVAL` until SQL package, runtime package, and optional catalog fix are approved for production apply.

## Финальное runtime-решение

Decision: `PASS_WITH_CONDITIONS`

Meaning:

- runtime package is acceptable for deploy review;
- no runtime blocker was found in reviewed files;
- production apply still requires backup, final SQL review, runtime package review, cache rebuild readiness, and smoke tests.