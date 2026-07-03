# Analyze Values Stage

Документ описывает контракт `AnalyzeValuesStage` — stage анализа и нормализации значений атрибутов в Framework Standardization.

`AnalyzeValuesStage` работает после `AnalyzeNamesStage` и использует `ValueParser` для обработки сырых значений.

```text
ExportAttributesStage
→ AnalyzeNamesStage
→ AnalyzeValuesStage
````

---

## Назначение

`AnalyzeValuesStage` получает сырые значения характеристик из `AttributeContext.attribute_value_structure.raw_values`, применяет к ним выбранный `ValueParser` и раскладывает результаты по категориям:

```text
raw_values
→ ValueParser
→ normalized / unknown / invalid / empty
```

Главная задача stage:

```text
сырые значения OpenCart
→ нормализованные значения
→ отчёт по значениям
→ warnings / blockers для SQL preview
```

---

## Главный принцип

`AnalyzeValuesStage` не принимает решений о синонимах.

Она работает только со значениями.

```text
AnalyzeNamesStage
→ анализирует имена атрибутов

AnalyzeValuesStage
→ анализирует значения атрибутов
```

Если значение не удаётся безопасно распознать, stage не должна “угадывать”.

Она должна поместить значение в `unknown_values` или `invalid_values` и объяснить причину.

---

## Место в pipeline

`AnalyzeValuesStage` вызывается после того, как:

* `ExportAttributesStage` выгрузил сырые значения;
* `AnalyzeNamesStage` сформировал кандидатов по именам;
* `ValueParser` уже описан как отдельный компонент нормализации одного значения.

Порядок:

```text
ValidateJobStage
→ ResolveCanonicalStage
→ ResolveScopeStage
→ ExportAttributesStage
→ AnalyzeNamesStage
→ AnalyzeValuesStage
→ BuildSqlPreviewStage
```

---

## Техническое имя stage

```text
analyze_values
```

Результат выполнения записывается в:

```text
AttributeContext.stage_results.analyze_values
```

---

## Ответственность AnalyzeValuesStage

Stage отвечает за:

* получение `raw_values`;
* выбор parser-а по `value_rules.value_parser`;
* вызов `ValueParser` для каждого значения;
* распределение результатов по категориям:

  * `normalized_values`;
  * `unknown_values`;
  * `invalid_values`;
  * `empty_values`;
* формирование `value_report`;
* применение `allow_empty`;
* применение `unknown_value_policy`;
* добавление warnings;
* добавление ошибок при технических сбоях parser-а;
* подготовку данных для `BuildSqlPreviewStage`.

---

## Что AnalyzeValuesStage не делает

`AnalyzeValuesStage` не должна:

* читать БД напрямую;
* изменять БД;
* выбирать канонический атрибут;
* создавать канонический атрибут;
* анализировать имена атрибутов;
* утверждать синонимы;
* переносить значения между атрибутами;
* формировать SQL preview;
* применять SQL;
* изменять импорты.

---

## Читает из AttributeContext

```text
AttributeContext.attribute_value_structure.raw_values
AttributeContext.job.raw_job.value_rules
AttributeContext.synonym_candidates
AttributeContext.canonical
AttributeContext.scope
```

---

## Пишет в AttributeContext

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

## Входная структура raw_values

Stage работает со списком:

```text
AttributeContext.attribute_value_structure.raw_values
```

Минимальная структура элемента:

```text
product_id
attribute_id
raw_text
language_id
```

Пример:

```yaml
product_id: 1001
attribute_id: 123
raw_text: "96 мм"
language_id: 3
```

---

## value_rules

Правила обработки значений берутся из `Attribute Job`.

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

Имя parser-а, который нужно применить.

Примеры:

```text
diameter_mm
head_m
flow_l_min
power_w
voltage_v
text_exact
```

Parser должен быть известен Framework.

Если parser неизвестен, это критическая ошибка.

---

### value_type

Ожидаемый тип нормализованного значения.

Допустимые значения первого этапа:

```text
integer
decimal
string
boolean
```

---

### unit

Ожидаемая единица измерения.

Примеры:

```text
mm
m
l_min
w
v
```

---

### allow_empty

Флаг, допускаются ли пустые значения.

```text
0 / 1
```

Если `allow_empty = 0`, пустые значения должны попадать в отчёт как проблема.

---

### normalize_spaces

Флаг нормализации пробелов перед разбором.

```text
0 / 1
```

---

### unknown_value_policy

Политика обработки неизвестных значений.

Допустимые значения первого этапа:

```text
report_only
block_sql
```

`AnalyzeValuesStage` должна учитывать эту политику при формировании диагностики и предупреждений.

---

## Базовый алгоритм

Рекомендуемый порядок работы stage:

```text
1. Проверить preconditions
2. Получить raw_values
3. Получить value_rules
4. Проверить, что value_parser известен Framework
5. Для каждого raw value вызвать ValueParser
6. Разложить parse_result по категориям
7. Посчитать диагностику
8. Сформировать value_report
9. Добавить warnings
10. Добавить blockers для SQL preview через диагностику/предупреждения
11. Записать stage_result
```

---

## Вызов ValueParser

`AnalyzeValuesStage` вызывает `ValueParser` для каждого значения.

```text
raw_text
+ value_rules
→ ValueParser
→ parse_result
```

Минимальный `parse_result`:

```text
status
raw_text
normalized_value
normalized_text
value_type
unit
parser
reason
warnings
```

---

## Статусы parse_result

`ValueParser` возвращает один из статусов:

```text
normalized
empty
unknown
invalid
```

`AnalyzeValuesStage` должна положить результат в соответствующий раздел `AttributeContext`.

---

## normalized_values

`normalized_values` содержит успешно нормализованные значения.

Минимальная структура элемента:

```text
product_id
attribute_id
raw_text
normalized_value
normalized_text
value_type
unit
parser
warnings
```

Пример:

```yaml
product_id: 1001
attribute_id: 123
raw_text: "96 мм"
normalized_value: 96
normalized_text: "96"
value_type: decimal
unit: mm
parser: diameter_mm
warnings: []
```

---

## unknown_values

`unknown_values` содержит значения, которые parser не смог уверенно распознать.

Пример:

```yaml
product_id: 1003
attribute_id: 456
raw_text: "около 100 мм"
reason: ambiguous_value
```

`unknown` не означает, что значение неверное.

Это означает, что Framework не может безопасно нормализовать его автоматически.

---

## invalid_values

`invalid_values` содержит значения, которые нарушают ожидаемый формат, тип или диапазон.

Пример:

```yaml
product_id: 1004
attribute_id: 456
raw_text: "abc"
reason: not_numeric
```

---

## empty_values

`empty_values` содержит пустые значения или технические маркеры пустоты.

Примеры:

```text
""
" "
"-"
"—"
```

Если `allow_empty = 0`, наличие таких значений должно быть отражено в warnings и value_report.

---

## diagnostics

Stage должна заполнить:

```text
AttributeContext.attribute_value_structure.diagnostics
```

Минимальная структура:

```text
total_values
normalized_count
unknown_count
invalid_count
empty_count
unique_normalized_values
affected_products_count
affected_attributes_count
```

Пример:

```yaml
diagnostics:
  total_values: 20
  normalized_count: 17
  unknown_count: 2
  invalid_count: 1
  empty_count: 0
  unique_normalized_values:
    - 96
    - 101.6
  affected_products_count: 20
  affected_attributes_count: 2
