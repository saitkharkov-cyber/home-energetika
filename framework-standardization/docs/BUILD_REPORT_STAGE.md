# Build Report Stage

Документ описывает контракт `BuildReportStage` — stage формирования человекочитаемого отчёта для инженера в Framework Standardization.

`BuildReportStage` работает после анализа имён, анализа значений и формирования SQL preview.

```text
AnalyzeNamesStage
→ AnalyzeValuesStage
→ BuildSqlPreviewStage
→ BuildReportStage
````

---

## Назначение

`BuildReportStage` формирует итоговый человекочитаемый отчёт по одному запуску `Attribute Job`.

Главная задача stage:

```text
AttributeContext
→ readable report
→ инженер понимает, что найдено, что предложено, что заблокировано и почему
```

Отчёт нужен для ручной проверки перед любыми действиями с данными.

---

## Главный принцип

`BuildReportStage` не принимает решений и не меняет данные.

Она только собирает уже имеющуюся информацию из `AttributeContext` и представляет её в понятной форме.

```text
AttributeContext
→ BuildReportStage
→ AttributeContext.report
```

Stage не должна скрывать warnings, errors или blockers.

---

## Место в pipeline

Порядок выполнения:

```text
ValidateJobStage
→ ResolveCanonicalStage
→ ResolveScopeStage
→ ExportAttributesStage
→ AnalyzeNamesStage
→ AnalyzeValuesStage
→ BuildSqlPreviewStage
→ BuildReportStage
→ BuildFrameworkResultStage
```

`BuildReportStage` может выполняться даже в safe mode, если предыдущие stages завершились с ошибками.

Цель safe mode — дать инженеру отчёт о том, почему выполнение не может продолжаться безопасно.

---

## Техническое имя stage

```text
build_report
```

Результат выполнения записывается в:

```text
AttributeContext.stage_results.build_report
```

---

## Ответственность BuildReportStage

Stage отвечает за:

* формирование человекочитаемого отчёта;
* сбор краткой информации о задаче;
* вывод канонического атрибута;
* вывод scope анализа;
* вывод найденных атрибутов;
* вывод кандидатов в синонимы;
* вывод отчёта по значениям;
* вывод unknown / invalid / empty values;
* вывод warnings;
* вывод errors;
* вывод SQL preview;
* вывод причин блокировки SQL preview;
* вывод stage summary;
* подготовку отчёта для `FrameworkResult`.

---

## Что BuildReportStage не делает

`BuildReportStage` не должна:

* читать БД напрямую;
* изменять БД;
* выполнять SQL;
* формировать новые SQL statements;
* утверждать синонимы;
* выбирать канон;
* нормализовать значения;
* изменять `synonym_candidates`;
* изменять `value_report`;
* менять импорты;
* скрывать ошибки от инженера.

---

## Читает из AttributeContext

```text
AttributeContext.job
AttributeContext.canonical
AttributeContext.scope
AttributeContext.source
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

## Пишет в AttributeContext

```text
AttributeContext.report
AttributeContext.stage_results.build_report
```

Stage не должна изменять смысловые данные, которые были сформированы предыдущими stages.

---

## Структура report

Минимальная структура:

```text
report
├─ format
├─ title
├─ summary
├─ canonical_section
├─ scope_section
├─ found_attributes_section
├─ synonym_candidates_section
├─ value_report_section
├─ sql_preview_section
├─ warnings_section
├─ errors_section
├─ stage_summary_section
└─ final_notes
```

---

## format

Формат отчёта.

Допустимые значения первого этапа:

```text
markdown
text
```

Рекомендуемый формат:

```text
markdown
```

---

## title

Заголовок отчёта.

Пример:

```text
Attribute Standardization Report: pump_diameter_borehole_pumps
```

---

## summary

Краткая сводка результата.

Минимально должна содержать:

```text
job_id
job_name
canonical_code
target_attribute_id
scope
product_count
result blockers
warnings count
errors count
sql_preview status
```

Пример:

```markdown
## Summary

- Job: `pump_diameter_borehole_pumps`
- Canonical code: `pump_diameter`
- Target attribute: `123 / Диаметр насоса`
- Scope: category `11900213 / Скважинные насосы`
- Products in scope: `120`
- Proposed synonym candidates: `2`
- Unknown values: `3`
- SQL preview: blocked
```

---

## canonical_section

Раздел канонического атрибута.

Должен содержать:

```text
canonical_id
canonical_code
target_attribute_id
target_attribute_name
target_attribute_group_id
target_attribute_group_name
status
locked
```

Пример:

```markdown
## Canonical Attribute

| Field | Value |
|-------|-------|
| canonical_code | pump_diameter |
| target_attribute_id | 123 |
| target_attribute_name | Диаметр насоса |
| target_attribute_group_name | Насосы |
| status | active |
| locked | 1 |
```

---

## scope_section

Раздел области анализа.

Должен содержать:

```text
scope.type
scope.category_id
scope.category_name
scope.include_subcategories
scope.product_count
source.database
source.language_id
source.db_prefix
```

Пример:

```markdown
## Scope

| Field | Value |
|-------|-------|
| type | category |
| category_id | 11900213 |
| category_name | Скважинные насосы |
| include_subcategories | 1 |
| product_count | 120 |
| source | local |
| language_id | 3 |
```

---

## found_attributes_section

Раздел найденных атрибутов.

Должен показывать все найденные в scope атрибуты, которые участвовали в анализе.

Минимальные поля:

```text
attribute_id
attribute_name
attribute_group_name
usage_count
sample_values
diagnostics
```

Пример:

```markdown
## Found Attributes

| attribute_id | attribute_name | group | usage_count | sample_values |
|--------------|----------------|-------|-------------|---------------|
| 123 | Диаметр насоса | Насосы | 12 | 96 мм, 101 мм |
| 456 | Диаметр корпуса | Насосы | 8 | 96, 101 |
```

`usage_count` должен быть показан как диагностика, а не как автоматическое решение.

---

## synonym_candidates_section

Раздел кандидатов в синонимы.

Должен содержать три группы:

```text
proposed
ambiguous
rejected
```

---

### Proposed candidates

Кандидаты, которые Framework предлагает инженеру рассмотреть.

Минимальные поля:

```text
attribute_id
attribute_name
attribute_group_name
usage_count
sample_values
reason
confidence
```

Важно явно указать:

```text
proposed candidates are not approved automatically
```

Пример:

```markdown
## Proposed Synonym Candidates

| attribute_id | name | group | usage_count | reason | confidence |
|--------------|------|-------|-------------|--------|------------|
| 456 | Диаметр корпуса | Насосы | 8 | similar_name_and_compatible_samples | medium |
```

---

### Ambiguous candidates

Кандидаты, которые требуют ручной проверки.

Пример:

```markdown
## Ambiguous Candidates

| attribute_id | name | reason |
|--------------|------|--------|
| 777 | Диаметр | name_is_too_generic |
```

---

### Rejected candidates

Отклонённые кандидаты.

Пример:

```markdown
## Rejected Candidates

| attribute_id | name | reason |
|--------------|------|--------|
| 789 | Диаметр патрубка | different_meaning |
```

---

## value_report_section

Раздел отчёта по значениям.

Должен содержать:

```text
parser
value_type
unit
total_values
normalized_count
unknown_count
invalid_count
empty_count
unique_normalized_values
examples
```

Пример:

```markdown
## Value Report

| Metric | Value |
|--------|-------|
| parser | diameter_mm |
| value_type | decimal |
| unit | mm |
| total_values | 20 |
| normalized_count | 17 |
| unknown_count | 2 |
| invalid_count | 1 |
| empty_count | 0 |
```

---

## unknown / invalid / empty values

Если есть unknown, invalid или empty values, они должны быть явно показаны.

### Unknown values

```markdown
## Unknown Values

| product_id | attribute_id | raw_text | reason |
|------------|--------------|----------|--------|
| 1003 | 456 | около 100 мм | ambiguous_value |
```

### Invalid values

```markdown
## Invalid Values

| product_id | attribute_id | raw_text | reason |
|------------|--------------|----------|--------|
| 1004 | 456 | abc | not_numeric |
```

### Empty values

```markdown
## Empty Values

| product_id | attribute_id | raw_text | reason |
|------------|--------------|----------|--------|
| 1005 | 456 | - | empty_value |
```

---

## sql_preview_section

Раздел SQL preview.

Должен содержать:

```text
enabled
generated
safe_to_apply
blocked_by
statements
notes
```

Важно явно написать:

```text
SQL preview is for manual review only.
Framework does not execute SQL automatically.
```

Пример:

```markdown
## SQL Preview

| Field | Value |
|-------|-------|
| enabled | 1 |
| generated | 1 |
| safe_to_apply | 0 |
| blocked_by | manual_approval_required, unknown_values_exist |
```

Если есть statements, они должны быть выведены отдельными блоками.

Пример:

````markdown
### Statement 1: diagnostic_select

Description: Show products using source attribute candidate.

```sql
SELECT
  pa.product_id,
  pa.attribute_id,
  pa.language_id,
  pa.text
FROM `{DB_PREFIX}product_attribute` pa
WHERE pa.attribute_id = 456
  AND pa.product_id IN (...);
````

````

---

## warnings_section

Раздел предупреждений.

Все warnings должны быть выведены явно.

Пример:

```markdown
## Warnings

- canonical_not_locked
- manual_approval_required
- unknown_values_found
````

Warnings не должны теряться при формировании отчёта.

---

## errors_section

Раздел ошибок.

Если есть ошибки, они должны быть выведены явно и заметно.

Пример:

```markdown
## Errors

- canonical_code_not_found
- value_parser_unknown
```

Если errors не пустой, отчёт должен явно указать, что результат не пригоден для применения.

---

## stage_summary_section

