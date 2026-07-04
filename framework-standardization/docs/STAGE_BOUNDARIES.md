# Stage Boundaries

Документ фиксирует текущие границы pipeline stages для Framework Standardization.

Он описывает, что каждая stage читает, что пишет в `AttributeContext`, каким компонентам делегирует работу и чего не должна делать. Это не дизайн production DB/OpenCart integration.

## Назначение

No-DB pipeline boundary завершён для всех 9 stages.

Текущие реализации используют dry-run resolvers, exporters, analyzers и builders. Они работают на fixture facts и записывают структурированные результаты в `AttributeContext`.

Оперативный handoff находится в `docs/HANDOFF.md`. Этот документ является подробным справочником по stage-level contracts.

## Глобальные no-DB rules

Все текущие stages соблюдают правила:

```text
no DB connection
no OpenCart connection
no SQL apply
no executable SQL
no production DB reads
no OpenCart MVC/admin runtime
no OpenCart module paths
```

Правила взаимодействия внутри Pipeline:

```text
stages обмениваются данными только через AttributeContext
stages не вызывают друг друга напрямую
PipelineEngine отвечает за stage order и safe-mode execution
dry-run implementations работают на fixture facts
текущий source marker: dry_run_fixture
```

## Порядок stages

```text
1. ValidateJobStage              validate_job
2. ResolveCanonicalStage         resolve_canonical
3. ResolveScopeStage             resolve_scope
4. ExportAttributesStage         export_attributes
5. AnalyzeNamesStage             analyze_names
6. AnalyzeValuesStage            analyze_values
7. BuildSqlPreviewStage          build_sql_preview
8. BuildReportStage              build_report
9. BuildFrameworkResultStage     build_framework_result
```

Этот порядок нельзя менять без отдельного архитектурного решения.

## Карта чтения/записи AttributeContext

`ValidateJobStage`

```text
reads:  job.raw_job
writes: errors, stage_results.validate_job
```

`ResolveCanonicalStage`

```text
reads:  job.raw_job.canonical
writes: canonical, errors, stage_results.resolve_canonical
```

`ResolveScopeStage`

```text
reads:  job.raw_job.scope
writes: scope, raw_data.products, errors, stage_results.resolve_scope
```

`ExportAttributesStage`

```text
reads:  canonical, scope, raw_data.products
writes: raw_data.attributes, raw_data.attribute_groups, raw_data.product_attributes
writes: attribute_name_structure.target_attribute, attribute_name_structure.found_attributes
writes: attribute_value_structure.raw_values, errors, stage_results.export_attributes
```

`AnalyzeNamesStage`

```text
reads:  canonical, raw_data, attribute_name_structure
writes: attribute_name_structure, synonym_candidates, errors, stage_results.analyze_names
```

`AnalyzeValuesStage`

```text
reads:  canonical, attribute_value_structure.raw_values, job.raw_job.value_rules
writes: attribute_value_structure, value_report, errors, stage_results.analyze_values
```

`BuildSqlPreviewStage`

```text
reads:  canonical, scope, attribute_name_structure, synonym_candidates
reads:  attribute_value_structure, value_report, job.raw_job.output, job.raw_job.value_rules
writes: sql_preview, errors, stage_results.build_sql_preview
```

`BuildReportStage`

```text
reads:  AttributeContext
writes: report, errors, stage_results.build_report
```

`BuildFrameworkResultStage`

```text
reads:  AttributeContext
writes: framework_result, errors, stage_results.build_framework_result
```

## Соглашения dry_run_fixture

Текущие fixture values являются placeholders:

```text
source = dry_run_fixture
canonical_code = pump_diameter
category_id = 11900213
target_attribute_id = 0
product_id = 0
attribute_id = 0
value_parser = diameter_mm
raw_text = 96 мм
normalized_value = 96
```

Важно:

```text
target_attribute_id = 0 не является реальным OpenCart ID
product_id = 0 не является реальным OpenCart ID
attribute_id = 0 не является реальным OpenCart ID
```

## ValidateJobStage

Technical name:

```text
validate_job
```

Текущая ответственность:

```text
минимальная structural/safety validation Attribute Job
```

Проверяет:

```text
job_id
job_id format
job_name
canonical.canonical_code
scope.type = category
scope.category_id
source.type = opencart_db
source.language_id
value_rules.value_parser
value_rules.unknown_value_policy = block_sql / report_only
output.apply_changes = 0
```

Поддерживаемые errors:

```text
job_id_empty
job_id_invalid_format
job_name_empty
canonical_code_empty
unsupported_scope_type
scope_category_id_empty
unsupported_source_type
language_id_empty
value_parser_empty
unknown_value_policy_invalid
apply_changes_not_allowed
```