```

---

## value_report

Stage должна сформировать:

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

Пример:

```yaml
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
  examples:
    - raw_text: "96 мм"
      normalized_value: 96
      status: normalized
    - raw_text: "около 100 мм"
      status: unknown
      reason: ambiguous_value
```

---

## Применение unknown_value_policy

### report_only

Если:

```text
unknown_value_policy = report_only
```

то unknown values должны попасть в отчёт, но сами по себе не обязаны блокировать SQL preview.

Stage добавляет warning:

```text
unknown_values_found
```

---

### block_sql

Если:

```text
unknown_value_policy = block_sql
```

и есть `unknown_values`, stage должна добавить warning/blocker signal:

```text
unknown_values_found
```

`BuildSqlPreviewStage` должен использовать этот сигнал и заблокировать безопасный SQL preview:

```text
unknown_values_exist
```

`AnalyzeValuesStage` сама не формирует SQL preview, но обязана оставить достаточно данных для блокировки.

---

## allow_empty

Если:

```text
allow_empty = 0
```

и найдены пустые значения, stage должна добавить warning:

```text
empty_values_found
```

Если пустые значения критичны для конкретного parser-а, stage может добавить ошибку или blocker signal.

На первом этапе пустые значения рекомендуется считать warning, если отдельно не задано более строгое правило.

---

## Предупреждения

Stage может добавить предупреждения в:

```text
AttributeContext.warnings
```

Возможные предупреждения:

```text
unknown_values_found
invalid_values_found
empty_values_found
value_normalization_partial
unit_was_missing_but_assumed
value_parser_warnings_found
```

---

## Ошибки

Критические ошибки:

```text
raw_values_missing
value_parser_empty
value_parser_unknown
value_parser_failed
value_rules_invalid
```

Ошибки записываются в:

```text
AttributeContext.errors
AttributeContext.stage_results.analyze_values.errors
```

---

## Preconditions

Stage может выполняться только если:

```text
AttributeContext.attribute_value_structure.raw_values заполнен
AttributeContext.job.raw_job.value_rules заполнен
value_rules.value_parser задан
AttributeContext.errors не содержит критических ошибок предыдущих stages
```

Если preconditions не выполнены, stage должна получить статус:

```text
skipped
```

или:

```text
blocked
```

в зависимости от причины.

---

## Stage result

Результат stage записывается в:

```text
AttributeContext.stage_results.analyze_values
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
started_at: 2026-07-03 12:15:00
finished_at: 2026-07-03 12:15:04
errors: []
warnings:
  - unknown_values_found
