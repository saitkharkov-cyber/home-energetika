# Attribute Context

Документ описывает контракт `AttributeContext` — рабочего состояния Framework Standardization.

`AttributeContext` создаётся Framework на основе `Attribute Job` и передаётся между stage pipeline.

---

## Назначение

`AttributeContext` является единым рабочим контейнером выполнения задачи.

```text
Attribute Job
→ Framework создает AttributeContext
→ Stage pipeline работают с AttributeContext
→ FrameworkResult формируется из AttributeContext
````

`AttributeContext` нужен, чтобы stage не общались напрямую друг с другом и не зависели от внутренней реализации соседних stage.

---

## Главный принцип

Все данные выполнения проходят через `AttributeContext`.

Stage pipeline:

* читают данные из `AttributeContext`;
* добавляют свои результаты в `AttributeContext`;
* не вызывают другие stage напрямую;
* не хранят глобальное состояние;
* не меняют продуктивную БД.

```text
Stage A
→ пишет результат в AttributeContext

Stage B
→ читает результат из AttributeContext
```

---

## Ответственность AttributeContext

`AttributeContext` отвечает за хранение рабочего состояния одной задачи обработки характеристики.

Он содержит:

* исходный `Attribute Job`;
* разрешённый канонический атрибут;
* scope анализа;
* сырые данные из OpenCart;
* структуру найденных имён атрибутов;
* структуру найденных значений;
* кандидатов в синонимы;
* результаты нормализации значений;
* предупреждения;
* ошибки;
* SQL preview;
* отчётные данные.

`AttributeContext` не отвечает за:

* выбор характеристики;
* создание канона;
* утверждение синонимов;
* применение SQL;
* публикацию результата;
* изменение импортов.

---

## Минимальная структура

```text
AttributeContext
├─ job
├─ canonical
├─ scope
├─ source
├─ runtime
├─ raw_data
├─ attribute_name_structure
├─ attribute_value_structure
├─ synonym_candidates
├─ value_report
├─ sql_preview
├─ warnings
├─ errors
└─ stage_results
```

---

## job

Блок `job` содержит исходную декларацию задачи.

```text
job
├─ job_id
├─ job_name
└─ raw_job
```

---

### job_id

Технический идентификатор задачи из `Attribute Job`.

Пример:

```text
pump_diameter_borehole_pumps
```

Используется для логов, отчётов и файлов результата.

---

### job_name

Человекочитаемое название задачи.

Пример:

```text
Стандартизация диаметра насоса в категории Скважинные насосы
```

---

### raw_job

Исходный `Attribute Job` без изменений.

Нужен для диагностики и воспроизводимости запуска.

---

## canonical

Блок `canonical` содержит разрешённые данные канонического атрибута.

```text
canonical
├─ canonical_id
├─ canonical_code
├─ target_attribute_id
├─ target_attribute_name
├─ target_attribute_group_id
├─ target_attribute_group_name
├─ status
└─ locked
```

Данные берутся из:

```text
{DB_PREFIX}canonical_attributes
```

и сверяются с текущими таблицами OpenCart.

---

### canonical_id

Внутренний идентификатор записи в registry канонов.

---

### canonical_code

Стабильный технический код смысла характеристики.

Пример:

```text
pump_diameter
```

---

### target_attribute_id

Реальный `attribute_id` OpenCart, который является целевым каноническим атрибутом.

---

### target_attribute_name

Контрольное имя целевого атрибута.

---

### target_attribute_group_id

Реальный `attribute_group_id` OpenCart.

---

### target_attribute_group_name

Контрольное имя группы атрибутов.

---

### status

Статус канона.

Допустимые значения:

```text
draft
active
```

---

### locked

Флаг блокировки канона.

```text
0 / 1
```

---

## scope

Блок `scope` описывает область анализа.

```text
scope
├─ type
├─ category_id
├─ category_name
├─ include_subcategories
├─ product_ids
└─ product_count
```

---

### type

Тип области анализа.

На первом этапе основной тип:

```text
category
```

---

### category_id

ID категории OpenCart, в рамках которой выполняется анализ.

Категория не является частью идентичности канона.

---

### category_name

Контрольное имя категории.

---

### include_subcategories

Флаг включения подкатегорий.

```text
0 / 1
```

---

### product_ids

Список товаров, попавших в scope.

Заполняется после разрешения scope.

---

### product_count

Количество товаров, попавших в scope.

Используется в отчётах и предупреждениях.

---

## source

Блок `source` описывает источник данных.

```text
source
├─ type
├─ database
├─ language_id
└─ db_prefix
```

---

### type

Тип источника.

На первом этапе:

```text
opencart_db
```

---

### database

Логическое имя окружения.

Примеры:

```text
local
staging
production_snapshot
```

---

### language_id

Основной язык, по которому читаются названия атрибутов, групп и значения.

---

### db_prefix

Префикс таблиц OpenCart.

Пример:

```text
oc_
```

В документах и SQL-драфтах используется placeholder:

```text
{DB_PREFIX}
```

---

## runtime

Блок `runtime` содержит служебное состояние выполнения.

```text
runtime
├─ started_at
├─ finished_at
├─ current_stage
├─ completed_stages
└─ failed_stage
```

---

### started_at

Время начала выполнения задачи.

---

### finished_at

Время завершения выполнения задачи.

---

### current_stage

Stage, который выполняется сейчас.

---

### completed_stages

Список успешно завершённых stage.

---

### failed_stage

Stage, на котором выполнение было остановлено из-за ошибки.

Если ошибок нет, значение пустое.

---

## raw_data

Блок `raw_data` содержит факты, прочитанные из OpenCart.

```text
raw_data
├─ products
├─ attributes
├─ attribute_groups
└─ product_attributes
```

---

### products

Товары, попавшие в scope.

Минимально:

```text
product_id
model
name
category_ids
```

---

### attributes

Атрибуты OpenCart, найденные в рамках анализа.

Минимально:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
usage_count
```