Пишет:

```text
AttributeContext.errors при ошибках
StageResult::failed при ошибках
StageResult::ok при успехе
```

Не делает:

```text
OpenCart/DB connection
canonical lookup
category/language existence checks
parser registry checks
SQL apply
```

Warnings пока не добавляет.

## ResolveCanonicalStage

Technical name:

```text
resolve_canonical
```

Текущая ответственность:

```text
разрешить canonical.canonical_code через no-DB resolver
```

Компоненты:

```text
CanonicalAttributeResolverInterface
DryRunCanonicalAttributeResolver
```

Текущая dry-run реализация:

```text
source = dry_run_fixture
fixture только для canonical_code = pump_diameter
unknown canonical возвращает canonical_code_not_found
```

Пишет в `AttributeContext`:

```text
canonical
errors при ошибках
stage_results.resolve_canonical
```

Fixture canonical:

```text
canonical_id = 1
canonical_code = pump_diameter
target_attribute_id = 0
target_attribute_name = Dry-run pump diameter
target_attribute_group_id = 0
target_attribute_group_name = Dry-run attributes
status = active
locked = 1
source = dry_run_fixture
```

Важно:

```text
target_attribute_id = 0 только dry-run fixture, не реальный OpenCart ID
```

Не делает:

```text
DB connection
OpenCart connection
SQL apply
real attribute_id / attribute_group_id validation
production registry
runtime config loading
```

## ResolveScopeStage

Technical name:

```text
resolve_scope
```

Текущая ответственность:

```text
разрешить scope из Attribute Job через no-DB resolver
```

Компоненты:

```text
ScopeResolverInterface
DryRunScopeResolver
```

Текущая dry-run реализация:

```text
source = dry_run_fixture
fixture только для scope.type = category и category_id = 11900213
unknown category возвращает scope_category_not_found
unsupported scope type возвращает unsupported_scope_type
```

Пишет в `AttributeContext`:

```text
scope
raw_data.products
errors при ошибках
stage_results.resolve_scope
```

Fixture scope:

```text
type = category
category_id = 11900213
category_name = Скважинные насосы
include_subcategories = 1
product_count = 1
source = dry_run_fixture
```

Fixture product:

```text
product_id = 0
model = dry-run-product
name = Dry-run product
category_ids = array(11900213)
source = dry_run_fixture
```

Важно:

```text
product_id = 0 только dry-run fixture, не реальный OpenCart ID
```

Не делает:

```text
DB connection
OpenCart connection
SQL apply
real category existence checks
real product reads
subcategory expansion
production scope resolving
```

## ExportAttributesStage

Technical name:

```text
export_attributes
```

Текущая ответственность:

```text
экспортировать dry-run facts по canonical + scope + products
```

Компоненты:

```text
AttributeExporterInterface
DryRunAttributeExporter
```

Текущая dry-run реализация:

```text
source = dry_run_fixture
fixture только для canonical_code = pump_diameter
fixture только для category_id = 11900213
fixture только для product_id = 0
```

Пишет в `AttributeContext`:

```text
raw_data.attributes
raw_data.attribute_groups
raw_data.product_attributes
attribute_name_structure.target_attribute
attribute_name_structure.found_attributes
attribute_value_structure.raw_values
stage_results.export_attributes
```

Fixture rows:

```text
attribute_id = 0
attribute_name = Dry-run pump diameter
attribute_group_id = 0
attribute_group_name = Dry-run attributes
usage_count = 1
sample_values = array('96 мм')
product_id = 0
language_id = 3
raw_text = 96 мм
source = dry_run_fixture
```

Поддерживаемые errors:

```text
canonical_missing
scope_products_empty
attribute_export_failed
product_attributes_export_failed
```

Не делает:

```text
DB connection
OpenCart connection
SQL apply
OpenCart product_attribute / attribute / attribute_description / attribute_group reads
name analysis
value normalization
SQL preview
production export
```

## AnalyzeNamesStage

Technical name:

```text
analyze_names
```

Текущая ответственность:

```text
проанализировать exported facts по именам атрибутов без production decisions
```

Компоненты:

```text
AttributeNameAnalyzerInterface
DryRunAttributeNameAnalyzer
```

Текущая dry-run реализация:

```text
source = dry_run_fixture
fixture-compatible только с canonical_code = pump_diameter
fixture-compatible только с target_attribute.attribute_id = 0
fixture-compatible только с found_attributes[0].attribute_id = 0
```

Пишет в `AttributeContext`:

```text
attribute_name_structure.target_attribute
attribute_name_structure.found_attributes
attribute_name_structure.exact_matches
attribute_name_structure.similar_name_candidates
attribute_name_structure.rejected_name_candidates
attribute_name_structure.diagnostics
synonym_candidates
stage_results.analyze_names
```

Важно:

```text
exact_matches = diagnostic fact, не synonym approval
synonym_candidates.proposed = array()
synonym_candidates.rejected = array()
synonym_candidates.ambiguous = array()
attribute_id = 0 только dry-run fixture, не реальный OpenCart ID
```

Поддерживаемые errors:

```text
target_attribute_missing
found_attributes_missing
name_analysis_failed
```

Не делает:

```text
DB connection
OpenCart connection
SQL apply
OpenCart attribute_description / attribute_group reads
value analysis
sample_values/raw_values based decisions
ValueParser calls
SQL preview
fuzzy matching
synonym approval
production analysis
```

Warnings пока не добавляет.

## AnalyzeValuesStage

Technical name:

```text
analyze_values
```

Текущая ответственность:

```text
проанализировать raw_values через dry-run value analyzer без production parser
```

Компоненты:

```text
AttributeValueAnalyzerInterface
DryRunAttributeValueAnalyzer
```

Текущая dry-run реализация:

```text
source = dry_run_fixture
fixture-compatible только с canonical_code = pump_diameter
fixture-compatible только с value_parser = diameter_mm
fixture-compatible только с raw_text = 96 мм
условно нормализует только 96 мм -> 96
```

Пишет в `AttributeContext`:

```text
attribute_value_structure.raw_values
attribute_value_structure.normalized_values
attribute_value_structure.unknown_values
attribute_value_structure.invalid_values
attribute_value_structure.empty_values
attribute_value_structure.diagnostics
value_report
stage_results.analyze_values
```

Fixture value result:

```text
product_id = 0
attribute_id = 0
raw_text = 96 мм
normalized_value = 96
unit = mm
parser = diameter_mm
source = dry_run_fixture
```

Важно:

```text
product_id = 0 и attribute_id = 0 только dry-run fixture
unknown_value_policy не интерпретируется как SQL blocker на этом этапе
SQL blockers - зона будущего BuildSqlPreviewStage
ValueParserRegistry пока не используется
```

Поддерживаемые errors:

```text
raw_values_missing
value_parser_unknown
value_analysis_failed
```

Не делает:

```text
DB connection
OpenCart connection
SQL apply
production parser
ValueParserRegistry
SQL preview
apply decisions
name analysis
arbitrary value normalization
```

Warnings пока не добавляет.

## BuildSqlPreviewStage

Technical name:

```text
build_sql_preview
```

Текущая ответственность:

```text
собрать dry-run sql_preview facts без executable SQL и без SQL apply
```

Компоненты:

```text
SqlPreviewBuilderInterface
DryRunSqlPreviewBuilder
```

Текущая dry-run реализация:

```text
source = dry_run_fixture
fixture-compatible только с canonical_code = pump_diameter
fixture-compatible только с category_id = 11900213
fixture-compatible только с normalized_values[0].product_id = 0
fixture-compatible только с normalized_values[0].attribute_id = 0
fixture-compatible только с normalized_values[0].normalized_value = 96
```

Пишет в `AttributeContext`:

```text
sql_preview
stage_results.build_sql_preview
errors при ошибках
```

`sql_preview` содержит:

```text
enabled
generated
safe_to_apply
apply_changes
source
mode
blocked_by
statements
operations
diagnostics
```

Happy path fixture:

```text
enabled = 1
generated = 1
safe_to_apply = 1
apply_changes = 0
mode = preview_only
blocked_by = array()
```

Statement:

```text
statement_type = preview_only
operation = update_product_attribute
sql = -- dry-run preview only: update product_attribute for product_id=0 attribute_id=0 normalized_value=96
source = dry_run_fixture
```

Важно:

```text
statement sql является preview-only comment, не executable SQL
output.generate_sql_preview = 0 даёт StageResult::skipped(sql_preview_disabled)
unknown_value_policy записывается как факт, но не является runtime apply-blocker
product_id = 0 и attribute_id = 0 только dry-run fixture
```

Поддерживаемые errors:

```text
sql_preview_build_failed
normalized_values_missing
apply_changes_not_allowed
```

Не делает:

```text
DB connection
OpenCart connection
SQL apply
real INSERT/UPDATE/DELETE
production SQL generator
real DB diff
real product_attribute existence checks
runtime config loading
```

Warnings пока не добавляет.

## BuildReportStage

Technical name:

```text
build_report
```

Текущая ответственность:

```text
собрать report из накопленных facts в AttributeContext
```

Компоненты:

```text
ReportBuilderInterface
DryRunReportBuilder
```

