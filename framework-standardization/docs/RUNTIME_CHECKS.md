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

## 2026-07-06 — DB-readonly diagnostics output in build_report check

Commit:

`50daba1 Add DB readonly diagnostics to report output`

Проверялось добавление DB-readonly diagnostics output в `build_report`.

Изменённый компонент:

`framework-standardization/src/Report/DryRunReportBuilder.php`

### Что добавлено

Report output теперь содержит `raw_profile_summary`:

`total_values`

`unique_raw_values_count`

`empty_values_count`

`suspicious_no_digits_count`

`suspicious_long_value_count`

`suspicious_multiple_numbers_count`

`top_raw_values_count`

`source`

Report output также содержит `sql_preview_safety_summary`:

`generated`

`safe_to_apply`

`apply_changes`

`statement_count`

`blocked_by`

marker, что `blocked_by` содержит:

`db_readonly_sql_preview_not_implemented`

Также в summary включён raw profile diagnostics summary из SQL preview.

### Boundary

`build_report` остаётся reporting-only.

Подтверждено:

- report builder только читает готовые diagnostics;
- `sql_preview` не меняется;
- `safe_to_apply` не меняется;
- `statements` не меняются;
- normalization не выполняется;
- SQL не создаётся;
- apply plan не создаётся.

### Syntax check

Команда:

`C:\php56\php.exe -l framework-standardization\src\Report\DryRunReportBuilder.php`

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

### Output contract check

Результат:

`raw_profile_summary_present: 1`

`raw_profile_total_values: 385`

`unique_raw_values_count: 14`

`sql_preview_safety_summary_present: 1`

`generated: 0`

`safe_to_apply: 0`

`statement_count: 0`

`blocked_by_expected: 1`

### Safety result

Подтверждено:

- default dry-run path не менялся;
- pipeline wiring не менялся;
- runners не менялись;
- `HANDOFF.md` не менялся;
- `DECISIONS.md` не менялся;
- SQL apply не выполнялся;
- executable SQL не добавлялся;
- apply plan не создавался;
- live DB не использовалась;
- write/schema operations не использовались;
- OpenCart module paths не создавались.

## 2026-07-06 — DB-readonly diagnostics summary in build_framework_result check

### Context

Implementation commit:

`ff06d47 Add DB readonly diagnostics to framework result`

Changed component:

`framework-standardization/src/Result/DryRunFrameworkResultBuilder.php`

This check covers DB-readonly top-level diagnostics/safety summary in `build_framework_result`.

### What was added

`DryRunFrameworkResultBuilder` now adds top-level summary blocks to the framework result payload:

- `diagnostics_summary`
- `safety_summary`

`diagnostics_summary` contains read-only diagnostic markers derived from already prepared report/sql_preview data:

- `raw_profile_present`
- `raw_profile_total_values`
- `unique_raw_values_count`
- `suspicious_no_digits_count`
- `suspicious_long_value_count`
- `suspicious_multiple_numbers_count`
- `report_has_raw_profile_summary`
- `report_has_sql_preview_safety_summary`
- `sql_preview_safe_to_apply`
- `sql_preview_statement_count`
- `blocked_preview_expected`

`safety_summary` contains explicit non-apply markers:

- `generated = 0`
- `safe_to_apply`
- `statements_count`
- `sql_apply_allowed = 0`
- `production_ready = 0`

### Boundary

`build_framework_result` remains dry-run / result-packaging only.

It only packages already prepared `report` and `sql_preview` diagnostics into top-level summary fields.

It does not:

- change `report`;
- change `sql_preview`;
- change `safe_to_apply`;
- change `statements`;
- perform normalization;
- create executable SQL;
- create SQL files;
- create apply plan;
- perform SQL apply;
- change pipeline wiring;
- change runners;
- change default dry-run path.

### Syntax check

Command:

`C:\php56\php.exe -l framework-standardization\src\Result\DryRunFrameworkResultBuilder.php`

Result:

`No syntax errors detected`

### Default dry-run check

Command:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner check

Command:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Output contract check

DB-readonly framework result contract:

- `diagnostics_summary_present: 1`
- `safety_summary_present: 1`
- `production_ready: 0`
- `sql_apply_allowed: 0`
- `safe_to_apply: 0`
- `statements_count: 0`
- `report_has_raw_profile_summary: 1`
- `report_has_sql_preview_safety_summary: 1`

### Safety result

The implementation keeps the framework result as a dry-run/result-packaging output.

Diagnostics are visible at the top level, but they are not production decisions and are not apply-ready data.

SQL generation and SQL apply remain blocked.

## 2026-07-06 — DB-readonly normalization proposal parser standalone check

### Context

Implementation commit:

`bd06b9c Add DB readonly normalization proposal parser`

Created component:

`framework-standardization/src/Normalizer/DbReadOnlyNormalizationProposalParser.php`

This check covers standalone normalization proposal parser skeleton.

The parser is not connected to pipeline wiring.

### What was added

`DbReadOnlyNormalizationProposalParser` implements standalone method:

`parse($rawValues)`

Input is an array of read-only raw values with fields such as:

- `product_id`
- `attribute_id`
- `language_id`
- `target_attribute_id`
- `raw_text` or `value`

Output contains:

- `normalization_value_proposals`
- `parser_diagnostics`
- `errors`
- `warnings`
- `source = local_dump_db_readonly`

### Proposal output

Each proposal contains parser/proposal fields such as:

- `proposal_id` or deterministic key
- `product_id`
- `attribute_id`
- `language_id`
- `target_attribute_id`
- `original_raw_value`
- `parsed_value`
- `proposed_normalized_value`
- `proposed_unit`
- `parser_confidence`
- `parser_warnings`
- `approval_status`
- `source`

### Status boundary

Allowed future statuses:

- `proposed`
- `needs_review`
- `unknown`
- `rejected`
- `approved`

Current standalone skeleton can emit only:

- `proposed`
- `needs_review`
- `unknown`

The parser must not emit:

- `approved`
- `rejected`

### Safe parsing rules checked

The standalone check covered raw values similar to:

- empty value
- `75`
- `75 мм`
- `75.5 mm`
- `75,5 мм`
- `75-90 мм`
- `75 / 90 мм`
- `abc`

Expected status behavior:

- empty value -> `unknown`
- one number without range -> `proposed`
- number with `мм` / `mm` -> `proposed`
- decimal comma / dot -> `proposed`
- multiple numbers -> `needs_review`
- range -> `needs_review`
- text without numbers -> `unknown`

### Boundary

The parser is standalone only.

It does not:

- connect to `analyze_values`;
- connect to `sql_preview`;
- connect to `build_report`;
- connect to `build_framework_result`;
- change pipeline wiring;
- change runners;
- change default dry-run path;
- change `HANDOFF.md`;
- create approved values;
- create rejected values;
- create executable SQL;
- create SQL files;
- create apply plan;
- perform SQL apply;
- use live DB.

