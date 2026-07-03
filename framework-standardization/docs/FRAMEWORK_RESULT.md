# Framework Result

Документ описывает контракт `FrameworkResult` — финального результата одного запуска Framework Standardization.

`FrameworkResult` формируется из `AttributeContext` после выполнения pipeline stages.

---

## Назначение

`FrameworkResult` — это финальная проекция рабочего состояния для инженера.

Он нужен, чтобы инженер получил понятный итог выполнения `Attribute Job`:

```text
что было найдено
что Framework предлагает
какие есть ошибки
какие есть предупреждения
какой SQL preview подготовлен
можно ли считать результат безопасным
````

---

## Главный принцип

`FrameworkResult` не является независимым состоянием.

Он формируется из:

```text
AttributeContext
```

и не должен содержать данных, которых не было в `AttributeContext`.

```text
Attribute Job
→ AttributeContext
→ Pipeline Stages
→ FrameworkResult
```

---

## Ответственность FrameworkResult

`FrameworkResult` отвечает за представление результата инженеру.

Он содержит:

* сведения о задаче;
* канонический атрибут;
* scope анализа;
* найденные атрибуты;
* предложенные кандидаты в синонимы;
* отклонённые кандидаты;
* отчёт по значениям;
* неизвестные значения;
* предупреждения;
* ошибки;
* SQL preview;
* итоговый статус выполнения.

`FrameworkResult` не отвечает за:

* хранение рабочего состояния pipeline;
* выполнение SQL;
* изменение БД;
* утверждение синонимов;
* публикацию результата;
* изменение импортов.

---

## Минимальная структура

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

## result_status

Итоговый статус результата.

Допустимые значения первого этапа:

```text
ok
ok_with_warnings
failed
blocked
```

---

### ok

Pipeline завершился успешно.

Критических ошибок нет.

SQL preview может быть сформирован, если он был запрошен.

---

### ok_with_warnings

Pipeline завершился, критических ошибок нет, но есть предупреждения.

Пример:

```text
canonical status is draft
canonical is not locked
include_subcategories is enabled
```

---

### failed

Pipeline завершился с критической ошибкой.

Результат не должен считаться пригодным для применения.

---

### blocked

Pipeline технически мог завершиться, но результат заблокирован правилами безопасности.

Пример:

```text
unknown values exist
synonym candidates require manual approval
apply_changes is not allowed
```

---

## job_summary

Краткая информация об исходной задаче.

```text
job_summary
├─ job_id
├─ job_name
├─ source
└─ created_at
```

---

### job_id

Технический идентификатор `Attribute Job`.

---

### job_name

Человекочитаемое название задачи.

---

### source

Источник данных, по которому выполнялся анализ.

Пример:

```text
local
staging
production_snapshot
```

---

### created_at

Дата создания или запуска задачи.

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

---

## found_attributes

Список найденных атрибутов OpenCart в рамках scope, которые были рассмотрены pipeline.

Пример элемента:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
usage_count
sample_values
diagnostics
```

`usage_count` является диагностикой, а не автоматическим критерием выбора канона.

---

## proposed_synonym_candidates

Кандидаты, которые Framework предлагает инженеру рассмотреть как возможные синонимы.