---

### attribute_groups

Группы атрибутов, связанные с найденными атрибутами.

---

### product_attributes

Сырые значения атрибутов товаров.

Минимально:

```text
product_id
attribute_id
language_id
text
```

---

## attribute_name_structure

`attribute_name_structure` описывает найденные варианты имён характеристики.

Этот блок отвечает только за имена атрибутов, а не за значения.

```text
attribute_name_structure
├─ target_attribute
├─ found_attributes
├─ exact_matches
├─ similar_name_candidates
├─ rejected_name_candidates
└─ diagnostics
```

---

### target_attribute

Целевой канонический атрибут OpenCart.

```text
target_attribute
├─ attribute_id
├─ attribute_name
├─ attribute_group_id
└─ attribute_group_name
```

---

### found_attributes

Все найденные атрибуты в scope, которые потенциально относятся к анализируемой характеристике.

Пример элемента:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
usage_count
sample_values
```

---

### exact_matches

Атрибуты, которые совпадают с целевым именем или заданными точными правилами.

---

### similar_name_candidates

Кандидаты, похожие по названию на целевую характеристику.

Важно: попадание в этот список не означает утверждённый синоним.

---

### rejected_name_candidates

Кандидаты, которые были рассмотрены, но отклонены на этапе анализа имён.

---

### diagnostics

Диагностические данные по именам:

```text
total_found
target_usage_count
most_frequent_attribute
ambiguous_names
```

---

## attribute_value_structure

`attribute_value_structure` описывает найденные и нормализованные значения характеристики.

Этот блок отвечает только за значения, а не за выбор имён атрибутов.

```text
attribute_value_structure
├─ raw_values
├─ normalized_values
├─ unknown_values
├─ invalid_values
├─ empty_values
└─ diagnostics
```

---

### raw_values

Сырые значения из OpenCart.

Пример элемента:

```text
product_id
attribute_id
raw_text
language_id
```

---

### normalized_values

Значения после обработки `ValueParser`.

Пример элемента:

```text
product_id
attribute_id
raw_text
normalized_value
unit
parser
```

---

### unknown_values

Значения, которые parser не смог уверенно распознать.

Пример:

```text
raw_text: около 100 мм
reason: ambiguous_value
```

---

### invalid_values

Значения, которые нарушают правила типа или диапазона.

Пример:

```text
raw_text: abc
reason: not_numeric
```

---

### empty_values

Пустые значения.

Поведение зависит от:

```text
allow_empty
```

из `Attribute Job`.

---

### diagnostics

Диагностика по значениям:

```text
total_values
normalized_count
unknown_count
invalid_count
empty_count
unique_normalized_values
```

---

## synonym_candidates

Блок `synonym_candidates` содержит предложения Framework по возможным синонимам.

```text
synonym_candidates
├─ proposed
├─ rejected
└─ ambiguous
```

---

### proposed

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

`confidence` является диагностическим сигналом, а не автоматическим решением.

---

### rejected

Кандидаты, отклонённые Framework по правилам анализа.

---

### ambiguous

Кандидаты, по которым недостаточно данных для уверенного предложения.

---

## value_report

Блок `value_report` содержит отчёт по значениям.

```text
value_report
├─ parser
├─ value_type
├─ unit
├─ total_values
├─ normalized_values
├─ unknown_values
├─ invalid_values
├─ empty_values
└─ examples
```

---

## sql_preview

Блок `sql_preview` содержит SQL, подготовленный для ручной проверки.

```text
sql_preview
├─ enabled
├─ safe_to_apply
├─ statements
└─ blocked_by
```

---

### enabled

Флаг, был ли запрошен SQL preview в `Attribute Job`.

---

### safe_to_apply

Флаг предварительной безопасности SQL preview.

Важно: даже если `safe_to_apply = 1`, Framework не применяет SQL автоматически.

---

### statements

Список SQL-выражений.

На текущем этапе это только preview.

---

### blocked_by

Причины, по которым SQL preview нельзя считать безопасным.

Примеры:

```text
unknown_values_exist
ambiguous_synonym_candidates
canonical_not_active
```

---

## warnings

Список предупреждений.

Предупреждения не обязательно останавливают выполнение, но должны быть показаны инженеру.

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

Ошибки блокируют выполнение текущей задачи или генерацию результата.

Примеры:

```text
canonical_code not found
target_attribute_id does not exist
scope category does not exist
value_parser is unknown
apply_changes is not allowed
```

---

## stage_results

Блок `stage_results` содержит результаты отдельных stage.

```text
stage_results
├─ validate_job
├─ resolve_canonical
├─ resolve_scope
├─ export_attributes
├─ analyze_names
├─ analyze_values
├─ build_sql_preview
└─ build_report
```

Каждый stage может записать:

```text
status
started_at
finished_at
errors
warnings
summary
```

---

## Пример AttributeContext

```yaml
job:
  job_id: pump_diameter_borehole_pumps
  job_name: Стандартизация диаметра насоса в категории Скважинные насосы
  raw_job: {}

