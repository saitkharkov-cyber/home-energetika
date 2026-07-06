# DB-readonly Value Profiling Spec

Mini-spec для будущего усиления `DbReadOnlyAttributeValueAnalyzer` как read-only value profiling stage.

Документ описывает будущий контракт profiling. Реализацию в рамках этого шага не делать.

## Purpose

Определить безопасный следующий engineering step для `analyze_values` в DB-readonly path:

```text
read-only value profiling без normalization
```

Цель profiling - дать инженерную видимость по real DB raw values, не переходя к production normalization, SQL preview generation или SQL apply.

## Current State

Текущий DB-readonly path:

```text
resolve_canonical       -> DB-backed
resolve_scope           -> DB-backed
export_attributes       -> DB-backed
analyze_names           -> DB-readonly-compatible adapter
analyze_values          -> DB-readonly-compatible adapter
build_sql_preview       -> DB-readonly-compatible blocked preview
build_report            -> dry-run
build_framework_result  -> dry-run
```

`DbReadOnlyAttributeValueAnalyzer` сейчас:

- принимает real DB `raw_values` из `export_attributes`;
- проверяет, что `raw_values` не пустые;
- проверяет real `product_id` и real `attribute_id`;
- сохраняет `raw_values`;
- оставляет `normalized_values` пустым;
- считает базовые diagnostics;
- собирает несколько examples;
- отмечает empty values;
- возвращает marker `db_readonly_values_not_normalized`.

Он сейчас не делает:

- production normalization;
- unit conversion;
- parser-driven normalization;
- semantic unknown/invalid classification;
- SQL blocker decisions;
- apply-ready data.

## Input Contract

`DbReadOnlyAttributeValueAnalyzer` сохраняет public contract:

```php
analyze(array $canonical, array $rawValues, array $valueRules)
```

Обязательные входные данные:

- `canonical.canonical_code = pump_diameter`;
- `canonical.target_attribute_id` как real OpenCart attribute ID;
- `rawValues[]`;
- `rawValues[].product_id` как real DB product ID;
- `rawValues[].attribute_id` как real DB attribute ID;
- `rawValues[].raw_text`;
- `rawValues[].language_id`;
- `valueRules.value_parser = diameter_mm`;
- `valueRules.value_type`;
- `valueRules.unit`.

Входные данные не должны содержать fixture placeholders:

```text
product_id = 0
attribute_id = 0
```

## Output Contract

Будущий profiling должен сохранить текущий high-level result shape:

```text
analyzed
attribute_value_structure
value_report
errors
warnings
source = local_dump_db_readonly
```

### attribute_value_structure

Рекомендуемый контракт:

```text
attribute_value_structure.raw_values
attribute_value_structure.normalized_values
attribute_value_structure.unknown_values
attribute_value_structure.invalid_values
attribute_value_structure.empty_values
attribute_value_structure.diagnostics
```

`raw_values` сохраняются без изменения.

`normalized_values` остаётся пустым:

```text
normalized_values = array()
```

Это обязательная граница: profiling не должен создавать apply-ready normalized data.

### Где хранить profiling

Выбранный вариант:

```text
attribute_value_structure.diagnostics.raw_profile
```

Причина:

- `diagnostics` уже является местом для read-only facts;
- `raw_profile` явно показывает, что данные относятся к raw text;
- не создаётся новый top-level contract без необходимости;
- downstream `BuildSqlPreviewStage` уже читает `attribute_value_structure` и может игнорировать unknown diagnostic keys;
- profiling не смешивается с `normalized_values`.

Пример будущего блока:

```php
'diagnostics' => array(
    'total_values' => 385,
    'normalized_count' => 0,
    'unknown_count' => 0,
    'invalid_count' => 0,
    'empty_count' => 0,
    'unique_normalized_values' => array(),
    'raw_profile' => array(
        'unique_raw_values_count' => 25,
        'top_raw_values' => array(),
        'raw_value_frequencies' => array(),
        'min_raw_length' => 2,
        'max_raw_length' => 40,
        'avg_raw_length' => 7.5,
        'contains_digits_count' => 380,
        'contains_unit_mm_count' => 300,
        'suspicious_no_digits_count' => 5,
        'suspicious_long_value_count' => 2,
        'suspicious_multiple_numbers_count' => 10,
        'source' => 'local_dump_db_readonly',
    ),
    'source' => 'local_dump_db_readonly',
)
```

### value_report

`value_report` может получить read-only summary:

- total values;
- empty count;
- unique raw values count;
- top raw values;
- examples;
- note `db_readonly_values_not_normalized`.

`value_report` не должен содержать apply-ready semantics:

- no approved normalized values;
- no SQL-ready values;
- no safe-to-apply flag;
- no production rejection/approval.

## Разрешённые diagnostics

Следующие diagnostics разрешены для будущего profiling.

### total_values

Разрешено.

Смысл: общее количество raw values.

### unique_raw_values_count

Разрешено.

Смысл: количество уникальных raw text values после простого string grouping.

Не является normalization.

### empty_values_count

Разрешено.

Смысл: количество raw values, где `trim(raw_text) === ''`.

### top_raw_values

Разрешено.

Смысл: список самых частых raw text values с count.

Ограничить размер, например top 20.

### raw_value_frequencies

Разрешено.

Смысл: map raw text -> count.

Если массив станет слишком большим, будущая реализация может ограничить его и добавить diagnostic о truncation.

### examples

Разрешено.

Смысл: небольшая выборка raw values с `product_id`, `attribute_id`, `raw_text`.