Пример элемента:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
usage_count
sample_values
reason
confidence
```

Важно:

```text
proposed_synonym_candidates
→ не утверждённые синонимы
→ только предложения для ручной проверки
```

Финальное решение принимает инженер.

---

## rejected_candidates

Кандидаты, которые были рассмотрены, но отклонены pipeline.

Пример элемента:

```text
attribute_id
attribute_name
reason
```

---

## ambiguous_candidates

Кандидаты, по которым Framework не может дать уверенное предложение.

Пример элемента:

```text
attribute_id
attribute_name
reason
sample_values
```

Такие кандидаты требуют ручной проверки.

---

## value_report

Отчёт по значениям характеристики.

```text
value_report
├─ parser
├─ value_type
├─ unit
├─ total_values
├─ normalized_count
├─ unknown_count
├─ invalid_count
├─ empty_count
├─ unique_normalized_values
└─ examples
```

---

### parser

Использованный `ValueParser`.

Пример:

```text
diameter_mm
```

---

### value_type

Ожидаемый тип нормализованного значения.

Пример:

```text
decimal
```

---

### unit

Единица измерения.

Пример:

```text
mm
```

---

### total_values

Общее количество обработанных значений.

---

### normalized_count

Количество успешно нормализованных значений.

---

### unknown_count

Количество значений, которые parser не смог уверенно распознать.

---

### invalid_count

Количество значений, которые нарушают правила типа или диапазона.

---

### empty_count

Количество пустых значений.

---

### unique_normalized_values

Список уникальных нормализованных значений.

---

### examples

Примеры обработки значений.

Пример:

```text
raw_text: 96 мм
normalized_value: 96
unit: mm
status: normalized
```

---

## unknown_values

Список неизвестных или нераспознанных значений.

Пример элемента:

```text
product_id
attribute_id
attribute_name
raw_text
reason
```

Если `unknown_value_policy = block_sql`, наличие `unknown_values` должно блокировать безопасный SQL preview.

---

## sql_preview

SQL preview, подготовленный для ручной проверки.

```text
sql_preview
├─ enabled
├─ generated
├─ safe_to_apply
├─ blocked_by
└─ statements
```

---

### enabled

Был ли запрошен SQL preview в `Attribute Job`.

---

### generated

Был ли SQL preview реально сформирован.

---

### safe_to_apply

Предварительная оценка безопасности.

Важно:

```text
safe_to_apply = 1
```

не означает автоматическое применение.

Framework всё равно не выполняет SQL сам.

---

### blocked_by

Причины блокировки SQL preview.

Примеры:

```text
errors_exist
unknown_values_exist
manual_approval_required
canonical_not_active
canonical_not_locked
```

---

### statements

Список SQL-выражений.

На текущем этапе это только preview для инженера.

---

## warnings

Список предупреждений.

Предупреждения не обязательно делают результат невалидным, но должны быть явно показаны инженеру.

Примеры:

```text
canonical status is draft
canonical is not locked
include_subcategories is enabled
target attribute has low usage count
similar attributes found
```

---

## errors

Список ошибок.

Ошибки блокируют результат или делают его непригодным для применения.

Примеры:

```text
canonical_code not found
target_attribute_id does not exist
scope category does not exist
value_parser is unknown
apply_changes is not allowed
```

---

## stage_summary

Краткий отчёт по прохождению pipeline stages.

```text
stage_summary
├─ completed_stages
├─ failed_stage
└─ stage_results
```

---

### completed_stages

Список успешно завершённых stages.

---

### failed_stage

Stage, на котором выполнение остановилось.

Если ошибок нет, значение пустое.

---

### stage_results

Краткие статусы stage.

Пример:

```text
validate_job: ok
resolve_canonical: ok
resolve_scope: ok
export_attributes: ok
analyze_names: ok_with_warnings
analyze_values: ok
build_sql_preview: blocked
build_report: ok
```

---

## report

Человекочитаемый отчёт для инженера.

`report` может быть сформирован в Markdown, text или HTML.

Минимально должен содержать:

* название задачи;
* канонический атрибут;
* scope;
* количество товаров;
* найденные атрибуты;
* предложенные кандидаты;
* спорные кандидаты;
* отчёт по значениям;
* предупреждения;
* ошибки;
* SQL preview или причины его блокировки.

---

## Пример FrameworkResult

```yaml
result_status: blocked

job_summary:
  job_id: pump_diameter_borehole_pumps
  job_name: Стандартизация диаметра насоса в категории Скважинные насосы
  source: local
  created_at: 2026-07-03

canonical_attribute:
  canonical_id: 1
  canonical_code: pump_diameter
  target_attribute_id: 123
  target_attribute_name: Диаметр насоса
  target_attribute_group_id: 7
  target_attribute_group_name: Насосы
  status: draft
  locked: 0

