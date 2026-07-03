# Build Framework Result Stage

Документ описывает контракт `BuildFrameworkResultStage` — финальной stage сборки `FrameworkResult` в Framework Standardization.

`BuildFrameworkResultStage` работает после формирования отчёта.

```text
BuildSqlPreviewStage
→ BuildReportStage
→ BuildFrameworkResultStage
````

---

## Назначение

`BuildFrameworkResultStage` формирует финальный результат одного запуска Framework.

Главная задача stage:

```text
AttributeContext
→ FrameworkResult
```

`FrameworkResult` нужен инженеру как итоговая структурированная проекция выполнения `Attribute Job`.

Он показывает:

* итоговый статус;
* что было обработано;
* какой канон использовался;
* какой scope анализировался;
* какие атрибуты найдены;
* какие кандидаты предложены;
* какие значения нормализованы;
* какие warnings/errors есть;
* был ли сформирован SQL preview;
* почему результат заблокирован или пригоден только для ручной проверки.

---

## Главный принцип

`FrameworkResult` не является новым источником истины.

Он формируется из уже заполненного:

```text
AttributeContext
```

Stage не должна создавать новые смысловые данные.

Она только собирает финальную проекцию.

```text
AttributeContext
→ BuildFrameworkResultStage
→ FrameworkResult
```

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

`BuildFrameworkResultStage` является финальной stage pipeline.

Она может выполняться в safe mode, чтобы инженер получил результат даже при ошибке на предыдущих этапах.

---

## Техническое имя stage

```text
build_framework_result
```

Результат выполнения записывается в:

```text
AttributeContext.stage_results.build_framework_result
```

Финальный объект:

```text
FrameworkResult
```

---

## Ответственность BuildFrameworkResultStage

Stage отвечает за:

* сбор финального `FrameworkResult`;
* детерминированное определение `result_status` из `AttributeContext`;
* перенос краткой информации о задаче;
* перенос данных канонического атрибута;
* перенос scope summary;
* перенос найденных атрибутов;
* перенос кандидатов в синонимы;
* перенос value report;
* перенос unknown / invalid / empty values;
* перенос SQL preview;
* перенос warnings;
* перенос errors;
* перенос stage summary;
* перенос человекочитаемого report;
* подготовку итогового результата для инженера.

---

## Что BuildFrameworkResultStage не делает

`BuildFrameworkResultStage` не должна:

* читать БД напрямую;
* изменять БД;
* выполнять SQL;
* создавать SQL preview;
* утверждать синонимы;
* выбирать канон;
* нормализовать значения;
* анализировать имена;
* менять данные предыдущих stages;
* скрывать warnings/errors/blockers;
* изменять импорты.

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
AttributeContext.report
```

---

## Пишет

```text
FrameworkResult
AttributeContext.stage_results.build_framework_result
```

Stage не должна изменять смысловые разделы `AttributeContext`, сформированные предыдущими stages.

---