Текущая dry-run реализация:

```text
source = dry_run_fixture
mode = dry_run_report
читает только AttributeContext
не меняет AttributeContext внутри builder
не принимает новых смысловых решений
```

Пишет в `AttributeContext`:

```text
report
stage_results.build_report
errors при ошибках
```

Report содержит:

```text
job_summary
canonical_summary
scope_summary
export_summary
name_analysis_summary
value_analysis_summary
sql_preview_summary
stage_summary
errors
warnings
notes
```

Notes должны отражать:

```text
dry-run fixture only
no DB/OpenCart connection
no SQL apply
```

Важно:

```text
report только отображает накопленные facts
report не пишет файлы
report не строит SQL
BuildReportStage является safe-mode stage
builder tolerant к partial context
```

Поддерживаемые errors:

```text
report_build_failed
```

Не делает:

```text
DB connection
OpenCart connection
SQL apply
file writes
var/reports writes
template engine
Markdown/HTML rendering
production report generation
```

Warnings не добавляет, только отображает уже накопленные warnings.

## BuildFrameworkResultStage

Technical name:

```text
build_framework_result
```

Текущая ответственность:

```text
собрать финальный FrameworkResult из AttributeContext
```

Компоненты:

```text
FrameworkResultBuilderInterface
DryRunFrameworkResultBuilder
```

Контракт builder:

```text
build(AttributeContext $context)
returns wrapper-array:
  framework_result
  errors
  source
```

`FrameworkResult` public API сохраняется:

```text
getResultStatus()
getStageSummary()
```

Логика result_status:

```text
failed если AttributeContext.errors не пустой
ok_with_warnings если errors пустой и warnings не пустой
ok если errors/warnings пустые
blocked не вводится как итоговый result_status
```

Пишет в `AttributeContext`:

```text
framework_result
stage_results.build_framework_result
errors при ошибках
```

Важный порядок:

```text
stage сначала пишет StageResult build_framework_result
затем пересобирает final FrameworkResult
это сохраняет build_framework_result в final stage_results
final dry-run output содержит все 9 stages
```

FrameworkResult payload может содержать:

```text
job_summary
canonical_attribute
scope_summary
found_attributes
proposed_synonym_candidates
rejected_candidates
ambiguous_candidates
value_report
unknown_values
sql_preview
report
warnings
errors
source
mode
```

Поддерживаемые errors:

```text
framework_result_build_failed
```

Не делает:

```text
DB connection
OpenCart connection
SQL apply
production result generation
runtime config loading
repository layer
file writes
domain decisions по synonyms/sql blockers
```

Warnings не добавляет, только отображает уже накопленные warnings.

## Safe-mode stages

Safe-mode stages:

```text
build_report
build_framework_result
```

Они выполняются после upstream errors.

Ожидаемые safe-mode scenarios:

```text
unknown canonical -> resolve_canonical failed, build_report ok, build_framework_result ok
unknown category  -> resolve_scope failed, build_report ok, build_framework_result ok
disabled sql_preview -> build_sql_preview skipped, build_report ok, build_framework_result ok
```

Safe-mode stages должны быть tolerant к partial context:

```text
canonical может быть пустой
scope может быть пустой
sql_preview может отсутствовать
report может быть partial
errors/warnings/stage_results всё равно попадают в итоговые структуры
```

`PipelineEngine` не менять без отдельного решения.

## Ручные negative checks

Manual checks выполняются через временные job-файлы:

```text
config/jobs/_manual_*.php
```

Временные файлы нельзя коммитить. После проверки их нужно удалить.

Unknown canonical:

```text
заменить canonical_code на unknown_canonical
expected error: canonical_code_not_found
downstream ordinary stages: skipped
build_report: ok
build_framework_result: ok
result_status: failed
```

Unknown category:

```text
заменить category_id на 99999999
expected error: scope_category_not_found
downstream ordinary stages: skipped
build_report: ok
build_framework_result: ok
result_status: failed
```

Disabled sql_preview:

```text
установить output.generate_sql_preview = 0
upstream stages: ok
build_sql_preview: skipped
build_report: ok
build_framework_result: ok
errors_count: 0
warnings_count: 0
result_status: ok
```

Stage-specific negative checks для Export/AnalyzeNames/AnalyzeValues/BuildSqlPreview/BuildReport/BuildFrameworkResult пока не делать без отдельного test hook.

## Финальные правила

```text
не применять SQL
не подключать DB/OpenCart
не делать production logic внутри dry-run boundary
не ломать AttributeContext contract
не менять stage order
не создавать OpenCart module paths
не добавлять Composer/YAML/test framework без отдельного решения
```
