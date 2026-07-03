# Pipeline Stages

Документ описывает контракт stage pipeline в Framework Standardization.

Pipeline stages выполняют `Attribute Job` через общее рабочее состояние:

```text
Attribute Job
→ AttributeContext
→ Pipeline Stages
→ FrameworkResult
````

---

## Назначение

Pipeline stages разбивают выполнение задачи стандартизации характеристики на последовательные независимые этапы.

Каждая stage:

* выполняет одну ограниченную ответственность;
* читает данные из `AttributeContext`;
* пишет результат в `AttributeContext`;
* не вызывает другие stages напрямую;
* не хранит глобальное состояние;
* не применяет изменения к продуктивной БД.

---

## Главный принцип

Stages не общаются друг с другом напрямую.

Единственная точка обмена данными:

```text
AttributeContext
```

```text
Stage A
→ пишет данные в AttributeContext

Stage B
→ читает данные из AttributeContext
```

Это позволяет:

* контролировать порядок выполнения;
* изолировать ответственность;
* безопасно останавливать pipeline;
* строить отчёт по каждому stage;
* расширять pipeline добавлением новых stages.

---

## Общий порядок выполнения

Базовый pipeline первого этапа:

```text
1. ValidateJobStage
2. ResolveCanonicalStage
3. ResolveScopeStage
4. ExportAttributesStage
5. AnalyzeNamesStage
6. AnalyzeValuesStage
7. BuildSqlPreviewStage
8. BuildReportStage
9. BuildFrameworkResultStage
```

---

## Stage contract

Каждая stage должна иметь единый контракт выполнения.

```text
Stage
├─ stage_name
├─ reads
├─ writes
├─ preconditions
├─ errors
├─ warnings
└─ result
```

---

### stage_name

Техническое имя stage.

Пример:

```text
validate_job
resolve_canonical
export_attributes
```

---

### reads

Список разделов `AttributeContext`, которые stage читает.

---

### writes

Список разделов `AttributeContext`, которые stage может изменять.

Stage не должна писать в чужие разделы без явного правила.

---

### preconditions

Условия, которые должны быть выполнены до запуска stage.

Если preconditions не выполнены, stage не должна выполняться.

---

### errors

Ошибки, которые stage может добавить в:

```text
AttributeContext.errors
```

Критические ошибки блокируют дальнейшее выполнение pipeline.

---

### warnings

Предупреждения, которые stage может добавить в:

```text
AttributeContext.warnings
```

Предупреждения не обязательно останавливают pipeline.

---

### result

Результат stage записывается в:

```text
AttributeContext.stage_results
```

Минимальная структура результата stage:

```text
stage_results.{stage_name}
├─ status
├─ started_at
├─ finished_at
├─ errors
├─ warnings
└─ summary
```

Допустимые статусы:

```text
pending
running
ok
ok_with_warnings
failed
blocked
skipped
```

---

## 1. ValidateJobStage

Техническое имя:

```text
validate_job
```

---

### Назначение

Проверяет исходный `Attribute Job` до создания полноценного рабочего состояния.

Эта stage должна убедиться, что задача описана корректно и не содержит заведомо опасных параметров.

---

### Читает

```text
AttributeContext.job.raw_job
```

---

### Пишет

```text
AttributeContext.job
AttributeContext.source
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.validate_job
```

---

### Проверки

Stage проверяет:

* `job_id` не пустой;
* `job_id` соответствует техническому формату;
* `job_name` не пустой;
* `canonical.canonical_code` не пустой;
* `scope.type` поддерживается;
* `scope.category_id` задан для `scope.type = category`;
* `source.type` поддерживается;
* `source.language_id` задан;
* `value_rules.value_parser` задан;
* `value_rules.unknown_value_policy` допустим;
* `output.apply_changes = 0`.

---

### Ошибки

Критические ошибки:

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

---

### Предупреждения

Возможные предупреждения:

```text
comment_empty
include_subcategories_enabled
max_sample_values_low
```

---

### Условие продолжения

Pipeline может продолжаться только если:

```text
AttributeContext.errors пустой
```

---

## 2. ResolveCanonicalStage

Техническое имя:

```text
resolve_canonical
```

---

### Назначение

Разрешает `canonical_code` из `Attribute Job` в зарегистрированный канонический атрибут.

Stage читает registry канонов:

```text
{DB_PREFIX}canonical_attributes
```

и сверяет его с текущими таблицами OpenCart.

---

### Читает

```text
AttributeContext.job.raw_job.canonical
AttributeContext.source.db_prefix
AttributeContext.source.language_id
```

---

### Пишет

```text
AttributeContext.canonical
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.resolve_canonical
```

---

### Проверки

Stage проверяет:

* `canonical_code` существует в `{DB_PREFIX}canonical_attributes`;
* `target_attribute_id` существует в `{DB_PREFIX}attribute`;
* `target_attribute_group_id` существует в `{DB_PREFIX}attribute_group`;
* `target_attribute_id` относится к `target_attribute_group_id`;
* текущее имя атрибута совпадает с `target_attribute_name`;
* текущее имя группы совпадает с `target_attribute_group_name`;
* `status` допустим;
* `locked` допустим.

---

### Ошибки

Критические ошибки:

```text
canonical_code_not_found
target_attribute_id_not_found
target_attribute_group_id_not_found
attribute_group_mismatch
target_attribute_name_mismatch
target_attribute_group_name_mismatch
canonical_status_invalid
canonical_locked_invalid
```

---

### Предупреждения

Возможные предупреждения:

```text
canonical_status_is_draft
canonical_not_locked
```

---

### Условие продолжения

Pipeline может продолжаться только если канон успешно разрешён.

---

## 3. ResolveScopeStage

Техническое имя:

```text
resolve_scope
```

---

### Назначение

Определяет фактическую область анализа.

Для `scope.type = category` stage находит товары, которые входят в указанную категорию.

---

### Читает

```text
AttributeContext.job.raw_job.scope
AttributeContext.source
```

---

### Пишет

```text
AttributeContext.scope
AttributeContext.raw_data.products
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.resolve_scope
```

---

### Проверки

Stage проверяет:

* категория существует;
* имя категории совпадает с контрольным `category_name`, если оно задано;
* при `include_subcategories = 1` подкатегории определены явно;
* в scope найдены товары.

---

### Ошибки

Критические ошибки:

```text
scope_category_not_found
scope_category_name_mismatch
scope_products_not_found
unsupported_scope_type
```

---

### Предупреждения

Возможные предупреждения:

```text
include_subcategories_enabled
scope_product_count_large
scope_product_count_small
```

---

### Результат

Stage заполняет:

```text
scope.product_ids
scope.product_count
raw_data.products
```

---

## 4. ExportAttributesStage

Техническое имя:

```text
export_attributes
```

---

### Назначение

Читает фактические атрибуты и значения товаров из OpenCart в рамках scope.

Stage является read-only по отношению к БД.

Она не анализирует синонимы и не нормализует значения.

---

### Читает

```text
AttributeContext.scope.product_ids
AttributeContext.canonical
AttributeContext.source
```

---

### Пишет

```text
AttributeContext.raw_data.attributes
AttributeContext.raw_data.attribute_groups
AttributeContext.raw_data.product_attributes
AttributeContext.attribute_name_structure.target_attribute
AttributeContext.attribute_name_structure.found_attributes
AttributeContext.attribute_value_structure.raw_values
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.export_attributes
```

---

### Проверки

Stage проверяет:

* scope содержит товары;
* `target_attribute_id` существует в выгруженных или доступных атрибутах;
* значения читаются по нужному `language_id`.

---

### Ошибки

Критические ошибки:

```text
scope_products_empty
attribute_export_failed
product_attributes_export_failed
language_id_not_found
```

---

### Предупреждения

Возможные предупреждения:

```text
target_attribute_not_used_in_scope
no_product_attributes_found
attribute_has_empty_values
```

---

### Результат

Stage заполняет сырые данные:

```text
raw_data.attributes
raw_data.attribute_groups
raw_data.product_attributes
attribute_name_structure.found_attributes
attribute_value_structure.raw_values
```

---

## 5. AnalyzeNamesStage

Техническое имя:

```text
analyze_names
```

---

### Назначение

Анализирует найденные имена атрибутов и формирует кандидатов в возможные синонимы.

Важно:

```text
AnalyzeNamesStage не утверждает синонимы.
```

Она только предлагает кандидатов для ручной проверки инженером.

---

### Читает

```text
AttributeContext.canonical
AttributeContext.attribute_name_structure.target_attribute
AttributeContext.attribute_name_structure.found_attributes
AttributeContext.raw_data.product_attributes
AttributeContext.job.raw_job.analysis_rules
```

---

### Пишет

```text
AttributeContext.attribute_name_structure.exact_matches
AttributeContext.attribute_name_structure.similar_name_candidates
AttributeContext.attribute_name_structure.rejected_name_candidates
AttributeContext.attribute_name_structure.diagnostics
AttributeContext.synonym_candidates.proposed
AttributeContext.synonym_candidates.rejected
AttributeContext.synonym_candidates.ambiguous
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.analyze_names
```

---

### Правила анализа

Stage может использовать:

* точное совпадение имени;
* похожесть имени;
* группу атрибута;
* `usage_count`;
* наличие совместимых sample values;
* ручные правила из `Attribute Job`.

Частотность используется только как диагностика.

```text
usage_count
→ диагностический сигнал
→ не автоматический выбор канона
```

---

### Ошибки

Обычно stage не должна создавать критические ошибки, если данные доступны.

Возможные критические ошибки:

```text
name_analysis_failed
found_attributes_missing
```

---

### Предупреждения

Возможные предупреждения:

```text
similar_attributes_found
ambiguous_name_candidates_found
target_attribute_has_low_usage_count
most_frequent_attribute_is_not_target
```

---

### Результат

Stage формирует:

```text
proposed synonym candidates
rejected candidates
ambiguous candidates
name diagnostics
```

---

## 6. AnalyzeValuesStage

Техническое имя:

```text
analyze_values
```

---

### Назначение

Анализирует и нормализует значения найденных атрибутов через `ValueParser`.

Stage работает только со значениями.

Она не принимает решений о синонимах и не меняет имена атрибутов.

---

### Читает

```text
AttributeContext.attribute_value_structure.raw_values
AttributeContext.job.raw_job.value_rules
AttributeContext.synonym_candidates
```

---

### Пишет

```text
AttributeContext.attribute_value_structure.normalized_values
AttributeContext.attribute_value_structure.unknown_values
AttributeContext.attribute_value_structure.invalid_values
AttributeContext.attribute_value_structure.empty_values
AttributeContext.attribute_value_structure.diagnostics
AttributeContext.value_report
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.analyze_values
```

---

### Правила анализа

Stage применяет `value_parser` из `Attribute Job`.

Пример:

```text
"96 мм" → 96
"4\""   → 101.6
```

Результаты делятся на:

```text
normalized
unknown
invalid
empty
```

---

### Ошибки

Критические ошибки:

```text
value_parser_unknown
value_parser_failed
raw_values_missing
```

Если `unknown_value_policy = block_sql`, наличие unknown values не обязательно останавливает весь pipeline, но должно блокировать безопасный SQL preview.

---

### Предупреждения

Возможные предупреждения:

```text
unknown_values_found
invalid_values_found
empty_values_found
value_normalization_partial
```

---

### Результат

Stage формирует:

```text
attribute_value_structure
value_report
unknown_values
invalid_values
empty_values
```

---

## 7. BuildSqlPreviewStage

Техническое имя:

```text
build_sql_preview
```

---

### Назначение

Готовит SQL preview для ручной проверки инженером.

Stage не выполняет SQL.

```text
SQL preview
→ только текст для проверки
→ не автоматическое применение
```

---

### Читает

```text
AttributeContext.canonical
AttributeContext.scope
AttributeContext.synonym_candidates
AttributeContext.attribute_value_structure
AttributeContext.value_report
AttributeContext.errors
AttributeContext.warnings
AttributeContext.job.raw_job.output
```

---

### Пишет

```text
AttributeContext.sql_preview
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.build_sql_preview
```

---

### Preconditions

Stage может выполняться только если:

```text
output.generate_sql_preview = 1
```

Если SQL preview не запрошен, stage получает статус:

```text
skipped
```

---

### Правила безопасности

SQL preview не считается безопасным, если:

```text
errors не пустой
unknown_value_policy = block_sql и есть unknown_values
есть proposed_synonym_candidates без ручного утверждения
есть ambiguous_candidates
canonical.status != active
canonical.locked != 1
output.apply_changes = 1
```

---

### Ошибки

Критические ошибки:

```text
sql_preview_build_failed
apply_changes_not_allowed
```

---

### Блокировки

Блокировки SQL preview:

```text
errors_exist
unknown_values_exist
manual_approval_required
ambiguous_candidates_exist
canonical_not_active
canonical_not_locked
```

---

### Результат

Stage заполняет:

```text
sql_preview.enabled
sql_preview.generated
sql_preview.safe_to_apply
sql_preview.blocked_by
sql_preview.statements
```

Даже если `safe_to_apply = 1`, Framework не применяет SQL автоматически.

---

## 8. BuildReportStage

Техническое имя:

```text
build_report
```

---

### Назначение

Формирует человекочитаемый отчёт для инженера.

Отчёт должен объяснять не только результат, но и причины предупреждений и блокировок.

---

### Читает

```text
AttributeContext.job
AttributeContext.canonical
AttributeContext.scope
AttributeContext.attribute_name_structure
AttributeContext.attribute_value_structure
AttributeContext.synonym_candidates
AttributeContext.value_report
AttributeContext.sql_preview
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results
```

---

### Пишет

```text
AttributeContext.report
AttributeContext.stage_results.build_report
```

---

### Содержание отчёта

Минимально отчёт должен содержать:

* название задачи;
* канонический атрибут;
* scope анализа;
* количество товаров;
* найденные атрибуты;
* usage count;
* sample values;
* предложенные синонимы;
* спорные кандидаты;
* отчёт по значениям;
* unknown / invalid / empty values;
* warnings;
* errors;
* SQL preview или причины его блокировки.

---

### Ошибки

Критические ошибки:

```text
report_build_failed
```

---

### Результат

Stage формирует:

```text
AttributeContext.report
```

---

## 9. BuildFrameworkResultStage

Техническое имя:

```text
build_framework_result
```

---

### Назначение

Формирует финальный `FrameworkResult` из `AttributeContext`.

Эта stage не создаёт новых смысловых данных.

Она только собирает финальную проекцию для инженера.

---

### Читает

```text
AttributeContext
```

---

### Пишет

```text
FrameworkResult
AttributeContext.stage_results.build_framework_result
```

---

### Правила result_status

`result_status = failed`, если:

```text
AttributeContext.errors не пустой
```

`result_status = blocked`, если критических ошибок нет, но:

```text
sql_preview.safe_to_apply = 0
или sql_preview.blocked_by не пустой
```

`result_status = ok_with_warnings`, если:

```text
errors пустой
blocked_by пустой
warnings не пустой
```

`result_status = ok`, если:

```text
errors пустой
blocked_by пустой
warnings пустой
```

---

### Ошибки

Критические ошибки:

```text
framework_result_build_failed
```

---

### Результат

Формируется финальная структура:

```text
FrameworkResult
├─ result_status
├─ job_summary
├─ canonical_attribute
├─ scope_summary
├─ found_attributes
├─ proposed_synonym_candidates
├─ rejected_candidates
├─ ambiguous_candidates
├─ value_report
├─ unknown_values
├─ sql_preview
├─ warnings
├─ errors
├─ stage_summary
└─ report
```

---

## Правила остановки pipeline

Pipeline должен остановиться после stage, если:

```text
AttributeContext.errors содержит критические ошибки
```

Исключение:

```text
BuildReportStage
BuildFrameworkResultStage
```

Эти stages могут выполняться в безопасном режиме, чтобы инженер получил отчёт об ошибке.

---

## Безопасный режим

Если pipeline заблокирован критической ошибкой, Framework может перейти в safe mode.

В safe mode разрешено:

* сформировать отчёт;
* сформировать `FrameworkResult`;
* показать ошибки;
* показать частично собранные данные.

В safe mode запрещено:

* генерировать SQL как безопасный;
* считать результат применимым;
* применять изменения;
* утверждать синонимы.

---

## Правила записи в AttributeContext

Каждая stage пишет только в свои разрешённые разделы.

Примеры:

```text
ValidateJobStage
→ job
→ source
→ warnings
→ errors
→ stage_results.validate_job
```

```text
ResolveCanonicalStage
→ canonical
→ warnings
→ errors
→ stage_results.resolve_canonical
```

```text
ExportAttributesStage
→ raw_data
→ attribute_name_structure.found_attributes
→ attribute_value_structure.raw_values
→ stage_results.export_attributes
```

```text
AnalyzeNamesStage
→ attribute_name_structure diagnostics
→ synonym_candidates
→ warnings
→ stage_results.analyze_names
```

```text
AnalyzeValuesStage
→ attribute_value_structure
→ value_report
→ warnings
→ stage_results.analyze_values
```

```text
BuildSqlPreviewStage
→ sql_preview
→ warnings
→ stage_results.build_sql_preview
```

```text
BuildReportStage
→ report
→ stage_results.build_report
```

```text
BuildFrameworkResultStage
→ FrameworkResult
→ stage_results.build_framework_result
```

---

## Что stage не должна делать

Stage не должна:

* вызывать следующую stage напрямую;
* применять SQL;
* изменять продуктивную БД;
* утверждать синонимы;
* создавать канонический атрибут;
* изменять импорты;
* читать или писать глобальное состояние вне `AttributeContext`;
* скрывать ошибки от инженера.

---

## Расширение pipeline

Pipeline может расширяться новыми stages.

Новая stage должна быть добавлена только если:

* у неё есть отдельная ответственность;
* понятно, какие разделы `AttributeContext` она читает;
* понятно, какие разделы `AttributeContext` она пишет;
* она не дублирует существующую stage;
* она не нарушает принцип обмена через `AttributeContext`.

Примеры будущих stages:

```text
ManualApprovalStage
CanonicalResolverStage
ImportCompatibilityCheckStage
SqlDiffReviewStage
```

---

## Минимальный успешный сценарий

```text
ValidateJobStage
→ ok

ResolveCanonicalStage
→ ok

ResolveScopeStage
→ ok

ExportAttributesStage
→ ok

AnalyzeNamesStage
→ ok / ok_with_warnings

AnalyzeValuesStage
→ ok / ok_with_warnings

BuildSqlPreviewStage
→ generated / blocked

BuildReportStage
→ ok

BuildFrameworkResultStage
→ ok
```

---

## Статус документа

Документ является архитектурным контрактом stage pipeline.

Код реализации должен следовать этому контракту.


