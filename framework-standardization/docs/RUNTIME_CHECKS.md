# Runtime Checks

Документ хранит историю ручных запусков и проверок Framework Standardization.

Он фиксирует команды, краткие результаты и проверяемые режимы. Документ не заменяет `docs/HANDOFF.md`.

## DB readonly manual runner runtime-check

Контрольная точка:

```text
DB readonly manual runner runtime-check
```

Коммиты на момент проверки:

```text
c6c19d2 Update handoff after DB readonly manual runner
8d98d61 Add DB readonly manual runner
```

### Проверка 1: DB-readonly manual runner

Команда:

```text
C:\php56\php.exe -c C:\php56\php.ini framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Результат:

```text
runtime_mode: db_readonly
db_backed_stage: resolve_canonical
result_status: ok
warnings_count: 0
errors_count: 0
все 9 stages ok
```

### Проверка 2: обычный dry-run

Команда:

```text
C:\php56\php.exe -c C:\php56\php.ini framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Результат:

```text
result_status: ok
warnings_count: 0
errors_count: 0
все 9 stages ok
```

## 2026-07-06 — DbReadOnlyAttributeExporter standalone check

Commit:

`235a579 Add DB readonly attribute exporter`

Проверялась новая standalone capability:

`framework-standardization/src/Exporter/DbReadOnlyAttributeExporter.php`

Цель проверки:

- подтвердить, что `DbReadOnlyAttributeExporter` работает отдельно от pipeline wiring;
- подтвердить совместимость с real DB product IDs из local dump;
- подтвердить отсутствие регрессии default dry-run path;
- подтвердить, что DB-readonly runner пока не переводился на пару `resolve_scope` / `export_attributes`.

### Syntax check

Команда:

`C:\php56\php.exe -l framework-standardization\src\Exporter\DbReadOnlyAttributeExporter.php`

Результат:

`No syntax errors detected`

### Standalone check against local dump

Проверка выполнялась через временный manual-check файл.

Временный файл после проверки удалён.

Результат:

`exported: 1`

`source: local_dump_db_readonly`

`attributes_count: 72`

`attribute_groups_count: 6`

`product_attributes_count: 4908`

`raw_values_count: 385`

`target_attribute_id: 44`

`first_raw_product_id: 1068`

`first_raw_attribute_id: 44`

Вывод:

- exporter читает данные из local dump DB в read-only режиме;
- exporter работает с реальными `product_id`;
- fixture `product_id = 0` не используется;
- target attribute берётся из resolved canonical data;
- standalone capability готова к будущему paired wiring.

### Default dry-run regression check

Команда:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Результат:

`result_status: ok`

`warnings_count: 0`

`errors_count: 0`

`all 9 stages ok`

Вывод:

- default dry-run path не сломан;
- `bin/dry-run.php` остаётся no-DB;
- fixture path остаётся рабочим.

### Wiring status

Pipeline wiring не менялся.

`DbReadOnlyPipelineFactory` по-прежнему использует:

`DryRunScopeResolver`

`DryRunAttributeExporter`

`bin/db-readonly-run.php` по-прежнему сообщает:

`db_backed_stage: resolve_canonical`

Вывод:

- `DbReadOnlyAttributeExporter` создан только как standalone capability;
- `DbReadOnlyScopeResolver` не подключался в `DbReadOnlyPipelineFactory`;
- `DbReadOnlyAttributeExporter` не подключался в `DbReadOnlyPipelineFactory`;
- DB-backed stage в runner пока только `resolve_canonical`.

### Boundary

Этот шаг не является paired wiring.

Следующий инженерный шаг должен отдельно решать подключение пары:

`DbReadOnlyScopeResolver + DbReadOnlyAttributeExporter`

Подключать только один компонент пары нельзя.

## 2026-07-06 — DB-readonly scope/export paired wiring check

Commit:

`cb54135 Wire DB readonly scope export path`

Проверялось подключение DB-readonly scope/export path после paired wiring.

Фактическое изменение оказалось шире исходного paired wiring: кроме подключения `resolve_scope` и `export_attributes`, были добавлены DB-readonly-compatible adapters для downstream stages, потому что dry-run downstream components оказались fixture-only и не работали с real DB IDs.