### min_raw_length

Разрешено.

Смысл: минимальная длина raw text.

### max_raw_length

Разрешено.

Смысл: максимальная длина raw text.

### avg_raw_length

Разрешено.

Смысл: средняя длина raw text как diagnostic.

### contains_digits_count

Разрешено.

Смысл: количество raw values, где есть хотя бы одна цифра.

### contains_unit_mm_count

Разрешено как heuristic diagnostic.

Смысл: количество raw values, где встречается `мм`, `mm` или близкий raw marker.

Это не unit conversion и не normalization.

### suspicious_no_digits_count

Разрешено только как diagnostic.

Смысл: raw value не содержит цифр.

Не означает reject.

### suspicious_long_value_count

Разрешено только как diagnostic.

Смысл: raw value длиннее будущего порога, например 64 characters.

Не означает reject.

### suspicious_multiple_numbers_count

Разрешено только как diagnostic.

Смысл: raw value содержит больше одного number-like fragment.

Не означает reject.

## Отложенные diagnostics

Отложить до отдельного parser/normalization spec:

- numeric parsing;
- decimal separator interpretation;
- range parsing;
- unit conversion;
- normalized numeric value;
- min/max numeric value;
- canonical unit validation;
- unknown/invalid semantic classification.

## Граница profiling vs normalization

Profiling:

- считает факты о raw text;
- группирует raw text как строки;
- считает frequencies;
- собирает examples;
- помечает suspicious patterns как diagnostics;
- не меняет meaning values.

Normalization:

- извлекает canonical numeric value;
- конвертирует units;
- принимает parser decisions;
- создаёт normalized values;
- может повлиять на SQL preview/apply.

В этом этапе разрешён только profiling.

Обязательное правило:

```text
attribute_value_structure.normalized_values must remain empty
```

## Как не сломать build_sql_preview

`DbReadOnlySqlPreviewBuilder` сейчас blocked preview.

Будущий profiling не должен менять это поведение.

После profiling:

- `safe_to_apply` остаётся `0`;
- `generated` остаётся `0`;
- `statements` остаётся пустым;
- `blocked_by` сохраняет причину, например `db_readonly_sql_preview_not_implemented`;
- SQL statements не создаются;
- executable SQL не появляется.

`build_sql_preview` может читать profiling diagnostics для отображения counts, но не должен превращать их в apply plan.

## Как не сломать default dry-run path

Default dry-run path не менять:

```text
bin/dry-run.php
PipelineFactory::createDefault()
config/jobs/pump_diameter.php
DryRunAttributeValueAnalyzer
DryRunSqlPreviewBuilder
```

Будущий value profiling должен подключаться только в DB-readonly composition path, если потребуется wiring change.

Dry-run fixture behavior должен остаться прежним:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

## Запрещённые действия

Запрещено:

- production normalization;
- unit conversion;
- parser-driven canonical value extraction;
- заполнение SQL-ready `normalized_values`;
- semantic reject/approve values;
- synonym decisions;
- SQL apply;
- executable SQL;
- SQL files;
- live DB;
- write/schema operations;
- pipeline wiring changes в рамках текущего documentation step;
- runner changes в рамках текущего documentation step;
- default dry-run path changes.

Запрещённые operation families:

```text
INSERT
UPDATE
DELETE
REPLACE
ALTER
DROP
TRUNCATE
CREATE
```

## Verification Plan для будущей реализации

После будущей реализации выполнить:

### Syntax checks

```text
C:\php56\php.exe -l framework-standardization\src\Analyzer\DbReadOnlyAttributeValueAnalyzer.php
```

Если менялись другие PHP-файлы, проверить каждый изменённый файл через `C:\php56\php.exe -l`.

### Default dry-run

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Ожидаемо:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

### DB-readonly runner

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Ожидаемо:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

### Contract checks

Проверить:

- `attribute_value_structure.raw_values` сохранён;
- `attribute_value_structure.normalized_values` пустой;
- `attribute_value_structure.diagnostics.raw_profile` присутствует;
- `raw_profile.total_values` или parent `diagnostics.total_values` соответствует count raw values;
- `raw_profile.unique_raw_values_count` заполнен;
- `raw_profile.top_raw_values` заполнен как read-only facts;
- `value_report` не содержит apply-ready semantics;
- `safe_to_apply` не становится `1`;
- `sql_preview.statements` остаётся пустым;
- нет `product_id = 0`;
- нет `attribute_id = 0`;
- runtime config и dump files не попали в git.

### Git status

```text
git status
```

## Out of Scope

Вне scope:

- реализация в текущем шаге;
- pipeline wiring;
- runner changes;
- default dry-run changes;
- runtime config changes;
- production normalization;
- unit conversion;
- parser registry;
- executable SQL;
- SQL apply;
- live DB;
- write/schema operations;
- OpenCart module paths;
- expansion beyond `pump_diameter`;
- expansion beyond `category_id = 11900213`;
- expansion beyond `language_id = 1`.

## Recommended Next Implementation Step

После отдельного подтверждения:

```text
усилить DbReadOnlyAttributeValueAnalyzer read-only raw value profiling diagnostics
```

Минимальный safe implementation:

- добавить `diagnostics.raw_profile`;
- добавить raw frequencies / top raw values;
- добавить length diagnostics;
- добавить suspicious diagnostics как non-blocking facts;
- оставить `normalized_values = array()`;
- не менять `DbReadOnlySqlPreviewBuilder` safe/apply behavior.