summary:
  total_values: 20
  normalized: 17
  unknown: 2
  invalid: 1
  empty: 0
```

---

## Влияние на SQL preview

`AnalyzeValuesStage` не формирует SQL preview.

Но её результат влияет на `BuildSqlPreviewStage`.

SQL preview не должен считаться безопасным, если:

```text
unknown_value_policy = block_sql
и unknown_values не пустой
```

Также SQL preview может быть заблокирован, если:

```text
invalid_values не пустой
value_parser_failed
value_rules_invalid
```

---

## Граница с ValueParser

`ValueParser` разбирает одно значение.

`AnalyzeValuesStage` отвечает за массовое применение parser-а ко всем значениям.

```text
ValueParser
→ one raw_text
→ one parse_result

AnalyzeValuesStage
→ all raw_values
→ value_report
```

---

## Граница с AnalyzeNamesStage

`AnalyzeNamesStage` формирует кандидатов по именам атрибутов.

`AnalyzeValuesStage` может использовать `synonym_candidates` как контекст, но не утверждает их.

Если значения кандидатов выглядят совместимыми, это может быть отражено в `value_report`, но финальное решение по синонимам остаётся ручным.

---

## Граница с AttributeExporter

`AttributeExporter` передаёт сырые значения как есть.

`AnalyzeValuesStage` не должна предполагать, что значения уже очищены или нормализованы.

---

## Граница с FrameworkResult

`FrameworkResult` должен получить из `AnalyzeValuesStage`:

```text
value_report
unknown_values
invalid_values
empty_values
warnings
diagnostics
```

---

## Пример результата

```yaml
attribute_value_structure:
  raw_values:
    - product_id: 1001
      attribute_id: 123
      raw_text: "96 мм"
      language_id: 3

    - product_id: 1002
      attribute_id: 456
      raw_text: "4\""
      language_id: 3

    - product_id: 1003
      attribute_id: 456
      raw_text: "около 100 мм"
      language_id: 3

  normalized_values:
    - product_id: 1001
      attribute_id: 123
      raw_text: "96 мм"
      normalized_value: 96
      normalized_text: "96"
      value_type: decimal
      unit: mm
      parser: diameter_mm
      warnings: []

    - product_id: 1002
      attribute_id: 456
      raw_text: "4\""
      normalized_value: 101.6
      normalized_text: "101.6"
      value_type: decimal
      unit: mm
      parser: diameter_mm
      warnings:
        - inch_converted_to_mm

  unknown_values:
    - product_id: 1003
      attribute_id: 456
      raw_text: "около 100 мм"
      reason: ambiguous_value

  invalid_values: []

  empty_values: []

  diagnostics:
    total_values: 3
    normalized_count: 2
    unknown_count: 1
    invalid_count: 0
    empty_count: 0
    unique_normalized_values:
      - 96
      - 101.6

value_report:
  parser: diameter_mm
  value_type: decimal
  unit: mm
  total_values: 3
  normalized_count: 2
  unknown_count: 1
  invalid_count: 0
  empty_count: 0
  unique_normalized_values:
    - 96
    - 101.6

warnings:
  - unknown_values_found

stage_results:
  analyze_values:
    status: ok_with_warnings
    errors: []
    warnings:
      - unknown_values_found
    summary:
      total_values: 3
      normalized: 2
      unknown: 1
      invalid: 0
      empty: 0
```

---

## Требования к реализации

Реализация `AnalyzeValuesStage` должна быть:

* детерминированной;
* безопасной;
* объяснимой;
* без записи в БД;
* без автоматического применения SQL;
* устойчивой к мусорным значениям;
* пригодной для тестирования на наборах raw values;
* расширяемой через новые `ValueParser`.

---

## Статус документа

Документ является архитектурным контрактом `AnalyzeValuesStage`.

Код реализации должен следовать этому контракту.
