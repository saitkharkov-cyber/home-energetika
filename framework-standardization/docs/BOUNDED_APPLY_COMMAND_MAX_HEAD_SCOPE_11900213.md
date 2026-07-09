# Spec — bounded apply command для максимального напора, scope 11900213

Дата: 2026-07-09

## 1. Контекст

Целевая характеристика:

`максимальный напор`

Category scope:

`11900213`

Canonical attribute:

`12 — Максимальный напор`

Included aliases:

- `101 — Максимальный напор, м.вод.ст.`
- `119 — Максимальный напор, м`
- `81 — Max напор, м`

Canonical unit:

`m`

Текущее состояние:

- `481` review-approved simple proposals;
- `14` unresolved values excluded;
- expected UPDATE count: `400`;
- expected INSERT count: `81`;
- source alias rows must be preserved: `81`;
- SQL apply not yet performed;
- production/cache forbidden.

Этот документ фиксирует spec будущей bounded apply command. Он не реализует команду.

## 2. Proposed command

Будущий command path:

`framework-standardization/bin/db-controlled-apply-max-head.php`

Команда должна быть controlled local/staging apply command.

Она не является:

- production command;
- cache rebuild command;
- auto-merge command;
- auto-canonical selection command.

## 3. Назначение команды

Будущая команда должна применить только review-approved изменения для `максимальный напор` в controlled local/staging-like DB.

Команда должна:

- использовать уже подтверждённый category scope `11900213`;
- использовать canonical attribute `12`;
- использовать included aliases `12,101,119,81` только как источник review-approved proposals;
- обновлять только canonical rows;
- вставлять только missing canonical rows;
- сохранять source alias rows;
- исключать unresolved values;
- по умолчанию работать как dry-run/preview.

Команда не должна сама искать, выбирать или объединять attributes.

## 4. Параметры будущей команды

Ожидаемые параметры:

- runtime config path;
- `--category-id=11900213`;
- `--canonical-attribute-id=12`;
- `--attribute-ids=12,101,119,81`;
- `--canonical-unit=m`;
- dry-run default behavior;
- `--confirm-apply` как mandatory explicit flag для реальных изменений.

## 5. Dry-run по умолчанию

Без `--confirm-apply` команда не должна менять DB.

Dry-run mode должен:

- напечатать summary / preview;
- показать expected UPDATE/INSERT counts;
- показать unresolved exclusions;
- показать safety markers;
- завершиться с `product_data_changed=0`;
- завершиться с `sql_applied=0`.

Dry-run является поведением по умолчанию.

## 6. Обязательный `--confirm-apply`

Реальные `UPDATE` / `INSERT` разрешены только при наличии `--confirm-apply`.

Даже с `--confirm-apply` команда разрешена только для local dump / staging-like controlled DB.

Production runtime must be blocked.

`--confirm-apply` не должен:

- разрешать production/cache changes;
- разрешать cache rebuild;
- разрешать auto-merge;
- разрешать unresolved values;
- разрешать source alias row modification.

## 7. Safety checks до применения

Перед любым apply команда должна проверить:

- runtime is not production;
- backup/dump snapshot requirement is acknowledged;
- category_scope matches `11900213`;
- canonical_attribute_id matches `12`;
- canonical_unit matches `m`;
- only review-approved simple proposals are eligible;
- unresolved values excluded;
- no schema blockers;
- no conflicts;
- target table is `oc_product_attribute`;
- relevant columns are `product_id`, `attribute_id`, `language_id`, `text`.

Если любой check не проходит, команда должна завершиться без изменений.

## 8. Allowed UPDATE/INSERT boundaries

Разрешённые будущие операции:

- UPDATE only `oc_product_attribute.text`;
- UPDATE only existing canonical rows with `attribute_id=12`;
- INSERT only missing canonical rows with `attribute_id=12`;
- only concrete `product_id`;
- only concrete `language_id`;
- only category_scope `11900213`;
- only normalized decimal meter values;
- expected UPDATE group = `400`;
- expected INSERT group = `81`.

Source alias rows must be preserved.

## 9. Forbidden operations

Будущая command не должна выполнять:

- DELETE;
- ALTER;
- DROP;
- TRUNCATE;
- CREATE TABLE;
- REPLACE;
- wide UPDATE without `product_id`;
- UPDATE/INSERT outside category_scope `11900213`;
- UPDATE/INSERT unresolved values;
- source alias rows modification;
- attribute merge;
- canonical auto-selection;
- production/cache changes;
- cache rebuild;
- rollback auto-apply;
- SQL files/diff creation unless explicitly approved by a later gate.

Также запрещены любые изменения `config/jobs`, pipeline/runners и production/cache.

## 10. Expected summary

Команда должна печатать summary с полями:

- `dry_run`;
- `confirm_apply`;
- `runtime_mode`;
- `category_scope`;
- `canonical_attribute_id`;
- `update_existing_canonical_row_count`;
- `insert_missing_canonical_row_count`;
- `keep_existing_source_row_count`;
- `unresolved_excluded_count`;
- `schema_blocker_count`;
- `conflicts_count`;
- `sql_applied`;
- `product_data_changed`;
- `production_ready`;
- `cache_rebuild_performed`.

Для dry-run expected:

- `dry_run = 1`;
- `confirm_apply = 0`;
- `sql_applied = 0`;
- `product_data_changed = 0`;
- `production_ready = 0`;
- `cache_rebuild_performed = 0`.

## 11. Post-apply verification gate

Post-apply verification должен быть отдельным gate после apply.

Он не является частью implementation этого spec.

Минимальная verification должна подтвердить:

- updated count = `400`;
- inserted count = `81`;
- affected rows only `attribute_id=12`;
- affected products only category_scope `11900213`;
- unresolved not applied;
- source alias rows preserved;
- no conflicts/duplicates;
- product data changed only expected rows;
- no production/cache touched;
- no cache rebuild.

## 12. Explicit boundary

Этот документ:

- does not implement command;
- does not create PHP files;
- does not execute SQL;
- does not create SQL files;
- does not create apply plan;
- does not touch production/cache;
- does not rebuild cache;
- does not change product data.

Этот документ является documentation/spec gate только для будущей bounded apply command.