### Изменённые файлы

`framework-standardization/src/Pipeline/DbReadOnlyPipelineFactory.php`

`framework-standardization/bin/db-readonly-run.php`

`framework-standardization/src/Analyzer/DbReadOnlyAttributeNameAnalyzer.php`

`framework-standardization/src/Analyzer/DbReadOnlyAttributeValueAnalyzer.php`

`framework-standardization/src/SqlPreview/DbReadOnlySqlPreviewBuilder.php`

### Pipeline status after wiring

DB-backed stages:

`resolve_canonical`

`resolve_scope`

`export_attributes`

DB-readonly-compatible stages:

`analyze_names`

`analyze_values`

`build_sql_preview`

Dry-run stages:

`build_report`

`build_framework_result`

### Runner status output

`bin/db-readonly-run.php` теперь выводит:

`runtime_mode: db_readonly`

`db_backed_stages: resolve_canonical, resolve_scope, export_attributes`

`db_readonly_compatible_stages: analyze_names, analyze_values, build_sql_preview`

`dry_run_stages: build_report, build_framework_result`

### Syntax checks

PHP syntax checks для изменённых и новых PHP-файлов выполнены через PHP 5.6.

Результат:

`ok`

### Default dry-run regression check

Команда:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Результат:

`result_status: ok`

`warnings_count: 0`

`errors_count: 0`

`all 9 stages ok`

Вывод:

- default dry-run path не сломан;
- `bin/dry-run.php` не менялся;
- `PipelineFactory::createDefault()` не менялся;
- default factory по-прежнему использует dry-run scope/export components.

### DB-readonly runner check

Команда:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Результат:

`result_status: ok`

`warnings_count: 0`

`errors_count: 0`

`all 9 stages ok`

Дополнительная временная проверка export facts:

`scope_product_count: 1972`

`first_scope_product_id: 1068`

`raw_values_count: 385`

`target_attribute_id: 44`

`first_raw_product_id: 1068`

`first_raw_attribute_id: 44`

`zero_product_ids: 0`

`zero_attribute_ids: 0`

Временный manual-check файл после проверки удалён.

### Safety result

Подтверждено:

- `DbReadOnlyScopeResolver` и `DbReadOnlyAttributeExporter` подключены только парой;
- запрещённое состояние `DbReadOnlyScopeResolver + DryRunAttributeExporter` не осталось;
- DB-readonly runner работает с real DB product IDs;
- fixture `product_id = 0` не используется в DB-readonly export facts;
- `attribute_id = 0` не используется в DB-readonly export facts;
- `DbReadOnlySqlPreviewBuilder` не генерирует executable SQL;
- SQL apply не выполнялся;
- live DB не использовалась;
- write/schema operations не использовались;
- OpenCart module paths не создавались.

### Boundary

Этот шаг расширил DB-readonly path до состояния:

`resolve_canonical  -> DB-backed`

`resolve_scope      -> DB-backed`

`export_attributes  -> DB-backed`

`analyze_names      -> DB-readonly-compatible`

`analyze_values     -> DB-readonly-compatible`

`build_sql_preview  -> DB-readonly-compatible`

`build_report       -> dry-run`

`build_framework_result -> dry-run`

`analyze_names`, `analyze_values` и `build_sql_preview` являются compatibility adapters, а не production normalization / SQL apply layer.

## 2026-07-06 — DB-readonly raw value profiling check

Commit:

`0a470df Add DB readonly raw value profiling`

Проверялось усиление `DbReadOnlyAttributeValueAnalyzer` как read-only raw value profiling stage.

Изменённый файл:

`framework-standardization/src/Analyzer/DbReadOnlyAttributeValueAnalyzer.php`

### Что добавлено

В `attribute_value_structure.diagnostics.raw_profile` добавлены read-only diagnostics:

`total_values`

`unique_raw_values_count`

`empty_values_count`

`top_raw_values`

`raw_value_frequencies`

`examples`

`min_raw_length`

`max_raw_length`

`avg_raw_length`

