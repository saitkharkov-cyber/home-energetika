# Attribute Job

Документ описывает контракт `Attribute Job` — одной задачи обработки характеристики в Framework Standardization.

`Attribute Job` является минимальной единицей работы Framework.

---

## Назначение

`Attribute Job` описывает одну конкретную задачу стандартизации характеристики.

Framework не запускается абстрактно по всему каталогу.

Он запускается по заранее выбранной характеристике и заранее заданной области анализа.

```text
один запуск Framework
→ один Attribute Job
→ одна характеристика
→ один канонический атрибут
→ один scope анализа
````

---

## Главный принцип

`Attribute Job` не создаёт канон.

Канон должен быть зарегистрирован заранее в:

```text
{DB_PREFIX}canonical_attributes
```

`Attribute Job` только использует уже существующий канонический атрибут как целевой.

---

## Ответственность Attribute Job

`Attribute Job` отвечает за описание задачи:

* какую характеристику обрабатываем;
* какой канонический атрибут используется;
* в какой области выполняем анализ;
* какие правила применяются к значениям;
* какие данные нужно собрать;
* какой результат должен быть подготовлен.

`Attribute Job` не отвечает за:

* создание канонического атрибута;
* автоматическое утверждение синонимов;
* автоматическое изменение продуктивной БД;
* изменение импортов;
* удаление старых атрибутов;
* публикацию результата.

---

## Минимальная структура

```text
Attribute Job
├─ job_id
├─ job_name
├─ canonical
├─ scope
├─ source
├─ value_rules
├─ analysis_rules
├─ output
└─ metadata
```

---

## job_id

Технический идентификатор задачи.

Пример:

```text
pump_diameter_borehole_pumps
```

Требования:

* не пустой;
* уникальный в рамках набора задач;
* пишется латиницей;
* используется для логов, отчётов и файлов результата.

Рекомендуемый формат:

```text
^[a-z][a-z0-9_]*$
```

---

## job_name

Человекочитаемое название задачи.

Пример:

```text
Стандартизация диаметра насоса в категории Скважинные насосы
```

Используется в отчётах и диагностике.

---

## canonical

Блок `canonical` описывает целевой канонический атрибут.

```text
canonical
├─ canonical_code
├─ canonical_id
├─ target_attribute_id
├─ target_attribute_name
├─ target_attribute_group_id
└─ target_attribute_group_name
```

---

### canonical_code

Стабильный технический код смысла характеристики.

Пример:

```text
pump_diameter
```

`canonical_code` должен существовать в таблице:

```text
{DB_PREFIX}canonical_attributes
```

---

### canonical_id

Внутренний идентификатор записи в таблице:

```text
{DB_PREFIX}canonical_attributes
```

Используется для связи Framework с registry канонов.

---

### target_attribute_id

Реальный `attribute_id` OpenCart, который является целевым каноническим атрибутом.

Все утверждённые варианты характеристики должны приводиться именно к нему.

---

### target_attribute_name

Контрольное имя целевого атрибута OpenCart.

Используется для отчёта и проверки, что задача работает с ожидаемым каноном.

---

### target_attribute_group_id

Реальный `attribute_group_id` OpenCart.

---

### target_attribute_group_name

Контрольное имя группы атрибутов OpenCart.

---

## scope

`scope` описывает область анализа и применения.

На текущем этапе scope не хранится в БД canonical registry.

Он задаётся внутри `Attribute Job`.

```text
scope
├─ type
├─ category_id
├─ category_name
└─ include_subcategories
```

---

### type

Тип области анализа.

На текущем этапе основной тип:

```text
category
```

---

### category_id

ID категории OpenCart, внутри которой выполняется анализ.

Пример:

```text
11900213
```

Категория не является частью идентичности канона.

Категория только ограничивает область текущей задачи.

---

### category_name

Контрольное имя категории.

Используется для отчётов и ручной проверки.

---

### include_subcategories

Флаг, нужно ли включать подкатегории.

Допустимые значения:

```text
0
1
```

На первом этапе рекомендуется использовать явное значение, без автоматических догадок.

---

## source

`source` описывает источник данных для анализа.

```text
source
├─ type
├─ database
├─ language_id
└─ notes
```

---

### type

Тип источника.

На текущем этапе основной тип:

```text
opencart_db
```

---

### database

Логическое имя базы или окружения.

Примеры:

```text
local
staging
production_snapshot
```

Framework должен работать с выбранным источником данных, но не должен автоматически публиковать изменения в продуктив.

---

### language_id

Основной язык, по которому читаются названия атрибутов, групп и значений.

Пример:

```text
3
```

Значение должно соответствовать текущей базе OpenCart.

---

### notes

Свободные заметки по источнику данных.

---

## value_rules

`value_rules` описывает правила обработки значений характеристики.

```text
value_rules
├─ value_parser
├─ value_type
├─ unit
├─ allow_empty
├─ normalize_spaces
└─ unknown_value_policy
```

---

### value_parser

Имя parser-а, который нормализует одно значение.

Примеры:

```text
diameter_mm
integer
decimal
text_exact
```

`ValueParser` не работает с БД.

Он получает одно значение и возвращает нормализованное значение или ошибку.

---

### value_type

Ожидаемый тип нормализованного значения.

Примеры:

```text
integer
decimal
string
boolean
```

---

### unit

Единица измерения, если применимо.

Примеры:

```text
mm
m
l_min
w
v
```

Если характеристика текстовая, поле может быть пустым.

---

### allow_empty

Флаг, допускаются ли пустые значения.

Допустимые значения:

```text
0
1
```

---

### normalize_spaces

Флаг, нужно ли нормализовать пробелы перед разбором значения.

Допустимые значения:

```text
0
1
```

---

### unknown_value_policy

Политика обработки неизвестных или нераспознанных значений.

Допустимые значения первого этапа:

```text
report_only
block_sql
```

`report_only` означает, что неизвестные значения попадают в отчёт, но не обязательно блокируют SQL preview.

`block_sql` означает, что при неизвестных значениях SQL preview не должен считаться безопасным.

---

## analysis_rules

`analysis_rules` описывает правила анализа кандидатов.

```text
analysis_rules
├─ collect_usage_count
├─ collect_sample_values
├─ max_sample_values
├─ propose_synonyms
└─ frequency_is_diagnostic_only
```

---

### collect_usage_count

Флаг, нужно ли считать частотность использования найденных атрибутов.

На текущем этапе рекомендуется:

```text
1
```

---

### collect_sample_values

Флаг, нужно ли собирать примеры значений.

На текущем этапе рекомендуется:

```text
1
```

---

### max_sample_values

Максимальное количество примеров значений для одного найденного атрибута.

Пример:

```text
20
```

---

### propose_synonyms

Флаг, может ли Framework предлагать кандидатов в синонимы.

Важно: предложение синонима не является утверждением.

Финальное решение принимает инженер.

---

### frequency_is_diagnostic_only

Фиксированный принцип:

```text
1
```

Частотность является диагностическим сигналом, а не автоматическим выбором канона.

Самый частотный атрибут не обязан быть правильным каноном.

---

## output

`output` описывает, какие результаты должен подготовить Framework.

```text
output
├─ generate_report
├─ generate_sql_preview
├─ generate_value_report
├─ generate_unknown_values_report
└─ apply_changes
```

---

### generate_report

Флаг генерации общего отчёта.

---

### generate_sql_preview

Флаг генерации SQL preview.

SQL preview не должен выполняться автоматически.

---

### generate_value_report

Флаг генерации отчёта по значениям.

---

### generate_unknown_values_report

Флаг генерации отчёта по неизвестным значениям.

---

### apply_changes

На текущем этапе должно быть:

```text
0
```

Framework не применяет изменения автоматически.

Публикация результата выполняется инженером вручную после проверки.

---

## metadata

`metadata` содержит служебную информацию.

```text
metadata
├─ created_at
├─ created_by
├─ comment
└─ related_docs
```

---

### created_at

Дата создания задачи.

---

### created_by

Автор задачи.

Поле может быть информационным.
В БД canonical registry авторство на текущем этапе не хранится.

---

### comment

Комментарий инженера.

---

### related_docs

Ссылки на связанные документы.

Пример:

```text
docs/ATTRIBUTE_PIPELINE.md
docs/CANONICAL_ATTRIBUTE_REGISTRATION.md
```

---

## Пример Attribute Job

```yaml
job_id: pump_diameter_borehole_pumps
job_name: Стандартизация диаметра насоса в категории Скважинные насосы