### Syntax check

Command:

`C:\php56\php.exe -l framework-standardization\src\Normalizer\DbReadOnlyNormalizationProposalParser.php`

Result:

`No syntax errors detected`

### Standalone manual-check

Result:

- `approved_count: 0`
- `rejected_count: 0`
- `proposed_count: 4`
- `needs_review_count: 2`
- `unknown_count: 2`
- `range_detected_count: 2`
- `multiple_numbers_count: 2`
- `sql_generated: 0`
- `apply_plan_created: 0`

Temporary manual-check file was removed after verification.

### Default dry-run regression check

Command:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Safety result

The parser is a standalone normalization proposal skeleton only.

It can create proposals with `proposed`, `needs_review` and `unknown` statuses, but it cannot approve proposals and cannot create apply-ready output.

SQL generation and SQL apply remain blocked.

## 2026-07-06 — DB-readonly normalization approval flow standalone check

### Context

Implementation commit:

`7d1f3e2 Add DB readonly normalization approval flow`

Created component:

`framework-standardization/src/Approval/DbReadOnlyNormalizationApprovalFlow.php`

This check covers standalone normalization approval flow skeleton.

The approval flow is not connected to pipeline wiring.

### What was added

`DbReadOnlyNormalizationApprovalFlow` implements standalone method:

`apply($proposals, $reviewActions)`

Input contains normalization proposals with fields such as:

- `proposal_id`
- `product_id`
- `attribute_id`
- `target_attribute_id`
- `original_raw_value`
- `parsed_value`
- `proposed_normalized_value`
- `proposed_unit`
- `parser_confidence`
- `parser_warnings`
- `approval_status`

Input review actions contain fields such as:

- `proposal_id`
- `action`
- `reviewer`
- `review_note`
- `source`

Output contains:

- `updated_proposals`
- `approval_audit`
- `approval_summary`
- `errors`
- `warnings`
- `source`

### Supported actions and status transitions

The standalone approval flow supports explicit actions:

- `approve` -> `approved`
- `reject` -> `rejected`
- `mark_needs_review` -> `needs_review`
- `mark_unknown` -> `unknown`
- `reset_to_proposed` -> `proposed`

Only approval flow can create `approved` and `rejected` statuses.

Parser remains unable to create `approved` or `rejected`.

### Audit trail

Each applied review action creates audit fields:

- `proposal_id`
- `review_action`
- `reviewer`
- `reviewed_at`
- `review_note`
- `previous_status`
- `new_status`
- `source`

### Approval summary

`approval_summary` contains counts:

- `total_proposals`
- `approved_count`
- `rejected_count`
- `needs_review_count`
- `unknown_count`
- `proposed_count`
- `changed_count`
- `error_count`

### Boundary

The approval flow is standalone only.

It does not:

- connect to parser;
- connect to `analyze_values`;
- connect to `sql_preview`;
- connect to `build_report`;
- connect to `build_framework_result`;
- change pipeline wiring;
- change runners;
- change default dry-run path;
- change docs;
- change `HANDOFF.md`;
- create executable SQL;
- create SQL files;
- create SQL diff;
- create apply plan;
- perform SQL apply;
- use live DB.

`approved` means only future SQL preview candidate eligibility.

`approved` does not mean:

- SQL apply;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

### Syntax check

Command:

`C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyNormalizationApprovalFlow.php`

Result:

`No syntax errors detected`

### Standalone manual-check

Result:

- `approved_count: 1`
- `rejected_count: 1`
- `needs_review_count: 1`
- `unknown_count: 1`
- `proposed_count: 1`
- `changed_count: 5`
- `audit_previous_status: proposed`
- `audit_new_status: approved`
- `reviewed_at_present: 1`
- `reviewer_present: 1`
- `safe_to_apply: 0`
- `sql_generated: 0`
- `apply_plan_created: 0`

Temporary manual-check file was removed after verification.

### Default dry-run regression check

Command:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Safety result

The approval flow is a standalone controlled review skeleton only.

It can explicitly change proposal statuses to `approved`, `rejected`, `needs_review`, `unknown` or `proposed`, but it cannot create SQL/apply output.

SQL generation and SQL apply remain blocked.

## 2026-07-06 — DB-readonly local approval fixture bridge standalone check

### Context

Implementation commit:

`1a5e9ef Add DB readonly local approval fixture bridge`

Checked component:

`framework-standardization/src/Approval/DbReadOnlyLocalApprovalFixtureBridge.php`

This check covers standalone local approval fixture bridge skeleton.

The bridge is not connected to pipeline wiring.

### What was checked

`DbReadOnlyLocalApprovalFixtureBridge` accepts local JSON fixture structure as PHP array, separates parser-owned proposal rows from reviewer-owned review actions, and delegates status transitions to:

`DbReadOnlyNormalizationApprovalFlow::apply($proposals, $reviewActions)`

The bridge itself does not create `approved` / `rejected` statuses directly.

### Syntax check

Command:

`C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalApprovalFixtureBridge.php`

Result:

`No syntax errors detected`

### Standalone manual-check

Manual fixture shape:

- 3 proposals total;
- one proposal with `approve` action;
- one proposal with empty action;
- one proposal with missing `review` block.

Observed:

- `proposals_count: 3`
- `review_actions_count: 1`
- `skipped_empty_actions_count: 2`
- `missing_review_block_count: 1`
- `approved_count: 1`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `safe_to_apply: 0`
- `errors_count: 0`

### Default dry-run regression check

Command:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Boundary

The local approval fixture bridge is standalone only.

Confirmed:

- pipeline wiring did not change;
- parser did not change;
- approval flow did not change;
- `sql_preview` did not change;
- report did not change;
- framework result did not change;
- runners did not change;
- default dry-run path did not change;
- bridge does not use DB or live DB;
- SQL generation was not added;
- SQL files were not created;
- SQL diff was not created;
- apply plan was not created;
- SQL apply was not performed;
- fixture JSON files were not committed.

The bridge remains a local review artifact/process between standalone parser output and standalone approval flow.

## 2026-07-06 — DB-readonly local review fixture generator standalone check

### Context

Implementation commit:

`416579f Add DB readonly local review fixture generator`

Checked component:

`framework-standardization/src/Approval/DbReadOnlyLocalReviewFixtureGenerator.php`

This check covers standalone local review fixture generator skeleton.

The generator is not connected to pipeline wiring.

### What was checked

`DbReadOnlyLocalReviewFixtureGenerator` accepts standalone parser output as PHP array and returns a JSON-ready local review fixture array.

The generator copies parser-owned proposal fields and creates empty reviewer-owned `review` blocks.

The generator does not call approval flow, does not call fixture bridge, and does not create fixture JSON files.

### Syntax check

Command:

`C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalReviewFixtureGenerator.php`

Result:

`No syntax errors detected`

### Standalone manual-check

Manual parser output shape:

- 3 proposals total;
- one proposal with `proposed` status;
- one proposal with `needs_review` status;
- one proposal with `unknown` status.

Observed:

- `fixture_type: db_readonly_normalization_review`
- `generator_mode: standalone_local_review_fixture_generation`
- `proposals_count: 3`
- `review_blocks_created_count: 3`
- `all_review_action_empty: 1`
- `approved_count: 0`
- `rejected_count: 0`
- `writes_files: 0`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `safe_to_apply: 0`
- `errors_count: 0`
- `warnings_count: 0`

### Default dry-run regression check

Command:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Boundary

The local review fixture generator is standalone only.

Confirmed:

- pipeline wiring did not change;
- parser did not change;
- approval flow did not change;
- fixture bridge did not change;
- `sql_preview` did not change;
- report did not change;
- framework result did not change;
- runners did not change;
- default dry-run path did not change;
- generator does not use DB or live DB;
- approval flow was not called by generator;
- fixture bridge was not called by generator;
- `approved` / `rejected` statuses were not generated;
- fixture JSON files were not written;
- `var` directory was not created;
- `.gitignore` did not change;
- SQL generation was not added;
- SQL files were not created;
- SQL diff was not created;
- apply plan was not created;
- SQL apply was not performed.

The generator remains a standalone JSON-ready fixture array builder for human review.

## 2026-07-06 — DB-readonly standalone E2E review flow temporary check

### Context

Working point:

`eedc5f5 Document standalone review flow check boundary`

This check covers the standalone in-memory review flow:

```text
parserOutput array
-> DbReadOnlyLocalReviewFixtureGenerator::generate($parserOutput)
-> manual in-memory edit fixture review blocks
-> DbReadOnlyLocalApprovalFixtureBridge::applyFixture($editedFixture)
-> DbReadOnlyNormalizationApprovalFlow::apply($proposals, $reviewActions)
```

The check was temporary only. It did not add a runner, test framework or persistent fixture files.

### Syntax checks

Commands:

```text
C:\php56\php.exe -l framework-standardization\src\Normalizer\DbReadOnlyNormalizationProposalParser.php
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalReviewFixtureGenerator.php
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalApprovalFixtureBridge.php
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyNormalizationApprovalFlow.php
```

Observed:

- `No syntax errors detected` for all four classes.

### Standalone E2E check setup

Parser output contained 4 proposals:

- `proposed`
- `proposed`
- `unknown`
- `needs_review`

Parser output contained no `approved` or `rejected` proposals.

Manual review was simulated in memory:

- proposal A: `approve`
- proposal B: `reject`
- proposal C: `mark_needs_review`
- proposal D: empty action

### Generator observed facts

Observed:

- `fixture_type: db_readonly_normalization_review`
- `generator_mode: standalone_local_review_fixture_generation`
- `proposals_count: 4`
- `review_blocks_created_count: 4`
- `all_review_action_empty_before_manual_edit: 1`
- `approved_count: 0`
- `rejected_count: 0`
- `writes_files: 0`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `safe_to_apply: 0`

### Bridge observed facts

Observed:

- `proposals_count: 4`
- `review_actions_count: 3`
- `skipped_empty_actions_count: 1`
- `bridge_mode: standalone_local_fixture_bridge`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `safe_to_apply: 0`

### Approval flow observed facts

Observed:

- `total_proposals: 4`
- `approved_count: 1`
- `rejected_count: 1`
- `needs_review_count: 2`
- `changed_count: 3`
- `approval_audit_count: 3`
- proposal D with empty action remained `needs_review`
- `approved` / `rejected` appeared only after approval flow
- `safe_to_apply: 0`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `errors_count: 0`
- `warnings_count: 0`

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Observed:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Observed:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Git safety / boundary confirmation

Confirmed:

- temporary PHP file was created only for check and removed;
- fixture JSON files were not created;
- `var` directory was not created;
- `.gitignore` was not changed;
- production PHP code did not change;
- docs did not change before this `RUNTIME_CHECKS.md` update;
- pipeline wiring did not change;
- runners did not change;
- parser did not change;
- generator did not change;
- bridge did not change;
- approval flow did not change;
- `sql_preview` did not change;
- report did not change;
- framework result did not change;
- SQL generation was not added;
- SQL files were not created;
- SQL diff was not created;
- apply plan was not created;
- SQL apply was not performed;
- live DB was not used;
- DB/schema operations were not used;
- git status before this docs update was `working tree clean`.

The standalone E2E review flow remains a contract check for standalone components only.

## 2026-07-06 — DbReadOnlyLocalReviewFixtureWriter implementation check

### Context

Implementation commit:

`5b83d6e Add DB readonly local review fixture writer`

Created standalone class:

`framework-standardization/src/Approval/DbReadOnlyLocalReviewFixtureWriter.php`

This check covers standalone local review fixture writer implementation.

### Boundary

The writer is standalone only.

Confirmed boundaries:

- writer is not connected to pipeline;
- writer is not connected to runners;
- writer does not call parser;
- writer does not call generator;
- writer does not call fixture bridge;
- writer does not call approval flow;
- writer does not call SQL preview;
- writer does not use DB or live DB;
- writer does not create SQL/apply artifacts;
- writer does not change fixture `approval_status`;
- writer does not create `approved` or `rejected`.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalReviewFixtureWriter.php
```

Result:

```text
No syntax errors detected
```

### Standalone manual check

Manual check setup:

- small fixture array was created in memory;
- `writer->write($fixture, 'manual_check_YYYYMMDD_HHMMSS.review.json')` was called;
- file was created under `framework-standardization/var/review-fixtures/`;
- `.json` extension was confirmed;
- executable SQL was not found;
- generated JSON did not appear in `git status`;
- generated fixture was deleted;
- `framework-standardization/var/review-fixtures` remained as empty local directory;
- no tracked/staged files exist under `var/review-fixtures`.

Writer diagnostics:

- `wrote_file = 1`
- `bytes_written = 1018`
- `fixture_type = db_readonly_normalization_review`
- `proposals_count = 1`
- `writes_files = 1`
- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `git_ignored_expected = 1`

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Git safety

Confirmed after implementation:

- `git status` showed only the new PHP file before commit;
- docs did not change during implementation;
- `.gitignore` did not change during implementation;
- pipeline did not change;
- runners did not change;
- jobs did not change;
- config did not change;
- generated fixture JSON was deleted;
- `var/review-fixtures` may remain as local directory, but without tracked files.

The writer remains a standalone local JSON review artifact writer only.

## 2026-07-06 — DbReadOnlyLocalReviewFixtureLoader implementation check

### Context

Implementation commit:

`d7af3d0 Add DB readonly local review fixture loader`

Created standalone class:

`framework-standardization/src/Approval/DbReadOnlyLocalReviewFixtureLoader.php`

This check covers standalone local review fixture loader implementation.

### Boundary

The loader is standalone only.

Confirmed boundaries:

- loader is not connected to pipeline;
- loader is not connected to runners;
- loader does not call parser;
- loader does not call generator;
- loader does not call writer;
- loader does not call fixture bridge;
- loader does not call approval flow;
- loader does not call SQL preview;
- loader does not use DB or live DB;
- loader does not create SQL/apply artifacts;
- loader does not change `review.action`;
- loader does not change `approval_status`;
- loader does not create `approved` or `rejected`.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalReviewFixtureLoader.php
```