## Минимальная структура FrameworkResult

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
├─ invalid_values
├─ empty_values
├─ sql_preview
├─ warnings
├─ errors
├─ stage_summary
└─ report
```

---

## result_status

Итоговый статус выполнения.

`BuildFrameworkResultStage` не принимает новое смысловое решение о статусе.

`result_status` выводится детерминированно из данных, уже накопленных в `AttributeContext`: errors, warnings, blockers, SQL preview и результатов предыдущих stages.

Допустимые значения первого этапа:

```text
ok
ok_with_warnings
blocked
failed
```

---

### failed

Устанавливается, если:

```text
AttributeContext.errors не пустой
```

`failed` означает, что pipeline столкнулся с критической ошибкой.

Примеры:

```text
canonical_code_not_found
target_attribute_id_not_found
scope_category_not_found
value_parser_unknown
report_build_failed
```

---

### blocked

Устанавливается, если критических ошибок нет, но результат нельзя считать безопасным для применения.

Примеры причин:

```text
manual_approval_required
ambiguous_candidates_exist
unknown_values_exist
invalid_values_exist
canonical_not_active
canonical_not_locked
sql_preview_blocked
```

Обычно `blocked` возникает, если:

```text
sql_preview.safe_to_apply = 0
```

или:

```text
sql_preview.blocked_by не пустой
```

---

### ok_with_warnings

Устанавливается, если:

```text
errors пустой
blocked_by пустой
warnings не пустой
```

`ok_with_warnings` означает, что критических ошибок и блокировок нет, но инженер должен обратить внимание на предупреждения.

---

### ok

Устанавливается, если:

```text
errors пустой
blocked_by пустой
warnings пустой
```

Даже при `ok` Framework не применяет SQL автоматически.

---

## Правило приоритета result_status

Статус определяется по приоритету:

```text
1. failed
2. blocked
3. ok_with_warnings
4. ok
```

То есть:

* ошибки имеют самый высокий приоритет;
* блокировки важнее warnings;
* warnings важнее чистого ok.

---

## job_summary

Краткая информация об исходной задаче.

```text
job_summary
├─ job_id
├─ job_name
├─ source
├─ language_id
└─ created_at
```

Источник:

```text
AttributeContext.job
AttributeContext.source
```

---

## canonical_attribute

Информация о каноническом атрибуте.

```text
canonical_attribute
├─ canonical_id
├─ canonical_code
├─ target_attribute_id
├─ target_attribute_name
├─ target_attribute_group_id
├─ target_attribute_group_name
├─ status
└─ locked
```

Источник:

```text
AttributeContext.canonical
```

Если canonical не был разрешён из-за ошибки, section может быть пустой или частично заполненной, но ошибка должна быть отражена в `errors`.

---

## scope_summary

Краткая информация об области анализа.

```text
scope_summary
├─ type
├─ category_id
├─ category_name
├─ include_subcategories
└─ product_count
```

Источник:

```text
AttributeContext.scope
```

---

## found_attributes

Список найденных атрибутов, участвовавших в анализе.

Источник:

```text
AttributeContext.attribute_name_structure.found_attributes
```

Минимальная структура элемента:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
usage_count
sample_values
diagnostics
```

`usage_count` остаётся диагностическим сигналом и не является автоматическим критерием выбора канона.

---

## proposed_synonym_candidates

Кандидаты, которые Framework предлагает инженеру рассмотреть как возможные синонимы.

Источник:

```text
AttributeContext.synonym_candidates.proposed
```

Важно:

```text
proposed_synonym_candidates
→ не утверждены
→ требуют ручной проверки
→ не применяются автоматически
```

---

## rejected_candidates

Кандидаты, которые были рассмотрены и отклонены stage анализа имён.

Источник:

```text
AttributeContext.synonym_candidates.rejected
```

---

## ambiguous_candidates

Кандидаты, по которым Framework не может дать уверенное предложение.

Источник:

```text
AttributeContext.synonym_candidates.ambiguous
```

Если `ambiguous_candidates` не пустой, результат обычно должен быть `blocked`.

---

## value_report

Отчёт по значениям.

Источник:

```text
AttributeContext.value_report
```

Минимальная структура:

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

---

## unknown_values

Значения, которые parser не смог уверенно распознать.

Источник:

```text
AttributeContext.attribute_value_structure.unknown_values
```

Если:

```text
unknown_value_policy = block_sql
```

и `unknown_values` не пустой, результат должен быть заблокирован.

---

## invalid_values

Значения, нарушающие ожидаемый формат, тип или диапазон.

Источник:

```text
AttributeContext.attribute_value_structure.invalid_values
```

Если `invalid_values` не пустой, результат обычно должен быть `blocked`.

---

## empty_values

Пустые значения или технические маркеры пустоты.

Источник:

```text
AttributeContext.attribute_value_structure.empty_values
```

Поведение зависит от `allow_empty`.

---

## sql_preview

SQL preview для ручной проверки.

Источник:

```text
AttributeContext.sql_preview
```

Минимальная структура:

```text
enabled
generated
safe_to_apply
blocked_by
statements
notes
```

Важно:

```text
safe_to_apply = 1
```

не означает автоматическое применение SQL.

Framework всё равно не выполняет SQL сам.

---

## warnings

Список предупреждений.

Источник:

```text
AttributeContext.warnings
```

Warnings должны быть перенесены без потерь.

---

## errors

Список ошибок.

Источник:

```text
AttributeContext.errors
```

Errors должны быть перенесены без потерь.

Если `errors` не пустой, `result_status` должен быть:

```text
failed
```

---

## stage_summary

Краткое состояние прохождения pipeline.

Источник:

```text
AttributeContext.stage_results
```

Минимальная структура:

```text
stage_summary
├─ completed_stages
├─ failed_stage
└─ stage_results
```

Пример:

```yaml
stage_summary:
  completed_stages:
    - validate_job
    - resolve_canonical
    - resolve_scope
    - export_attributes
    - analyze_names
    - analyze_values
    - build_sql_preview
    - build_report
    - build_framework_result
  failed_stage:
  stage_results:
    validate_job: ok
    resolve_canonical: ok
    resolve_scope: ok
    export_attributes: ok
    analyze_names: ok_with_warnings
    analyze_values: ok_with_warnings
    build_sql_preview: blocked
    build_report: ok
    build_framework_result: ok
```

---

## report

Человекочитаемый отчёт.

Источник:

```text
AttributeContext.report
```

Если report не был сформирован из-за ошибки, `FrameworkResult` должен всё равно быть создан в safe mode, но с warning/error:

```text
report_missing
```

---

## Базовый алгоритм

Рекомендуемый порядок работы stage:

```text
1. Получить AttributeContext
2. Собрать job_summary
3. Собрать canonical_attribute
4. Собрать scope_summary
5. Перенести found_attributes
6. Перенести synonym_candidates
7. Перенести value_report
8. Перенести unknown / invalid / empty values
9. Перенести sql_preview
10. Перенести warnings
11. Перенести errors
12. Сформировать stage_summary
13. Перенести report
14. Детерминированно вывести result_status из AttributeContext
15. Записать stage_result
16. Вернуть FrameworkResult
```

---

## Определение blockers

Stage должна учитывать blockers из:

```text
AttributeContext.sql_preview.blocked_by
```

и может дополнительно считать блокировками:

```text
ambiguous_candidates_exist
manual_approval_required
unknown_values_exist
invalid_values_exist
canonical_not_active
canonical_not_locked
```

Но она не должна придумывать новые смысловые blockers, которых нет в данных `AttributeContext`.

---

## Safe mode

`BuildFrameworkResultStage` должна уметь работать в safe mode.

Safe mode нужен, если pipeline был остановлен до полного выполнения.

В safe mode stage должна:

* сформировать частичный `FrameworkResult`;
* показать errors;
* показать completed / failed stages;
* показать отсутствующие sections;
* не считать результат безопасным;
* не скрывать, что данные неполные.

Пример:

```yaml
result_status: failed

errors:
  - canonical_code_not_found

stage_summary:
  completed_stages:
    - validate_job
  failed_stage: resolve_canonical

report:
  title: Safe Mode Result
  summary: Pipeline stopped before canonical was resolved.
```

---

## Stage result

Результат stage записывается в:

```text
AttributeContext.stage_results.build_framework_result
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
status: ok
started_at: 2026-07-03 12:30:00
finished_at: 2026-07-03 12:30:01
errors: []
warnings: []
summary:
  result_status: blocked
  report_attached: 1
  sql_preview_generated: 1
```

Важно: `stage_results.build_framework_result.status` описывает успешность самой stage, а `FrameworkResult.result_status` описывает итог всего запуска.

---

## Пример FrameworkResult

