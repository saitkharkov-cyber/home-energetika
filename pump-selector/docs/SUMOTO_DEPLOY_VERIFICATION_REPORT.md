# SUMOTO Deploy Verification Report

## Статус

Status: `NEEDS_MORE_INFO`

SUMOTO нельзя деплоить сейчас как готовую production migration.

Причина: в Git есть код, dataset и generated SQL-кандидаты, но нет подтверждения на свежем production dump, что реальные `product_id`, `attribute_id`, `language_id`, структура `oc_pump_selector_product` и данные каталога совпадают с ожиданиями.

Старая локальная БД с SUMOTO не считается источником правды.

## Цель документа

Зафиксировать текущий статус SUMOTO deploy и определить, какие read-only проверки нужно выполнить на fresh production dump перед любым deploy, SQL migration или переходом к `framework-standardization` DB-readonly работе.

Этот документ не является production migration script.

## Что уже есть в Git по SUMOTO

- SUMOTO поддержан в `PumpSelectorCacheBuilder`.
- Производитель `Sumoto*` получает `brand_priority = 8`.
- `brand_priority = 8` позволяет SUMOTO участвовать в роли Premium.
- Есть SUMOTO dataset в `catalog-standardization/data/sumoto/...`.
- Есть generated SQL для 98 SUMOTO товаров.
- Есть документация `pump-selector`, где зафиксирован контракт атрибутов `12`, `13`, `15`, `44`.

## Чего не хватает

- Не найден единый production migration script.
- Не найден rollback plan.
- Не подтверждено, что все 98 `product_id` существуют и совпадают на production.
- Не подтверждено, что `language_id = 1` верен для production.
- Не подтверждено, что все нужные строки `oc_product_attribute` уже существуют для `UPDATE`.
- Не проверены 13 строк со статусом `needs_check`.
- Не подтверждена структура production-таблицы `oc_pump_selector_product`.

## Файлы-кандидаты на deploy

Runtime-файлы pump-selector:

- `pump-selector/module/catalog/controller/extension/module/pump_selector.php`
- `pump-selector/module/catalog/model/extension/module/pump_selector.php`
- `pump-selector/module/catalog/model/extension/module/pump_selector_cache_builder.php`
- `pump-selector/module/catalog/view/theme/revolution/template/extension/module/pump_selector.tpl`
- `pump-selector/module/catalog/view/theme/revolution/image/home-water.webp`
- `pump-selector/module/catalog/view/theme/revolution/image/plig-ins.webp`
- `pump-selector/module/catalog/view/theme/revolution/image/pump.webp`

SQL install candidate:

- `pump-selector/sql/install.sql`

`pump-selector/sql/install.sql` рассматривать только если на production ещё нет корректной таблицы `oc_pump_selector_product`.

## Файлы, которые нельзя деплоить без отдельного решения

- `pump-selector/module/catalog/controller/extension/module/pump_selector_test.php`
- `pump-selector/sql/seed-from-attributes.sql`
- старые local DB dumps целиком
- любые `framework-standardization` файлы как часть SUMOTO deploy

Причины:

- `pump_selector_test.php` выглядит как temporary diagnostic controller;
- `seed-from-attributes.sql` выглядит устаревшим относительно текущего cache contract;
- старая локальная БД не является источником правды;
- `framework-standardization` не должен смешиваться с SUMOTO deploy.

## SQL-файлы-кандидаты

Generated SQL candidates:

- `catalog-standardization/sql/generated/sumoto/sumoto_head_attribute_98.sql`
- `catalog-standardization/sql/generated/sumoto/sumoto_flow_attribute_98.sql`
- `catalog-standardization/sql/generated/sumoto/sumoto_voltage_attribute_98.sql`
- `catalog-standardization/sql/generated/sumoto/sumoto_diameter_attribute_98.sql`

## Почему generated SQL ещё не production migration

Эти SQL-файлы нельзя применять на production как есть, пока не выполнена проверка на fresh production dump.

Причины:

- это generated DML, а не проверенная migration;
- нет pre-check queries;
- нет rollback plan;
- нет подтверждения production `product_id`;
- нет подтверждения production `attribute_id`;
- нет подтверждения production `language_id`;
- `diameter` SQL может использовать `UPDATE`, а значит не вставит отсутствующие строки;
- 13 строк в исходных данных имеют статус `needs_check`.

## Fresh production dump verification checklist

Перед deploy нужно выполнить read-only проверку на fresh production dump.

Проверить:

- наличие всех 98 `product_id`;
- что эти `product_id` соответствуют именно SUMOTO товарам;
- что manufacturer name начинается с `Sumoto`;
- category membership для категорий:
  - `11900308`
  - `11900309`
  - `11900321`