canonical:
  canonical_id: 1
  canonical_code: pump_diameter
  target_attribute_id: 123
  target_attribute_name: Диаметр насоса
  target_attribute_group_id: 7
  target_attribute_group_name: Насосы
  status: draft
  locked: 0

scope:
  type: category
  category_id: 11900213
  category_name: Скважинные насосы
  include_subcategories: 1
  product_ids: [1001, 1002, 1003]
  product_count: 3

source:
  type: opencart_db
  database: local
  language_id: 3
  db_prefix: oc_

runtime:
  started_at: 2026-07-03 12:00:00
  finished_at:
  current_stage: analyze_names
  completed_stages:
    - validate_job
    - resolve_canonical
    - resolve_scope
    - export_attributes
  failed_stage:

attribute_name_structure:
  target_attribute:
    attribute_id: 123
    attribute_name: Диаметр насоса
    attribute_group_id: 7
    attribute_group_name: Насосы
  found_attributes:
    - attribute_id: 123
      attribute_name: Диаметр насоса
      attribute_group_id: 7
      attribute_group_name: Насосы
      usage_count: 12
      sample_values: ["96 мм", "101 мм"]
    - attribute_id: 456
      attribute_name: Диаметр корпуса
      attribute_group_id: 7
      attribute_group_name: Насосы
      usage_count: 8
      sample_values: ["96", "101"]
  exact_matches:
    - 123
  similar_name_candidates:
    - 456
  rejected_name_candidates: []
  diagnostics:
    total_found: 2
    target_usage_count: 12
    most_frequent_attribute: 123
    ambiguous_names: []

attribute_value_structure:
  raw_values:
    - product_id: 1001
      attribute_id: 123
      raw_text: "96 мм"
      language_id: 3
  normalized_values:
    - product_id: 1001
      attribute_id: 123
      raw_text: "96 мм"
      normalized_value: 96
      unit: mm
      parser: diameter_mm
  unknown_values: []
  invalid_values: []
  empty_values: []
  diagnostics:
    total_values: 1
    normalized_count: 1
    unknown_count: 0
    invalid_count: 0
    empty_count: 0
    unique_normalized_values: [96]

synonym_candidates:
  proposed:
    - attribute_id: 456
      attribute_name: Диаметр корпуса
      attribute_group_id: 7
      attribute_group_name: Насосы
      usage_count: 8
      sample_values: ["96", "101"]
      reason: similar_name_and_compatible_values
      confidence: medium
  rejected: []
  ambiguous: []

sql_preview:
  enabled: 1
  safe_to_apply: 0
  statements: []
  blocked_by:
    - proposed_synonym_candidates_require_manual_approval

warnings:
  - canonical status is draft
  - canonical is not locked

errors: []

stage_results:
  validate_job:
    status: ok
  resolve_canonical:
    status: ok
  resolve_scope:
    status: ok
  export_attributes:
    status: ok
```

---

## Правила изменения AttributeContext

Stage может добавлять данные только в свой логический раздел.

Например:

```text
AttributeExporter
→ raw_data
→ attribute_name_structure.found_attributes
→ attribute_value_structure.raw_values
```

```text
AnalyzeStage
→ synonym_candidates
→ diagnostics
→ warnings
```

```text
ValueParserStage
→ attribute_value_structure.normalized_values
→ attribute_value_structure.unknown_values
→ value_report
```

Stage не должен перезаписывать чужие результаты без явного правила.

---

## Ошибки и остановка pipeline

Если stage добавляет критическую ошибку в `errors`, pipeline должен остановиться или перейти в безопасный режим.

SQL preview не должен генерироваться как безопасный, если существуют критические ошибки.

---

## Связь с FrameworkResult

`FrameworkResult` формируется из `AttributeContext`.

`FrameworkResult` не является независимым источником состояния.

```text
AttributeContext
→ FrameworkResult
```

`FrameworkResult` содержит финальную проекцию для инженера:

```text
canonical_attribute
proposed_synonym_candidates
rejected_candidates
value_report
warnings
sql_preview
report
unknown_values
```

---

## Граница ответственности

`AttributeContext` не является БД-моделью.

`AttributeContext` не является stage.

`AttributeContext` не является результатом для публикации.

Это временное рабочее состояние одного запуска Framework.

---

## Статус документа

Документ является архитектурным контрактом `AttributeContext`.

Код реализации должен следовать этому контракту.