Result:

```text
No syntax errors detected
```

### Standalone manual check

Manual check setup:

- temporary JSON fixture was created under `framework-standardization/var/review-fixtures/`;
- `loader->load($filename)` was called;
- generated JSON did not appear in `git status`;
- temporary fixture was deleted;
- after check, there were no JSON files in `var/review-fixtures`.

Loader diagnostics:

- `loaded = 1`
- `loaded_file = 1`
- `bytes_read = 842`
- `fixture_type = db_readonly_normalization_review`
- `proposals_count = 1`
- `reads_files = 1`
- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `git_ignored_expected = 1`
- `fixture_is_array_object = 1`
- `errors_count = 0`
- `warnings_count = 0`

### Negative checks

Observed:

- path traversal rejected: `loaded = 0`;
- non-json rejected: `loaded = 0`;
- SQL/apply-like filename rejected: `loaded = 0`;
- invalid JSON returned error: `loaded = 0`, `error = json_decode_failed`;
- invalid JSON fixture was deleted;
- after negative checks, there were no JSON files in `var/review-fixtures`.

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Git safety

Confirmed after implementation:

- `git status` showed only the new PHP file before commit;
- docs did not change during implementation;
- `.gitignore` did not change during implementation;
- pipeline did not change;
- runners did not change;
- jobs did not change;
- config did not change;
- parser did not change;
- generator did not change;
- writer did not change;
- bridge did not change;
- approval flow did not change;
- SQL preview did not change;
- report did not change;
- framework result did not change;
- generated fixture JSON was deleted;
- `var/review-fixtures` may remain as local directory, but without tracked files.

The loader remains a standalone local JSON review artifact reader only.

## 2026-07-06  DbReadOnlyReviewChainResultReporter implementation check

### Context

Implementation commit:

`d5b66da Add DB readonly review chain result reporter`

Created standalone class:

`framework-standardization/src/Approval/DbReadOnlyReviewChainResultReporter.php`

This check covers the standalone review-chain result reporter implementation.

### Boundary

The reporter is standalone reporting/diagnostics only.

Confirmed boundaries:

- reporter is not connected to pipeline;
- reporter is not connected to runners;
- reporter does not call bridge;
- reporter does not call approval flow;
- reporter does not call SQL preview;
- reporter does not use DB or live DB;
- reporter does not create SQL/apply artifacts;
- reporter does not change statuses;
- reporter does not create `approved` or `rejected`;
- reporter does not accept review decisions;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyReviewChainResultReporter.php
```

Result:

```text
No syntax errors detected
```

### Standalone manual check

Manual check setup:

- `approvalResult` array was created in memory;
- `reporter->summarize($approvalResult)` was called;
- unsupported status was reported in diagnostics and was not converted into reject/approve;
- input `approvalResult` remained unchanged.

Observed:

- `proposed_count = 1`
- `unsupported_statuses_count = 1`
- `unsupported_status_seen = 1`
- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`
- `input_unchanged = 1`
- `errors_count = 0`
- `warnings_count = 1`

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Git safety

Confirmed after implementation:

- `git status` showed only the new PHP file before commit;
- docs did not change during implementation;
- `.gitignore` did not change during implementation;
- pipeline did not change;
- runners did not change;
- jobs did not change;
- config did not change;
- parser did not change;
- generator did not change;
- writer did not change;
- loader did not change;
- bridge did not change;
- approval flow did not change;
- SQL preview did not change;
- report did not change;
- framework result did not change;
- runtime fixture JSON files were not created;
- SQL/apply artifacts were not created;
- DB/live DB was not used.

The reporter remains a standalone review-chain reporting/diagnostics boundary only.

## 2026-07-07  DbReadOnlyStandaloneReviewChainE2EChecker implementation check

### Context

Implementation commit:

`0dab23a Add DB readonly standalone review chain E2E checker`

Created standalone class:

`framework-standardization/src/Approval/DbReadOnlyStandaloneReviewChainE2EChecker.php`

The checker is standalone diagnostic-only and runs the local review chain:

```text
generator
-> writer
-> local ignored JSON
-> synthetic review blocks
-> loader
-> bridge
-> approval flow
-> reporter
```

### Boundary

Confirmed boundaries:

- checker is not a pipeline stage;
- checker is not runner integration;
- checker is not SQL preview input;
- checker is not production output;
- checker does not change default dry-run path;
- checker does not use DB or live DB;
- checker does not perform DB/schema changes;
- checker does not create SQL/apply artifacts;
- runtime fixture JSON does not remain after successful check;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyStandaloneReviewChainE2EChecker.php
```

Result:

```text
No syntax errors detected
```

### Standalone manual check

Manual check setup:

- parser-like output array was created in memory;
- `checker->run($parserOutput)` was called;
- temporary fixture JSON was created only under `framework-standardization/var/review-fixtures/`;
- fixture was removed after check;
- SQL/apply artifacts were not created.

Observed:

- `checked = 1`
- `generator_ok = 1`
- `writer_ok = 1`
- `loader_ok = 1`
- `bridge_ok = 1`
- `approval_flow_ok = 1`
- `reporter_ok = 1`
- `temp_fixture_created = 1`
- `temp_fixture_removed = 1`
- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`
- `errors_count = 0`
- `warnings_count = 0`
- `fixture_exists_after_run = 0`
- `json_files_count_after_run = 0`

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### Git safety

Confirmed after implementation:

- `git status` showed only the new PHP file before commit;
- docs did not change during implementation;
- `.gitignore` did not change during implementation;
- pipeline did not change;
- runners did not change;
- jobs did not change;
- config did not change;
- parser did not change;
- generator did not change;
- writer did not change;
- loader did not change;
- bridge did not change;
- approval flow did not change;
- reporter did not change;
- SQL preview did not change;
- report did not change;
- framework result did not change;
- runtime fixture JSON did not remain after check;
- SQL/apply artifacts were not created.

The checker remains a standalone review-chain diagnostic/compatibility boundary only.

## 2026-07-07  DbReadOnlyRealDataReviewChainUsageChecker implementation check

### Context

Implementation commit:

`79106c0 Add DB readonly real data review chain usage checker`

Created standalone class:

`framework-standardization/src/Approval/DbReadOnlyRealDataReviewChainUsageChecker.php`

Purpose:

- standalone usage checker for controlled readonly real-data-like scenario;
- accepts readonly input array;
- builds parser-like output;
- calls `DbReadOnlyStandaloneReviewChainE2EChecker`;
- returns `used`, `usage_diagnostics`, `e2e_result`, `errors`, `warnings`, `source`.