```yaml
result_status: blocked

job_summary:
  job_id: pump_diameter_borehole_pumps
  job_name: Стандартизация диаметра насоса в категории Скважинные насосы
  source: local
  language_id: 3
  created_at: 2026-07-03

canonical_attribute:
  canonical_id: 1
  canonical_code: pump_diameter
  target_attribute_id: 123
  target_attribute_name: Диаметр насоса
  target_attribute_group_id: 7
  target_attribute_group_name: Насосы
  status: active
  locked: 1

scope_summary:
  type: category
  category_id: 11900213
  category_name: Скважинные насосы
  include_subcategories: 1
  product_count: 120

found_attributes:
  - attribute_id: 123
    attribute_name: Диаметр насоса
    attribute_group_id: 7
    attribute_group_name: Насосы
    usage_count: 12
    sample_values:
      - "96 мм"
      - "101 мм"

proposed_synonym_candidates:
  - attribute_id: 456
    attribute_name: Диаметр корпуса
    attribute_group_id: 7
    attribute_group_name: Насосы
    usage_count: 8
    sample_values:
      - "96"
      - "101"
    reason: similar_name_and_compatible_samples
    confidence: medium

rejected_candidates: []

ambiguous_candidates: []

value_report:
  parser: diameter_mm
  value_type: decimal
  unit: mm
  total_values: 20
  normalized_count: 17
  unknown_count: 2
  invalid_count: 1
  empty_count: 0
  unique_normalized_values:
    - 96
    - 101.6

unknown_values:
  - product_id: 1003
    attribute_id: 456
    raw_text: "около 100 мм"
    reason: ambiguous_value

invalid_values:
  - product_id: 1004
    attribute_id: 456
    raw_text: "abc"
    reason: not_numeric

empty_values: []

sql_preview:
  enabled: 1
  generated: 1
  safe_to_apply: 0
  blocked_by:
    - manual_approval_required
    - unknown_values_exist
    - invalid_values_exist
  statements: []
  notes:
    - SQL preview is for manual review only.

warnings:
  - manual_approval_required
  - unknown_values_found
  - invalid_values_found

errors: []

stage_summary:
  completed_stages:
    - validate_job
    - resolve_canonical
    - resolve_scope
    - export_attributes
    - analyze_names
    - analyze_values
    - build_sql_preview
    - build_report
    - build_framework_result
  failed_stage:
  stage_results:
    validate_job: ok
    resolve_canonical: ok
    resolve_scope: ok
    export_attributes: ok
    analyze_names: ok_with_warnings
    analyze_values: ok_with_warnings
    build_sql_preview: blocked
    build_report: ok
    build_framework_result: ok

report:
  format: markdown
  title: Attribute Standardization Report
```

---

## Граница с BuildReportStage

`BuildReportStage` формирует:

```text
AttributeContext.report
```

`BuildFrameworkResultStage` только переносит этот отчёт в `FrameworkResult`.

Она не должна переписывать содержание отчёта.

---

## Граница с BuildSqlPreviewStage

`BuildSqlPreviewStage` формирует:

```text
AttributeContext.sql_preview
```

`BuildFrameworkResultStage` использует его для:

* переноса SQL preview;
* определения blockers;
* детерминированного определения `result_status` из `AttributeContext`.

Но она не должна создавать новые SQL statements.

---

## Граница с FrameworkResult

Структура `FrameworkResult` отдельно описана в:

```text
docs/FRAMEWORK_RESULT.md
```

Этот документ описывает именно stage, которая собирает этот результат.

---

## Требования к реализации

Реализация `BuildFrameworkResultStage` должна быть:

* детерминированной;
* устойчивой к частично заполненному `AttributeContext`;
* пригодной для safe mode;
* без записи в БД;
* без выполнения SQL;
* без изменения смысловых результатов предыдущих stages;
* с явным `result_status`;
* с переносом warnings/errors без потерь.

---

## Статус документа

Документ является архитектурным контрактом `BuildFrameworkResultStage`.

Код реализации должен следовать этому контракту.