Раздел прохождения stages.

Должен содержать:

```text
stage_name
status
warnings
errors
summary
```

Пример:

```markdown
## Stage Summary

| Stage | Status |
|-------|--------|
| validate_job | ok |
| resolve_canonical | ok |
| resolve_scope | ok |
| export_attributes | ok |
| analyze_names | ok_with_warnings |
| analyze_values | ok_with_warnings |
| build_sql_preview | blocked |
```

---

## final_notes

Финальные пояснения инженеру.

Минимально:

```markdown
## Final Notes

- This report is for manual engineering review.
- Framework does not apply SQL automatically.
- Proposed synonym candidates require manual approval.
- Backup database before applying any SQL manually.
```

---

## Preconditions

Stage может выполняться, если:

```text
AttributeContext.job заполнен
AttributeContext.canonical может быть заполнен или отсутствовать при safe mode
AttributeContext.stage_results содержит данные предыдущих stages
```

В отличие от аналитических stages, `BuildReportStage` должна уметь работать в safe mode.

Если часть данных отсутствует из-за ошибки предыдущей stage, отчёт должен показать это, а не падать без объяснения.

---

## Safe mode

`BuildReportStage` может выполняться в safe mode, если pipeline был остановлен критической ошибкой.

В safe mode отчёт должен содержать:

* какие stages успели выполниться;
* на какой stage произошла ошибка;
* какие данные отсутствуют;
* почему SQL preview не был сформирован;
* какие действия нужны инженеру.

Пример:

```markdown
## Safe Mode Report

Pipeline stopped at `resolve_canonical`.

Reason:

- canonical_code_not_found

SQL preview was not generated.
```

---

## Ошибки BuildReportStage

Критические ошибки самой stage:

```text
report_build_failed
report_template_invalid
```

Эти ошибки записываются в:

```text
AttributeContext.errors
AttributeContext.stage_results.build_report.errors
```

Но stage должна быть максимально устойчивой: даже при неполных данных лучше сформировать частичный отчёт, чем полностью упасть.

---

## Предупреждения BuildReportStage

Возможные предупреждения:

```text
report_generated_in_safe_mode
report_has_missing_sections
sql_preview_not_generated
```

---

## Stage result

Результат stage записывается в:

```text
AttributeContext.stage_results.build_report
```

Минимальная структура:

```text
status
started_at
finished_at
errors
warnings
summary
```

Пример:

```yaml
status: ok_with_warnings
started_at: 2026-07-03 12:25:00
finished_at: 2026-07-03 12:25:01
errors: []
warnings:
  - report_generated_in_safe_mode
summary:
  format: markdown
  sections: 10
  safe_mode: 1
```

---

## Пример report

```markdown
# Attribute Standardization Report

## Summary

- Job: `pump_diameter_borehole_pumps`
- Canonical code: `pump_diameter`
- Scope: category `11900213 / Скважинные насосы`
- Products in scope: `120`
- SQL preview: blocked

## Canonical Attribute

| Field | Value |
|-------|-------|
| canonical_code | pump_diameter |
| target_attribute_id | 123 |
| target_attribute_name | Диаметр насоса |
| status | active |
| locked | 1 |

## Proposed Synonym Candidates

| attribute_id | name | reason | confidence |
|--------------|------|--------|------------|
| 456 | Диаметр корпуса | similar_name_and_compatible_samples | medium |

## Value Report

| Metric | Value |
|--------|-------|
| total_values | 20 |
| normalized_count | 17 |
| unknown_count | 2 |
| invalid_count | 1 |

## SQL Preview

SQL preview is blocked.

Blocked by:

- manual_approval_required
- unknown_values_exist

## Final Notes

- Framework does not execute SQL automatically.
- Proposed candidates require manual approval.
```

---

## Влияние на FrameworkResult

`FrameworkResult` должен получить из `BuildReportStage`:

```text
report
stage_results.build_report
```

`FrameworkResult.report` является финальной человекочитаемой проекцией отчёта.

---

## Граница с BuildSqlPreviewStage

`BuildSqlPreviewStage` формирует:

```text
AttributeContext.sql_preview
```

`BuildReportStage` только отображает этот блок в отчёте.

Она не должна менять `safe_to_apply`, `blocked_by` или `statements`.

---

## Граница с BuildFrameworkResultStage

`BuildFrameworkResultStage` использует готовый report как часть финального результата.

`BuildReportStage` не должна сама определять итоговый `result_status`, если это уже относится к `BuildFrameworkResultStage`.

---

## Требования к реализации

Реализация `BuildReportStage` должна быть:

* устойчивой к неполным данным;
* пригодной для safe mode;
* читаемой для инженера;
* детерминированной;
* без записи в БД;
* без выполнения SQL;
* без изменения смысловых результатов предыдущих stages;
* расширяемой новыми разделами отчёта.

---

## Статус документа

Документ является архитектурным контрактом `BuildReportStage`.

Код реализации должен следовать этому контракту.