### Boundary

Confirmed boundaries:

- standalone controlled readonly usage only;
- not a pipeline stage;
- not runner integration;
- not SQL preview input;
- not production output;
- no live DB;
- no production DB;
- no full category batch;
- no arbitrary uploaded data;
- no OpenCart runtime path;
- no production data changes;
- no SQL generation/files/diff/apply plan/apply;
- no DB/schema changes;
- no write/schema operations;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyRealDataReviewChainUsageChecker.php
```

Result:

```text
No syntax errors detected
```

### Positive standalone check

Manual check setup:

- readonly input array was created in memory with 1-2 rows;
- `checker->run($readonlyInput)` was called;
- checker built parser-like output;
- checker called `DbReadOnlyStandaloneReviewChainE2EChecker`;
- runtime fixture JSON did not remain after check;
- SQL/apply artifacts were not created.

Observed:

- `used = 1`
- `parser_like_output_created = 1`
- `e2e_checker_called = 1`
- `e2e_checked = 1`
- `review_ready = 1`
- `errors_count = 0`

### Negative checks

Observed:

- non-array input rejected with `used = 0`;
- empty rows rejected with `used = 0`;
- over-limit rows rejected with `used = 0`;
- path/URL inputs rejected with `used = 0`.

### Runtime artifact safety

Observed:

- JSON fixture after check did not remain;
- SQL/apply artifacts were not created.

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

The usage checker remains a standalone controlled readonly real-data-like scenario checker only.

## 2026-07-07  DbReadOnlyFirstRealDataUsageInputFixture implementation check

### Context

Implementation commit:

`941a5b8 Add DB readonly first real data usage input fixture`

Created standalone class:

`framework-standardization/src/Approval/DbReadOnlyFirstRealDataUsageInputFixture.php`

Purpose:

- standalone readonly fixture provider for the first controlled input source;
- context: `pump_diameter`;
- prepared fixture contains controlled readonly rows;
- first run slice is limited to `<= 2 rows`;
- fixture is intended only for local controlled usage checks.

### Boundary

Confirmed boundaries:

- standalone readonly fixture provider only;
- not connected to pipeline;
- not connected to runners;
- no live DB;
- no production DB;
- no SQL/apply artifacts;
- no runtime fixture JSON left after check;
- no production output;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyFirstRealDataUsageInputFixture.php
```

Result:

```text
No syntax errors detected
```

### Standalone fixture check

Observed:

- `context = pump_diameter`
- prepared rows count `4 <= 12`
- first run rows count `2 <= 2`
- `readonly = 1`
- all required row fields exist
- SQL/apply markers are all `0`

Non-apply markers:

- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`

### Integration check with DbReadOnlyRealDataReviewChainUsageChecker

Manual check setup:

- `getFirstRunSlice()` result was passed to `DbReadOnlyRealDataReviewChainUsageChecker->run($readonlyInput)`;
- usage checker created parser-like output;
- usage checker called the standalone E2E checker;
- runtime fixture JSON did not remain after check;
- SQL/apply artifacts were not created.

Observed:

- `used = 1`
- `parser_like_output_created = 1`
- `e2e_checker_called = 1`
- `e2e_checked = 1`
- `review_ready = 1`
- `errors_count = 0`

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

The fixture provider remains a standalone readonly input source for controlled local review-chain checks only.

## 2026-07-07  db-readonly-first-real-data-usage-check manual command implementation check

### Context

Implementation commit:

`87f6c35 Add DB readonly first real data usage manual command`

Created standalone manual command:

`framework-standardization/bin/db-readonly-first-real-data-usage-check.php`

Purpose:

- standalone manual entrypoint;
- does not accept CLI arguments;
- gets `DbReadOnlyFirstRealDataUsageInputFixture::getFirstRunSlice(2)`;
- passes input to `DbReadOnlyRealDataReviewChainUsageChecker::run($readonlyInput)`;
- prints concise diagnostics as plain text;
- returns exit code `0` only for a successful safe readonly check.

### Boundary

Confirmed boundaries:

- standalone manual command only;
- not connected to pipeline;
- not connected to runners;
- no arbitrary input;
- no filenames/paths/URLs input;
- no live DB;
- no production DB;
- no SQL preview;
- no SQL generation/files/diff/apply plan/apply;
- no DB/schema changes;
- no production output;
- no committed runtime artifacts;
- default dry-run path does not change;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\bin\db-readonly-first-real-data-usage-check.php
```

Result:

```text
No syntax errors detected
```

- `Syntax: No syntax errors detected`

### Manual command run

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-first-real-data-usage-check.php
```

Observed:

- `used = 1`
- `review_ready = 1`
- `input_rows_count = 2`
- `e2e_checked = 1`
- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`
- `errors_count = 0`
- `warnings_count = 0`
- `exit_code = 0`

### Runtime artifact safety

Observed:

- runtime fixture JSON did not remain after command;
- SQL/apply artifacts were not created.

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

The command remains a standalone manual readonly usage-check entrypoint only.

## 2026-07-07  db-readonly-real-data-usage-review-batch-check manual command implementation check

### Context

Implementation commit:

`02e4456 Add DB readonly real data usage review batch command`

Created standalone manual command:

`framework-standardization/bin/db-readonly-real-data-usage-review-batch-check.php`

Purpose:

- standalone manual command for controlled expanded review batch;
- uses prepared fixture rows from `DbReadOnlyFirstRealDataUsageInputFixture`;
- runs expanded batch with `input_rows_count = 4`;
- passes readonly input to `DbReadOnlyRealDataReviewChainUsageChecker::run($readonlyInput)`;
- prints concise diagnostics as plain text;
- returns exit code `0` only for a successful safe readonly expanded batch check.

### Boundary

Confirmed boundaries:

- standalone manual command only;
- expanded batch `input_rows_count = 4`;
- not connected to pipeline;
- not connected to runners;
- no live DB;
- no production DB;
- no full category batch;
- no arbitrary input;
- no filenames/paths/URLs input;
- no SQL preview;
- no SQL generation/files/diff/apply plan/apply;
- no DB/schema changes;
- no production output;
- no committed runtime artifacts;
- default dry-run path does not change;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\bin\db-readonly-real-data-usage-review-batch-check.php
```

Result:

```text
No syntax errors detected
```

### Manual command run

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-real-data-usage-review-batch-check.php
```

Observed:

- `used = 1`
- `review_ready = 1`
- `input_rows_count = 4`
- `e2e_checked = 1`
- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`
- `errors_count = 0`
- `warnings_count = 0`
- `exit_code = 0`

### Runtime artifact safety

Observed:

- runtime fixture JSON did not remain after command;
- SQL/apply artifacts were not created.

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

The expanded batch command remains a standalone manual readonly usage-check entrypoint only.

## 2026-07-07  DbReadOnlyFirstRealDataUsageInputFixture expansion check

### Context

Implementation commit:

`04d02e5 Expand DB readonly first real data usage input fixture`

Updated fixture provider:

`framework-standardization/src/Approval/DbReadOnlyFirstRealDataUsageInputFixture.php`

Purpose:

- expanded prepared fixture from 4 to 8 controlled rows;
- context remains `pump_diameter`;
- `max_rows` remains `12`;
- `getFirstRunSlice()` default remains `<= 2 rows`;
- fixture remains local readonly dump-derived/test-like data.

### Boundary

Confirmed boundaries:

- prepared fixture expanded from 4 to 8 controlled rows;
- `max_rows` remains `12`;
- context remains `pump_diameter`;
- `getFirstRunSlice()` default remains `<= 2 rows`;
- no external file reading;
- no CLI input;
- no DB access;
- not connected to pipeline;
- not connected to runners;
- no SQL/apply artifacts;
- no production output;
- default dry-run path does not change;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyFirstRealDataUsageInputFixture.php
```

Result:

```text
No syntax errors detected
```

### Standalone fixture check

Observed:

- `context = pump_diameter`
- `prepared_rows_count = 8`
- `prepared_rows_gt_4 = 1`
- `prepared_rows_lte_12 = 1`
- `first_run_rows_count = 2`
- `first_run_rows_lte_2 = 1`
- `readonly = 1`
- `all_required_row_fields_exist = 1`
- SQL/apply markers are all `0`

Non-apply markers:

- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`

### First manual command regression

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-first-real-data-usage-check.php
```

Observed:

- `used = 1`
- `review_ready = 1`
- `input_rows_count = 2`
- `e2e_checked = 1`
- SQL/apply markers are all `0`
- `errors_count = 0`
- `exit_code = 0`

### Expanded batch command regression

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-real-data-usage-review-batch-check.php
```

Observed:

- `used = 1`
- `review_ready = 1`
- `input_rows_count = 4`
- `e2e_checked = 1`
- SQL/apply markers are all `0`
- `errors_count = 0`
- `exit_code = 0`

### Runtime artifact safety

Observed:

- runtime fixture JSON did not remain after checks;
- SQL/apply artifacts were not created.

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

The prepared fixture provider remains a standalone local readonly fixture source only.

## 2026-07-07  db-readonly-prepared-fixture-full-batch-check manual command implementation check

### Context

Implementation commit:

`0763272 Add DB readonly prepared fixture full batch command`

Created standalone manual command:

`framework-standardization/bin/db-readonly-prepared-fixture-full-batch-check.php`

Purpose:

- standalone manual command;
- takes the full current prepared fixture set from `DbReadOnlyFirstRealDataUsageInputFixture`;
- current full set is `8` rows;
- validates `input_rows_count = 8`;
- validates `input_rows_count > 4`;
- validates `input_rows_count <= 12`;
- runs the data through `DbReadOnlyRealDataReviewChainUsageChecker`;
- because the current usage checker safety limit is `MAX_ROWS = 5`, the full set is processed inside the command as two bounded chunks;
- existing classes were not changed.

### Boundary

Confirmed boundaries:

- standalone manual command only;
- full prepared fixture set is `8` rows;
- bounded chunking is used only to respect usage checker `MAX_ROWS = 5`;
- not connected to pipeline;
- not connected to runners;
- no arbitrary input;
- no filenames/paths/URLs input;
- no live DB;
- no production DB;
- no full category batch;
- no SQL preview;
- no SQL generation/files/diff/apply plan/apply;
- no DB/schema changes;
- no production output;
- no committed runtime artifacts;
- default dry-run path does not change;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\bin\db-readonly-prepared-fixture-full-batch-check.php
```

Result:

```text
No syntax errors detected
```

### Manual command run

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-prepared-fixture-full-batch-check.php
```

Observed:

- `used = 1`
- `review_ready = 1`
- `input_rows_count = 8`
- `e2e_checked = 1`
- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`
- `errors_count = 0`
- `warnings_count = 0`

### Runtime safety

Observed:

- JSON fixture files did not remain after command;
- SQL/apply artifacts were not created.

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

The full prepared fixture batch command remains a standalone manual readonly usage-check entrypoint only.

## 2026-07-07  DbReadOnlySecondPumpDiameterUsageInputFixture implementation check

### Context

Implementation commit:

`f5c87ac Add DB readonly second pump diameter usage input fixture`

Created standalone class:

`framework-standardization/src/Approval/DbReadOnlySecondPumpDiameterUsageInputFixture.php`

Purpose:

- second controlled sample source inside the same `pump_diameter` characteristic;
- standalone local readonly fixture provider;
- source marker: `second_pump_diameter_controlled_sample`;
- prepared fixture contains `8` controlled readonly rows;
- first run slice remains limited to `2` rows;
- full batch contains `8` rows.

### Boundary

Confirmed boundaries:

- second controlled sample source inside the same `pump_diameter` characteristic;
- standalone local readonly fixture provider only;
- no new characteristic;
- no full category batch;
- not connected to pipeline;
- not connected to runners;
- no live DB;
- no production DB;
- no arbitrary input;
- no filenames/paths/URLs input;
- no SQL preview;
- no SQL generation/files/diff/apply plan/apply;
- no DB/schema changes;
- no production output;
- no committed runtime artifacts;
- default dry-run path does not change;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax check

Command:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlySecondPumpDiameterUsageInputFixture.php
```

Result:

```text
No syntax errors detected
```

### Standalone fixture check

Observed:

- `context = pump_diameter`
- `source_marker = second_pump_diameter_controlled_sample`
- `prepared_rows_count = 8`
- `prepared_rows_lte_12 = 1`
- `first_run_rows_count = 2`
- `full_batch_rows_count = 8`
- `readonly = 1`
- `all_required_row_fields_exist = 1`
- SQL/apply markers are all `0`

Non-apply markers:

- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`

### Integration check with DbReadOnlyRealDataReviewChainUsageChecker

Manual check setup:

- `getFirstRunSlice()` result was passed to `DbReadOnlyRealDataReviewChainUsageChecker->run($readonlyInput)`;
- usage checker created parser-like output;
- usage checker called the standalone E2E checker;
- runtime fixture JSON did not remain after check;
- SQL/apply artifacts were not created.

Observed:

- `used = 1`
- `parser_like_output_created = 1`
- `e2e_checker_called = 1`
- `e2e_checked = 1`
- `review_ready = 1`
- `errors_count = 0`

### Full batch check through bounded chunks

Because the current usage checker safety limit is `MAX_ROWS = 5`, full batch was checked through bounded chunks without changing existing classes.

Observed:

- `full_batch_rows_count = 8`
- `chunks_used = 2`
- all chunks `used/review_ready/e2e_checked = 1`
- aggregate `errors_count = 0`
- SQL/apply markers are all `0`

Aggregate non-apply markers:

- `sql_generated = 0`
- `apply_plan_created = 0`
- `safe_to_apply = 0`
- `sql_apply_allowed = 0`
- `production_ready = 0`

