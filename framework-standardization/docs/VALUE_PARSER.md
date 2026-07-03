# Value Parser

Документ описывает контракт `ValueParser` — компонента нормализации одного значения характеристики в Framework Standardization.

`ValueParser` используется pipeline stage:

```text
AnalyzeValuesStage
````

и заполняет результат нормализации в `AttributeContext.attribute_value_structure`.

---

## Назначение

`ValueParser` нужен для приведения сырого текстового значения характеристики к ожидаемому нормализованному виду.

Пример:

```text
"96 мм" → 96
"96мм"  → 96
"4\""   → 101.6
```

`ValueParser` работает только с одним значением за один вызов.

---

## Главный принцип

`ValueParser` не работает с БД.

Он не знает про товары, категории, атрибуты, группы, импорты и каноны.

Он получает строку и правила обработки, возвращает результат разбора.

```text
raw_text
+ value_rules
→ ValueParser
→ parse_result
```

---

## Ответственность ValueParser

`ValueParser` отвечает за:

* очистку одного значения;
* нормализацию пробелов;
* распознавание единиц измерения;
* преобразование значения к нужному типу;
* выявление пустых значений;
* выявление неизвестных значений;
* выявление невалидных значений;
* возврат причины ошибки или неопределённости.

---

## Что ValueParser не делает

`ValueParser` не должен:

* читать БД;
* писать в БД;
* искать атрибуты;
* выбирать канон;
* решать, что является синонимом;
* анализировать usage count;
* работать со списком товаров;
* формировать SQL preview;
* утверждать значения;
* изменять импорты.

---

## Место в pipeline

`ValueParser` вызывается внутри:

```text
AnalyzeValuesStage
```

Порядок:

```text
ExportAttributesStage
→ AnalyzeNamesStage
→ AnalyzeValuesStage
```

`ExportAttributesStage` подготавливает сырые значения:

```text
AttributeContext.attribute_value_structure.raw_values
```

`AnalyzeValuesStage` передаёт каждое значение в `ValueParser`.

---

## Входные данные

Минимальные входные данные одного вызова:

```text
raw_text
value_rules
```

---

## raw_text

Сырое значение из OpenCart.

Примеры:

```text
96 мм
96мм
4"
около 100 мм
-
```

Значение передаётся как есть, без предварительной смысловой нормализации.

---

## value_rules

Правила обработки значения из `Attribute Job`.

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

Имя parser-а.

Примеры:

```text
diameter_mm
integer
decimal
text_exact
```

---

### value_type

Ожидаемый тип результата.

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

Если характеристика текстовая, поле может быть пустым.

---

### allow_empty

Флаг, допускаются ли пустые значения.

```text
0 / 1
```

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

Эта политика не обязательно применяется внутри самого `ValueParser`.

`ValueParser` возвращает статус значения, а `AnalyzeValuesStage` решает, как этот статус влияет на SQL preview.

---

## Выходные данные

`ValueParser` возвращает `parse_result`.

```text
parse_result
├─ status
├─ raw_text
├─ normalized_value
├─ normalized_text
├─ value_type
├─ unit
├─ parser
├─ reason
└─ warnings
```

---

## status

Статус результата разбора.

Допустимые значения:

```text
normalized
empty
unknown
invalid
```

---

### normalized

Значение успешно распознано и приведено к ожидаемому виду.

Пример:

```yaml
status: normalized
raw_text: "96 мм"
normalized_value: 96
value_type: decimal
unit: mm
```

---

### empty

Значение пустое или является техническим маркером пустоты.

Примеры:

```text
""
" "
"-"
"—"
```

Если `allow_empty = 0`, `AnalyzeValuesStage` может считать это проблемой.

---

### unknown

Значение не удалось уверенно распознать.

Пример:

```yaml
status: unknown
raw_text: "около 100 мм"
reason: ambiguous_value
```

`unknown` не означает, что значение точно неверное.

Это значит, что parser не может безопасно нормализовать его автоматически.

---

### invalid

Значение нарушает ожидаемый формат, тип или диапазон.

Пример:

```yaml
status: invalid
raw_text: "abc"
reason: not_numeric
```

---

## normalized_value

Нормализованное значение в машинном виде.

Примеры:

```text
96
101.6
220
true
```

Если статус не `normalized`, поле может быть пустым.

---

## normalized_text

Нормализованное значение в текстовом виде, пригодном для SQL preview или отчёта.

Примеры:

```text
96
101.6
220
да
```

---

## reason

Причина результата, если значение не было успешно нормализовано.

Примеры:

```text
empty_value
ambiguous_value
not_numeric
unsupported_unit
multiple_values
out_of_range
```

---

## warnings

Предупреждения по конкретному значению.

Примеры:

```text
unit_was_missing_but_assumed
inch_converted_to_mm
spaces_normalized
```

---

## Базовый алгоритм

Рекомендуемый общий порядок обработки:

```text
1. Получить raw_text
2. Проверить null / empty
3. Нормализовать пробелы, если normalize_spaces = 1
4. Удалить технический мусор, если это разрешено parser-ом
5. Распознать число / текст / boolean
6. Распознать единицу измерения
7. При необходимости выполнить конвертацию единиц
8. Проверить тип результата
9. Вернуть parse_result
```

---

## Пример parser-а diameter_mm

`diameter_mm` нормализует диаметр в миллиметрах.

Примеры:

```text
"96 мм" → 96
"96мм"  → 96
"101"   → 101
"4\""   → 101.6
```

Правила:

* значение без единицы может считаться миллиметрами, если это разрешено конфигурацией;
* дюймы могут конвертироваться в миллиметры;
* приблизительные значения не нормализуются автоматически;
* диапазоны не нормализуются автоматически на первом этапе.

---

## Примеры результатов

### Успешная нормализация

```yaml
status: normalized
raw_text: "96 мм"
normalized_value: 96
normalized_text: "96"
value_type: decimal
unit: mm
parser: diameter_mm
reason:
warnings: []
```

---

### Конвертация дюймов

```yaml
status: normalized
raw_text: "4\""
normalized_value: 101.6
normalized_text: "101.6"
value_type: decimal
unit: mm
parser: diameter_mm
reason:
warnings:
  - inch_converted_to_mm