scope_summary:
  type: category
  category_id: 11900213
  category_name: Скважинные насосы
  include_subcategories: 1
  product_count: 3

found_attributes:
  - attribute_id: 123
    attribute_name: Диаметр насоса
    attribute_group_id: 7
    attribute_group_name: Насосы
    usage_count: 12
    sample_values:
      - "96 мм"
      - "101 мм"

  - attribute_id: 456
    attribute_name: Диаметр корпуса
    attribute_group_id: 7
    attribute_group_name: Насосы
    usage_count: 8
    sample_values:
      - "96"
      - "101"

proposed_synonym_candidates:
  - attribute_id: 456
    attribute_name: Диаметр корпуса
    attribute_group_id: 7
    attribute_group_name: Насосы
    usage_count: 8
    sample_values:
      - "96"
      - "101"
    reason: similar_name_and_compatible_values
    confidence: medium

rejected_candidates: []

ambiguous_candidates: []

value_report:
  parser: diameter_mm
  value_type: decimal
  unit: mm
  total_values: 20
  normalized_count: 18
  unknown_count: 2
  invalid_count: 0
  empty_count: 0
  unique_normalized_values:
    - 96
    - 101

unknown_values:
  - product_id: 1003
    attribute_id: 456
    attribute_name: Диаметр корпуса
    raw_text: "около 100 мм"
    reason: ambiguous_value

sql_preview:
  enabled: 1
  generated: 0
  safe_to_apply: 0
  blocked_by:
    - proposed_synonym_candidates_require_manual_approval
    - unknown_values_exist
  statements: []

warnings:
  - canonical status is draft
  - canonical is not locked

errors: []

stage_summary:
  completed_stages:
    - validate_job
    - resolve_canonical
    - resolve_scope
    - export_attributes
    - analyze_names
    - analyze_values
  failed_stage:
  stage_results:
    validate_job: ok
    resolve_canonical: ok
    resolve_scope: ok
    export_attributes: ok
    analyze_names: ok_with_warnings
    analyze_values: ok
    build_sql_preview: blocked
```

---

## Правила формирования result_status

### failed

Устанавливается, если есть критические ошибки:

```text
errors не пустой
```

---

### blocked

Устанавливается, если критических ошибок нет, но результат заблокирован правилами безопасности.

Примеры:

```text
unknown_values_exist
manual_approval_required
sql_preview_blocked
```

---

### ok_with_warnings

Устанавливается, если ошибок нет, блокировок нет, но есть warnings.

---

### ok

Устанавливается, если ошибок, блокировок и предупреждений нет.

---

## Связь с AttributeContext

`FrameworkResult` формируется из `AttributeContext`.

Пример соответствия:

```text
AttributeContext.job
→ FrameworkResult.job_summary

AttributeContext.canonical
→ FrameworkResult.canonical_attribute

AttributeContext.scope
→ FrameworkResult.scope_summary

AttributeContext.attribute_name_structure.found_attributes
→ FrameworkResult.found_attributes

AttributeContext.synonym_candidates.proposed
→ FrameworkResult.proposed_synonym_candidates

AttributeContext.attribute_value_structure.unknown_values
→ FrameworkResult.unknown_values

AttributeContext.value_report
→ FrameworkResult.value_report

AttributeContext.sql_preview
→ FrameworkResult.sql_preview

AttributeContext.warnings
→ FrameworkResult.warnings

AttributeContext.errors
→ FrameworkResult.errors

AttributeContext.stage_results
→ FrameworkResult.stage_summary
```

---

## Граница ответственности

`FrameworkResult` не является:

* БД-моделью;
* stage pipeline;
* источником истины для промежуточного состояния;
* командой на применение изменений;
* автоматическим подтверждением синонимов.

Это финальная форма результата для ручной инженерной проверки.

---

## Статус документа

Документ является архитектурным контрактом `FrameworkResult`.

Код реализации должен следовать этому контракту.