### Runtime artifact safety

Observed:

- temporary check file was removed;
- `framework-standardization/var/review-fixtures/*.json: 0`;
- SQL/apply artifacts were not created.

### Default dry-run regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

The second pump diameter usage input fixture remains a standalone local readonly controlled source only.

## 2026-07-08 — DB-readonly attribute discovery command implementation check

### Context

Implementation commit:

`05a1f2a Add DB readonly attribute discovery command`

Spec commit:

`c7a9cbc Add DB readonly attribute discovery command spec`

Created standalone manual command:

`framework-standardization/bin/db-readonly-attribute-discovery.php`

Created discovery class:

`framework-standardization/src/Discovery/DbReadOnlyAttributeDiscovery.php`

Purpose:

- standalone manual CLI command for DB-readonly attribute name discovery;
- shows real OpenCart `attribute_id` / `attribute_name` candidates for human canonical selection;
- uses readonly local dump runtime config;
- does not choose canonical attribute automatically;
- does not perform auto-merge;
- does not perform raw values inventory as a full step;
- does not perform normalization, proposals, SQL/apply, config/jobs, pipeline wiring or runner integration.

### Boundary

Confirmed boundaries:

- standalone manual command only;
- not connected to pipeline;
- not connected to runners;
- no config/jobs changes;
- no live DB;
- no production DB;
- no arbitrary SQL input;
- no SQL preview;
- no SQL generation/files/diff;
- no apply plan;
- no SQL apply;
- no DB/schema changes;
- no write/schema operations;
- no production output;
- no production/cache changes;
- no cache rebuild;
- no runtime artifacts;
- no committed runtime artifacts;
- default dry-run path does not change;
- `approved` remains only a review-chain status, not SQL/apply permission.

### Syntax checks

Commands:

`C:\php56\php.exe -l framework-standardization\src\Discovery\DbReadOnlyAttributeDiscovery.php`

`C:\php56\php.exe -l framework-standardization\bin\db-readonly-attribute-discovery.php`

Result:

`No syntax errors detected`

### Manual readonly command check

Command:

`C:\php56\php.exe framework-standardization\bin\db-readonly-attribute-discovery.php "максимальный напор" framework-standardization\config\runtime\local.dump.php`

Observed:

- `runtime_mode: db_readonly`
- `command: attribute_discovery`
- `target: максимальный напор`
- `candidates_count: 14`

Top observed candidates:

- `attribute_id: 12`
- `attribute_name: Максимальный напор`
- `usage_count: 1193`
- `reason_found: exact_name_match`
- `possible_role: canonical_candidate`
- `raw_samples: 40м. | 55м. | 22м.`

Second observed candidate:

- `attribute_id: 101`
- `attribute_name: Максимальный напор, м.вод.ст.`
- `usage_count: 14`
- `reason_found: all_search_terms_matched`
- `possible_role: canonical_candidate`

### Safety markers

Observed safety markers:

- `auto_canonical_selected: 0`
- `auto_merge_performed: 0`
- `raw_values_inventory_completed: 0`
- `unit_contract_created: 0`
- `normalization_proposals_created: 0`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `safe_to_apply: 0`
- `sql_apply_allowed: 0`
- `production_ready: 0`

### Default dry-run regression check

Command:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

### DB-readonly runner regression check

Command:

`C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php`

Result:

- `result_status: ok`
- `warnings_count: 0`
- `errors_count: 0`
- `all 9 stages ok`

The DB-readonly attribute discovery command remains a standalone manual readonly discovery entrypoint only.

## 2026-07-08 — DB-readonly attribute discovery markdown output check

### Context

Implementation commit:

`32d5933 Add markdown output for DB readonly attribute discovery`

Related previous implementation:

`05a1f2a Add DB readonly attribute discovery command`

The standalone DB-readonly attribute discovery command now supports console-only output formats:

- `--format=plain`
- `--format=markdown`

Default remains `plain`.

### Manual markdown output check

Command:

`chcp 65001; $OutputEncoding = [Console]::OutputEncoding = [Text.UTF8Encoding]::UTF8; C:\php56\php.exe framework-standardization\bin\db-readonly-attribute-discovery.php "максимальный напор" framework-standardization\config\runtime\local.dump.php --format=markdown`

Observed:

- Windows console code page switched to `65001`;
- output rendered readable Cyrillic text;
- `runtime_mode: db_readonly`;
- `command: attribute_discovery`;
- `target: максимальный напор`;
- `candidates_count: 14`;
- candidates were printed as a markdown table;
- raw sample separators inside table cells were escaped as `\|`;
- safety markers were printed as a fenced `text` block.

Top observed candidate:

- `attribute_id: 12`
- `attribute_name: Максимальный напор`
- `group: Параметры насоса`
- `usage_count: 1193`
- `reason_found: exact_name_match`
- `possible_role: canonical_candidate`
- `warnings: none`
- `raw_samples: 40м. | 55м. | 22м.`

Observed possible alias candidates for later human review:

- `101 — Максимальный напор, м.вод.ст.`
- `134 — Максимальный напор, м.вод.ст`
- `119 — Максимальный напор, м`
- `81 — Max напор, м`

Observed excluded / similar-but-different examples:

- `20 — Минимальный напор`
- `100 — Максимальный расход Qmax, м³/ч`
- `150 — Максимальный ток`
- `159 — Номинальный напор`
- `171 — Максимальный расход Qmax, м³/ч`
- `120 — Номинальный напор, м`
- `148 — Номинальный напор`
- `129 — Номинальный напор, м`

Observed unresolved / manual-check candidate:

- `108 — Напор, м`

### Safety markers

Observed safety markers remained zero:

- `auto_canonical_selected: 0`
- `auto_merge_performed: 0`
- `raw_values_inventory_completed: 0`
- `unit_contract_created: 0`
- `normalization_proposals_created: 0`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `safe_to_apply: 0`
- `sql_apply_allowed: 0`
- `production_ready: 0`

### Boundary confirmation

Confirmed:

- markdown output is console-only;
- no output files created;
- no runtime artifacts created;
- no config/jobs changes;
- no pipeline/runners changes;
- no SQL preview;
- no SQL generation/files/diff;
- no apply plan;
- no SQL apply;
- no production/cache changes;
- no cache rebuild;
- no auto-canonical selection;
- no auto-merge;
- no raw values inventory as a full step;
- no unit contract;
- no normalization proposals.

The command remains a standalone manual readonly discovery entrypoint.

## 2026-07-08 — DB-readonly attribute discovery category scope check

### Context

Implementation commit:

`eab3691 Add category scope to DB readonly attribute discovery`

The standalone DB-readonly attribute discovery command now supports optional category scope filtering:

- `--category-id=<int>`

When provided, discovery is limited to products assigned to the selected category or its child categories.

### Manual scoped markdown check

Command:

`chcp 65001; $OutputEncoding = [Console]::OutputEncoding = [Text.UTF8Encoding]::UTF8; C:\php56\php.exe framework-standardization\bin\db-readonly-attribute-discovery.php "максимальный напор" framework-standardization\config\runtime\local.dump.php --format=markdown --category-id=11900213`

Observed:

- `runtime_mode: db_readonly`
- `command: attribute_discovery`
- `target: максимальный напор`
- `category_scope: 11900213`
- `candidates_count: 8`
- readable Cyrillic output in PowerShell after UTF-8 console setup;
- candidates printed as a markdown table;
- safety markers printed as a fenced `text` block.

### Scoped candidate result

Canonical candidate:

- `12 — Максимальный напор`
- group: `Параметры насоса`
- usage_count: `400`
- reason_found: `exact_name_match`
- possible_role: `canonical_candidate`
- raw_samples: `46.5м. | 68м. | 93м.`

Possible alias candidates for later human review:

- `101 — Максимальный напор, м.вод.ст.`
- usage_count: `4`
- raw_samples: `310 | 279 | 54.5`

- `119 — Максимальный напор, м`
- usage_count: `1`
- raw_samples: `150`

- `81 — Max напор, м`
- usage_count: `90`
- raw_samples: `45 | 86 | 375`

Excluded similar-but-different candidates:

- `20 — Минимальный напор`
- `171 — Максимальный расход Qmax, м³/ч`
- `100 — Максимальный расход Qmax, м³/ч`
- `120 — Номинальный напор, м`

### Scope effect

Compared with the previous unscoped run:

- unscoped candidates_count: `14`
- scoped candidates_count: `8`

The category scope removed unrelated branches/examples from the result, including:

- `108 — Напор, м — Фекальные насосы`
- `150 — Максимальный ток — Насосные установки Grundfos`
- `159 — Номинальный напор — Насосные установки Grundfos`
- no-usage nominal head candidates from unrelated scope.

Remaining similar-but-different candidates are expected because they occur inside the selected category scope and must be excluded by human canonical selection.

### Safety markers

Observed safety markers remained zero:

- `auto_canonical_selected: 0`
- `auto_merge_performed: 0`
- `raw_values_inventory_completed: 0`
- `unit_contract_created: 0`
- `normalization_proposals_created: 0`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `safe_to_apply: 0`
- `sql_apply_allowed: 0`
- `production_ready: 0`

### Boundary confirmation

Confirmed:

- category scope is readonly discovery only;
- no output files created;
- no runtime artifacts created;
- no config/jobs changes;
- no pipeline/runners changes;
- no SQL preview;
- no SQL generation/files/diff;
- no apply plan;
- no SQL apply;
- no production/cache changes;
- no cache rebuild;
- no auto-canonical selection;
- no auto-merge;
- no raw values inventory as a full step;
- no unit contract;
- no normalization proposals.

The command remains a standalone manual readonly discovery entrypoint.

## 2026-07-08 — DB-readonly raw values inventory command check

### Context

Implementation commit:

`ad0090b Add DB readonly raw values inventory command`

Human decision source:

`docs/HUMAN_DECISION_MAX_HEAD_SCOPE_11900213.md`

Inventory command:

`framework-standardization/bin/db-readonly-raw-values-inventory.php`

Inventory class:

`framework-standardization/src/Inventory/DbReadOnlyRawValuesInventory.php`

The raw values inventory command is a standalone manual DB-readonly command.

It uses explicit manual inputs:

- `--category-id=11900213`
- `--attribute-ids=12,101,119,81`

The attribute IDs come from the human decision document and were not selected automatically by the command.

### Manual markdown inventory check

Command:

`chcp 65001; $OutputEncoding = [Console]::OutputEncoding = [Text.UTF8Encoding]::UTF8; C:\php56\php.exe framework-standardization\bin\db-readonly-raw-values-inventory.php framework-standardization\config\runtime\local.dump.php --category-id=11900213 --attribute-ids=12,101,119,81 --format=markdown`

Observed:

- `runtime_mode: db_readonly`
- `command: raw_values_inventory`
- `category_scope: 11900213`
- `attribute_ids: 12,101,119,81`
- readable Cyrillic output in PowerShell after UTF-8 console setup;
- attributes summary printed as markdown table;
- raw values printed as markdown tables grouped by attribute;
- safety markers printed as fenced `text` block.

### Attributes summary

Observed attributes summary:

- `12 — Максимальный напор`
  - group: `Параметры насоса`
  - products_with_attribute_count: `400`
  - distinct_raw_values_count: `182`

- `101 — Максимальный напор, м.вод.ст.`
  - group: `Насосы Pedrollo`
  - products_with_attribute_count: `4`
  - distinct_raw_values_count: `4`

- `119 — Максимальный напор, м`
  - group: `Параметры насоса`
  - products_with_attribute_count: `1`
  - distinct_raw_values_count: `1`

- `81 — Max напор, м`
  - group: `Насосы Pedrollo`
  - products_with_attribute_count: `90`
  - distinct_raw_values_count: `77`

### Inventory evidence summary

The inventory shows that `attribute_id: 12` is the main source for maximum head values in the selected category scope.

Observed raw value patterns include values with meter unit suffix:

- `60м.`
- `120м.`
- `90м.`
- `75 м`

Observed raw value patterns also include numeric values without explicit unit:

- `42`
- `65`
- `84`
- `200`
- `279`
- `310`
- `54.5`

Observed range / mixed values include examples such as:

- `100-104`
- `104–118`
- `123–151`
- `43–46`
- `50–51,5`
- `до 51 м`

These were flagged with warnings such as:

- `contains_unit_m`
- `multiple_numbers`
- `range_value`
- `mixed_text_value`

### Important observations

`attribute_id: 81 — Max напор, м` is confirmed as a real alias candidate within category scope `11900213`, but it contains mixed raw value formats, including ranges.

The inventory does not decide how to normalize these values.

Range handling remains unresolved and must be handled by a later canonical unit / normalized_value contract decision.

### Safety markers

Observed safety markers:

- `raw_values_inventory_completed: 1`
- `auto_canonical_selected: 0`
- `auto_merge_performed: 0`
- `unit_contract_created: 0`
- `normalization_proposals_created: 0`
- `sql_generated: 0`
- `apply_plan_created: 0`
- `safe_to_apply: 0`
- `sql_apply_allowed: 0`
- `production_ready: 0`

### Boundary confirmation

Confirmed:

- DB-readonly inventory only;
- no output files created;
- no runtime artifacts created;
- no config/jobs changes;
- no pipeline/runners changes;
- no SQL preview;
- no SQL generation/files/diff;
- no apply plan;
- no SQL apply;
- no production/cache changes;
- no cache rebuild;
- no auto-canonical selection;
- no auto-merge;
- no unit contract;
- no normalization proposals.

The next allowed workflow gate is canonical unit / normalized_value contract decision based on the inventory evidence.