canonical:
  canonical_code: pump_diameter
  canonical_id: 1
  target_attribute_id: 123
  target_attribute_name: Диаметр насоса
  target_attribute_group_id: 7
  target_attribute_group_name: Насосы

scope:
  type: category
  category_id: 11900213
  category_name: Скважинные насосы
  include_subcategories: 1

source:
  type: opencart_db
  database: local
  language_id: 3
  notes: Локальная копия базы OpenCart

value_rules:
  value_parser: diameter_mm
  value_type: decimal
  unit: mm
  allow_empty: 0
  normalize_spaces: 1
  unknown_value_policy: block_sql

analysis_rules:
  collect_usage_count: 1
  collect_sample_values: 1
  max_sample_values: 20
  propose_synonyms: 1
  frequency_is_diagnostic_only: 1

output:
  generate_report: 1
  generate_sql_preview: 1
  generate_value_report: 1
  generate_unknown_values_report: 1
  apply_changes: 0

metadata:
  created_at: 2026-07-03
  created_by: engineer
  comment: Первый job для стандартизации диаметра насоса
  related_docs:
    - docs/ATTRIBUTE_PIPELINE.md
    - docs/CANONICAL_ATTRIBUTE_REGISTRATION.md
```

---

## Проверки перед запуском Attribute Job

Перед запуском Framework должен проверить:

* `job_id` не пустой;
* `canonical_code` существует в `{DB_PREFIX}canonical_attributes`;
* канон имеет допустимый статус;
* `target_attribute_id` из job совпадает с registry;
* `target_attribute_name` совпадает с текущим OpenCart;
* `target_attribute_group_id` совпадает с registry;
* `target_attribute_group_name` совпадает с текущим OpenCart;
* `scope.category_id` существует;
* `language_id` существует;
* `value_parser` известен Framework;
* `output.apply_changes = 0`.

---

## Ошибки

Ошибки блокируют запуск `Attribute Job`.

К ошибкам относятся:

* отсутствующий `job_id`;
* несуществующий `canonical_code`;
* несовпадение данных канона с registry;
* несуществующий `target_attribute_id`;
* несуществующий `scope.category_id`;
* неизвестный `value_parser`;
* недопустимый `unknown_value_policy`;
* `apply_changes = 1` на текущем этапе.

---

## Предупреждения

Предупреждения не обязательно блокируют запуск, но должны быть показаны инженеру.

К предупреждениям относятся:

* канон имеет статус `draft`;
* канон не заблокирован (`locked = 0`);
* `include_subcategories = 1`;
* `comment` пустой;
* `max_sample_values` слишком мал;
* scope содержит слишком много товаров.

---

## Результат запуска

Запуск `Attribute Job` должен сформировать `AttributeContext`.

`AttributeContext` используется всеми stage pipeline.

Финальный результат формируется как `FrameworkResult`.

```text
Attribute Job
→ AttributeContext
→ Pipeline Stages
→ FrameworkResult
```

---

## Граница ответственности

`Attribute Job` не является stage.

`Attribute Job` не выполняет анализ сам.

`Attribute Job` не работает напрямую с БД.

`Attribute Job` является декларацией задачи, по которой Framework создаёт `AttributeContext`.

---

## Связь с AttributeContext

Framework читает `Attribute Job` и создаёт `AttributeContext`.

`AttributeContext` содержит рабочие структуры:

```text
attribute_name_structure
attribute_value_structure
warnings
errors
intermediate_results
```

Stage pipeline работают не с `Attribute Job` напрямую, а с `AttributeContext`.

---

## Статус документа

Документ является архитектурным контрактом `Attribute Job`.

Код реализации должен следовать этому контракту.