`contains_digits_count`

`contains_unit_mm_count`

`suspicious_no_digits_count`

`suspicious_long_value_count`

`suspicious_multiple_numbers_count`

`source`

Также в `value_report` добавлены:

`unique_raw_values_count`

`top_raw_values`

`profiling_note = db_readonly_raw_value_profiling_only`

Существующий marker сохранён:

`note = db_readonly_values_not_normalized`

### Boundary

Этот шаг является profiling, а не normalization.

Подтверждено:

- `raw_values` сохраняются;
- `normalized_values` остаётся пустым массивом;
- canonical numeric value не извлекается;
- unit conversion не выполняется;
- suspicious diagnostics не означают reject / approve;
- SQL-ready data не создаётся.

### Syntax check

Команда:

`C:\php56\php.exe -l framework-standardization\src\Analyzer\DbReadOnlyAttributeValueAnalyzer.php`

Результат:

`No syntax errors detected`

### Default dry-run regression check

Команда:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Результат:

`result_status: ok`

`warnings_count: 0`

`errors_count: 0`

`all 9 stages ok`

### DB-readonly runner check

Команда:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Результат:

`result_status: ok`

`warnings_count: 0`

`errors_count: 0`

`all 9 stages ok`

### Manual contract check

Результат:

`raw_values_count: 385`

`normalized_values_count: 0`

`raw_profile_present: yes`

`raw_profile_total_values: 385`

`unique_raw_values_count: 14`

`top_raw_values_count: 14`

`safe_to_apply: 0`

`sql_statement_count: 0`

`zero_product_ids: 0`

`zero_attribute_ids: 0`

Временный manual-check файл после проверки удалён.

### Safety result

Подтверждено:

- default dry-run path не менялся;
- pipeline wiring не менялся;
- runners не менялись;
- `DbReadOnlySqlPreviewBuilder` не менялся;
- SQL preview/apply не менялись;
- live DB не использовалась;
- executable SQL не добавлялся;
- write/schema operations не использовались;
- OpenCart module paths не создавались.

## 2026-07-06 — DB-readonly SQL preview raw profile diagnostics check

Commit:

`ecd9196 Add DB readonly SQL preview raw profile diagnostics`

Проверялось добавление read-only summary из raw value profiling в DB-readonly SQL preview diagnostics.

Изменённый компонент:

`framework-standardization/src/SqlPreview/DbReadOnlySqlPreviewBuilder.php`

### Что добавлено

`sql_preview.diagnostics` теперь содержит read-only raw profile summary:

`raw_profile_present`

`raw_profile_total_values`

`unique_raw_values_count`

`empty_values_count`

`suspicious_no_digits_count`

`suspicious_long_value_count`

`suspicious_multiple_numbers_count`

`top_raw_values_count`

`raw_profile_source`

Если upstream `raw_profile` отсутствует:

`raw_profile_present = 0`

### Boundary

Blocked preview сохранён:

`generated = 0`

`safe_to_apply = 0`

`apply_changes = 0`

`statements = array()`

`blocked_by` содержит:

`db_readonly_sql_preview_not_implemented`

SQL generation и apply plan не появились.

Raw profile summary остаётся diagnostics-only и не означает:

- reject;
- approve;
- SQL-ready normalized data;
- safe-to-apply decision.

### Syntax check

Команда:

`C:\php56\php.exe -l framework-standardization\src\SqlPreview\DbReadOnlySqlPreviewBuilder.php`

Результат:

`No syntax errors detected`

### Default dry-run regression check

Команда:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Результат:

`result_status: ok`

`warnings_count: 0`

`errors_count: 0`

`all 9 stages ok`

### DB-readonly runner check

Команда:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Результат:

`result_status: ok`

`warnings_count: 0`

`errors_count: 0`

`all 9 stages ok`

### Safety result

Подтверждено:

- default dry-run path не менялся;
- pipeline wiring не менялся;
- runners не менялись;
- SQL apply не выполнялся;
- executable SQL не добавлялся;
- apply plan не создавался;
- live DB не использовалась;
- write/schema operations не использовались;
- OpenCart module paths не создавались.