- наличие и структуру `oc_pump_selector_product`;
- текущие значения в `oc_product_attribute`;
- отсутствие конфликтов с существующими значениями;
- соответствие `attribute_id` контракту;
- соответствие `language_id` рабочему языку production;
- наличие строк, которые generated SQL предполагает обновлять через `UPDATE`.

## Проверки по 98 product_id

Для каждого SUMOTO `product_id` проверить:

- товар существует в `oc_product`;
- товар связан с нужной карточкой товара;
- товар является SUMOTO;
- товар активен или понятно, почему он неактивен;
- цена актуальна;
- остаток актуален;
- производитель корректный;
- нет несовпадения ID между старой локальной БД и production.

Если хотя бы часть `product_id` не совпадает с production, generated SQL нельзя применять как есть.

## Проверки по attribute_id

Проверить, что на production действуют именно эти значения:

- `12` = максимальный напор;
- `13` = максимальная производительность;
- `15` = напряжение;
- `44` = диаметр насоса.

Эти ID должны быть подтверждены именно на production.

Также проверить, что значения находятся в формате, который читает `PumpSelectorCacheBuilder`: без лишних единиц измерения там, где builder приводит значения к числам.

## Проверки по language_id

Проверить:

- что `language_id = 1` соответствует рабочему языку production;
- что существующие строки `oc_product_attribute` для этих товаров используют тот же `language_id`;
- что generated SQL не пишет данные в нерабочий язык.

Если production использует другой `language_id`, SQL-кандидаты нельзя применять как есть.

## Проверки по oc_pump_selector_product

Проверить:

- таблица существует с production-префиксом;
- ожидаемое имя: `oc_pump_selector_product`, если production-префикс действительно `oc_`;
- структура соответствует `pump-selector/sql/install.sql`;
- есть поля:
  - `brand_priority`
  - `product_price`
  - `quantity`
  - `status`

Важно:

- `oc_pump_selector_product` не является source of truth;
- cache нужно полностью пересобрать после изменения атрибутов;
- переносить `oc_pump_selector_product` из старой локальной БД нельзя.

## Что делать с diameter SQL

Если `sumoto_diameter_attribute_98.sql` использует `UPDATE`, сначала нужно проверить наличие строк `oc_product_attribute` для всех 98 товаров:

- нужный `product_id`;
- `attribute_id = 44`;
- production `language_id`.

Если строки есть для всех 98 товаров, `UPDATE` можно рассматривать после review.

Если строк нет хотя бы у части товаров, нужен reviewed migration variant, который вставляет отсутствующие строки, например через согласованный безопасный `INSERT ... ON DUPLICATE KEY UPDATE` или другой утверждённый подход с учётом реальных ключей production-таблицы.

## Что делать с 13 строками needs_check

13 строк со статусом `needs_check` нельзя считать автоматически готовыми к production.

Для них нужно отдельно сверить:

- источник;
- модель;
- максимальный напор;
- максимальную производительность;
- напряжение;
- диаметр.

До подтверждения эти строки должны либо быть исключены из migration, либо весь deploy должен оставаться blocked.

## Минимальный безопасный порядок

1. Получить fresh production dump.
2. Выполнить read-only verification report по SUMOTO.
3. Подтвердить 98 `product_id`.
4. Подтвердить `attribute_id` `12`, `13`, `15`, `44`.
5. Подтвердить production `language_id`.
6. Проверить manufacturer/category membership.
7. Проверить структуру `oc_pump_selector_product`.
8. Проверить существующие строки `oc_product_attribute`.
9. Разобрать 13 строк `needs_check`.
10. Подготовить reviewed migration на основе SQL-кандидатов.
11. Подготовить rollback plan.
12. Сделать backup перед production-изменениями.
13. Задеплоить runtime files pump-selector.
14. Применить reviewed SQL.
15. Полностью пересобрать cache через `PumpSelectorCacheBuilder`.
16. Провести smoke tests.

## Что категорически нельзя делать

- Нельзя копировать старую локальную БД целиком.
- Нельзя переносить `oc_pump_selector_product` из локальной БД.
- Нельзя применять generated SQL на production без проверки fresh production dump.
- Нельзя выполнять SQL без backup и review.
- Нельзя деплоить diagnostic controller без отдельного решения.
- Нельзя смешивать SUMOTO deploy с `framework-standardization`.
- Нельзя строить `framework-standardization` DB-readonly выводы на старой локальной БД с SUMOTO.
- Нельзя считать documentation reference runtime deploy-файлами.

## Решение на текущий момент

Decision: `BLOCK_DEPLOY`

SUMOTO deploy остаётся заблокированным до read-only verification report на fresh production dump.

## Следующий шаг

Сделать read-only verification report на fresh production dump:

- 98 `product_id`;
- `attribute_id` `12`, `13`, `15`, `44`;
- production `language_id`;
- manufacturer;
- category membership;
- структура `oc_pump_selector_product`;
- наличие строк `oc_product_attribute`;
- 13 строк `needs_check`.

После этого можно будет принять одно из решений:

- `READY_FOR_REVIEWED_MIGRATION`
- `NEEDS_SQL_REBUILD`
- `BLOCKED_BY_DATA_MISMATCH`
- `BLOCKED_BY_NEEDS_CHECK`

## Fresh production dump verification findings

### Verified facts

- Fresh production dump imported into local DB `he_prod_fresh_sumoto_check`.
- `language_id = 1` confirmed as `Russian / ru-ru / status = 1`.
- `oc_pump_selector_product` exists.
- `oc_pump_selector_product` contains expected cache fields:
  - `product_id`
  - `max_head_m`
  - `max_flow_l_min`
  - `pump_diameter_mm`
  - `voltage`
  - `brand_priority`
  - `product_price`
  - `quantity`
  - `status`
- Attribute contract confirmed:
  - `12` = `Максимальный напор`
  - `13` = `Максимальная производительность`
  - `15` = `Напряжение`
  - `44` = `Диаметр насоса`
- Manufacturer confirmed:
  - `manufacturer_id = 58`
  - `name = Sumoto`
- 98 generated SQL `product_id` candidates exist on fresh production dump.
- All 98 candidates have `manufacturer_id = 58 / Sumoto`.
- 97 of 98 candidates are active.
- 1 candidate is inactive:
  - `product_id = 1821`
  - `Погружной скважинный насос SUMOTO 3OPC2.5/10`
  - `status = 0`
- `product_id = 4260` is a valid active Sumoto pump and is included in generated SQL, but it is missing parent category links:
  - missing `11900213 = Скважинные насосы`
  - missing `11900321 = Насосы SUMOTO`
  - current category: `11900323 = Погружные 4-х дюймовые насосы Sumoto`
- This is treated as a catalog categorization issue, not as SQL candidate mismatch.

### Existing product_attribute state

For the 98 SUMOTO candidates, fresh production dump currently has only 3 `oc_product_attribute` rows total:

- `1812 / attribute_id 14 / Мощность двигателя / 1,5 kw`
- `1812 / attribute_id 45 / Материал / нержавеющая сталь`
- `1813 / attribute_id 50 / Тип насоса / погружной`

For required pump-selector attributes there are currently 0 rows:

- `attribute_id = 12`
- `attribute_id = 13`
- `attribute_id = 15`
- `attribute_id = 44`

### SQL candidate operation types

Generated SQL operation types:

- `sumoto_head_attribute_98.sql` = `REPLACE INTO` x98
- `sumoto_flow_attribute_98.sql` = `REPLACE INTO` x98
- `sumoto_voltage_attribute_98.sql` = `REPLACE INTO` x98
- `sumoto_diameter_attribute_98.sql` = `UPDATE` x98

### Current decision

Decision: `NEEDS_SQL_REBUILD`

Reason:

`sumoto_diameter_attribute_98.sql` is not production-ready because it uses `UPDATE`, while fresh production dump has 0 existing rows for `attribute_id = 44` for the 98 SUMOTO candidates.

Applying this file as-is would update 0 rows for pump diameter.

### Required next fix

Rebuild `sumoto_diameter_attribute_98.sql` as reviewed insert/upsert migration, preferably consistent with the other SUMOTO SQL files.

Expected direction:

- replace `UPDATE` statements with reviewed `REPLACE INTO` / agreed upsert form;
- preserve `product_id`;
- use `attribute_id = 44`;
- use confirmed `language_id = 1`;
- preserve generated diameter values;
- review before production apply.

### Additional catalog fix candidate

Recommended data fix before or during SUMOTO deploy:

```sql
INSERT IGNORE INTO oc_product_to_category (product_id, category_id)
VALUES
  (4260, 11900213),
  (4260, 11900321);
```
This fix must be reviewed and applied only after backup.

## SQL rebuild update

`sumoto_diameter_attribute_98.sql` was rebuilt locally from `UPDATE` x98 to `REPLACE INTO` x98.

Current generated SQL operation types:

- `sumoto_head_attribute_98.sql` = `REPLACE INTO` x98
- `sumoto_flow_attribute_98.sql` = `REPLACE INTO` x98
- `sumoto_voltage_attribute_98.sql` = `REPLACE INTO` x98
- `sumoto_diameter_attribute_98.sql` = `REPLACE INTO` x98

Updated decision: `READY_FOR_REVIEWED_MIGRATION`

Remaining required review before production apply:

- confirm generated SQL file contents;
- apply only after production backup;
- apply additional catalog fix for `product_id = 4260` only after review;
- rebuild `oc_pump_selector_product` cache after SQL apply;
- perform smoke tests.