```

---

### Неизвестное значение

```yaml
status: unknown
raw_text: "около 100 мм"
normalized_value:
normalized_text:
value_type: decimal
unit: mm
parser: diameter_mm
reason: ambiguous_value
warnings: []
```

---

### Невалидное значение

```yaml
status: invalid
raw_text: "abc"
normalized_value:
normalized_text:
value_type: decimal
unit: mm
parser: diameter_mm
reason: not_numeric
warnings: []
```

---

### Пустое значение

```yaml
status: empty
raw_text: "-"
normalized_value:
normalized_text:
value_type: decimal
unit: mm
parser: diameter_mm
reason: empty_value
warnings: []
```

---

## Граница с AnalyzeValuesStage

`ValueParser` возвращает результат разбора одного значения.

`AnalyzeValuesStage` отвечает за:

* перебор всех raw values;
* вызов нужного parser-а;
* распределение результатов по структурам:

  * `normalized_values`;
  * `unknown_values`;
  * `invalid_values`;
  * `empty_values`;
* формирование `value_report`;
* применение `unknown_value_policy`;
* добавление warnings/errors в `AttributeContext`.

---

## Граница с AttributeExporter

`AttributeExporter` передаёт сырые значения как есть.

Он не должен предварительно нормализовать значения.

```text
AttributeExporter
→ raw_values

ValueParser
→ normalized / unknown / invalid / empty
```

---

## Граница с SQL preview

`ValueParser` не формирует SQL.

Он только возвращает нормализованное значение.

Решение о том, можно ли строить SQL preview, принимает `BuildSqlPreviewStage`.

---

## Ошибки parser-а

Parser должен различать:

```text
ожидаемую невозможность распознать значение
```

и

```text
техническую ошибку выполнения parser-а
```

Пример:

```text
"около 100 мм"
→ status: unknown
→ не техническая ошибка

parser crashed
→ value_parser_failed
→ ошибка AnalyzeValuesStage
```

---

## Расширение набора parser-ов

Новый parser можно добавить, если:

* у него есть отдельное имя;
* он работает с одним значением;
* у него описаны входы и выходы;
* он возвращает стандартный `parse_result`;
* он не работает с БД;
* он не принимает решений о синонимах.

Примеры будущих parser-ов:

```text
diameter_mm
head_m
flow_l_min
power_w
voltage_v
phase
float_switch
material
```

---

## Требования к реализации

Реализация `ValueParser` должна быть:

* детерминированной;
* безопасной;
* без побочных эффектов;
* без записи в БД;
* пригодной для тестирования на наборе строк;
* расширяемой через новые parser-ы.

---

## Статус документа

Документ является архитектурным контрактом `ValueParser`.

Код реализации должен следовать этому контракту.

