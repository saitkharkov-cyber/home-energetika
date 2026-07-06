# DB-readonly Normalization Parser Skeleton Spec

Mini-spec для будущего standalone skeleton parser-а, который сможет превращать raw DB values в normalization proposals.

Документ описывает минимальный parser skeleton. Реализацию в рамках этого шага не делать.

## Purpose

Подготовить безопасный standalone parser skeleton между:

```text
raw_profile diagnostics
```

и:

```text
future normalization approval flow
```

Parser skeleton должен создавать normalization proposals, но не должен создавать approved/apply-ready values и не должен подключаться к SQL/apply.

## Current Context

Уже есть:

- `raw_profile` diagnostics в `attribute_value_structure.diagnostics.raw_profile`;
- DB-readonly SQL preview diagnostics;
- report/framework result diagnostics summaries;
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`.

Текущая граница:

- SQL/apply преждевременен без approval flow;
- parser может создавать proposals;
- parser не может создавать approved values;
- `approved` может появиться только через отдельный future approval flow;
- `safe_to_apply` остаётся `0`;
- `statements` остаётся `array()`.

## Future Parser Location

Будущий parser может жить в namespace:

```text
FrameworkStandardization\Normalizer
```

Пример будущего файла:

```text
framework-standardization/src/Normalizer/DbReadOnlyNormalizationProposalParser.php
```

Пример будущего класса:

```text
DbReadOnlyNormalizationProposalParser
```

В этом шаге файл parser-а не создавать.

## Input Contract

Parser получает read-only raw values из:

```text
attribute_value_structure.raw_values
```

или, для ограниченной standalone проверки:

```text
attribute_value_structure.diagnostics.raw_profile.examples
```

Предпочтительный input для будущей реализации:

```text
raw_values[]
```

Обязательные поля raw value:

- `product_id`;
- `attribute_id`;
- `language_id`;
- `target_attribute_id`;
- `raw_text` или `value`.

Для DB-readonly facts недопустимы:

```text
product_id = 0
attribute_id = 0
target_attribute_id = 0
```

Parser должен сохранять original raw text без изменения.

## Output Contract

Parser возвращает:

```text
normalization_value_proposals
parser_diagnostics
errors
warnings
source = local_dump_db_readonly
```

Минимальные поля proposal:

- `proposal_id` или deterministic key;
- `product_id`;
- `attribute_id`;
- `language_id`;
- `target_attribute_id`;
- `original_raw_value`;
- `parsed_value`;
- `proposed_normalized_value`;
- `proposed_unit`;
- `parser_confidence`;
- `parser_warnings`;
- `approval_status`;
- `source`.

Proposal не является apply-ready data.

## Allowed Approval Statuses

Полный набор будущих статусов:

```text
proposed
needs_review
unknown
rejected
approved
```

## Skeleton Parser Boundary

На первом skeleton/parser этапе parser не должен выставлять:

```text
approved
```

Разрешённые статусы для skeleton:

```text
proposed
needs_review
unknown
```

`rejected` и `approved` зарезервированы для future approval flow.

Parser skeleton не должен:

- принимать production decision;
- выполнять approval auto-pass;
- создавать SQL;
- создавать SQL files;
- создавать apply plan;
- менять DB;
- менять `safe_to_apply`;
- менять `statements`;
- менять SQL preview;
- менять pipeline wiring.

## Safe Parsing Rules

Первый safe skeleton может использовать только простые deterministic rules.

### Empty value

Если raw value пустой после trim:

```text
approval_status = unknown
parser_warnings includes empty_raw_value
```

### Single number without range

Если найдено одно число и не найден диапазон:

```text
approval_status = proposed
```

### Number with mm marker

Если найдено одно число и marker `мм` или `mm`:

```text
approval_status = proposed
proposed_unit = mm
```

### Decimal comma / dot

Decimal comma и decimal dot можно распознавать как number-like value:

```text
12,5
12.5
```

Skeleton может приводить parsed numeric representation для proposal, но это всё ещё не approved normalized value.

### Multiple numbers

Если найдено несколько number-like fragments:

```text
approval_status = needs_review
parser_warnings includes multiple_numbers_detected
```

### Range

Если raw value выглядит как диапазон:

```text
approval_status = needs_review
parser_warnings includes range_detected
```

Примеры range markers:

```text
10-20
10 – 20
10/20
от 10 до 20
```

### Text without numbers

Если raw value не пустой, но не содержит numbers:

```text
approval_status = unknown
parser_warnings includes no_number_detected
```

## Parser Diagnostics

Parser skeleton должен возвращать diagnostics:

- `total_raw_values`;
- `proposal_count`;
- `proposed_count`;
- `needs_review_count`;
- `unknown_count`;
- `rejected_count`;
- `approved_count`;
- `range_detected_count`;
- `multiple_numbers_count`;
- `unit_missing_count`;
- `low_confidence_count`.

Обязательная граница для skeleton:

```text
approved_count = 0
```

Если `rejected` пока не используется:

```text
rejected_count = 0
```

## Standalone Verification Plan

Будущую реализацию parser skeleton проверять standalone, без pipeline wiring.

### Syntax check

```text
C:\php56\php.exe -l framework-standardization\src\Normalizer\DbReadOnlyNormalizationProposalParser.php
```

### Standalone manual-check

Проверка должна:

- загрузить DB-readonly job/runtime только если нужен local dump context;
- получить raw values через существующие read-only components или fixture/manual array;
- вызвать parser напрямую;
- не запускать SQL apply;
- не создавать SQL files;
- не менять pipeline wiring.

Временный manual-check файл, если создаётся, удалить после проверки.

### Default dry-run regression

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

### DB-readonly runner regression

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

### Contract check

Проверить:

- `normalization_value_proposals` присутствует;
- proposals сохраняют `original_raw_value`;
- proposals содержат real `product_id`;
- proposals содержат real `attribute_id`;
- proposals содержат real `target_attribute_id`;
- allowed statuses только `proposed`, `needs_review`, `unknown` для skeleton;
- `approved_count = 0`;
- `rejected_count = 0`, если rejected ещё не реализован;
- `safe_to_apply = 0`;
- `statements_count = 0`;
- SQL не генерируется;
- SQL files не создаются;
- apply plan не создаётся.

## Out of Scope

Вне scope:

- PHP implementation;
- pipeline wiring;
- changing `analyze_values`;
- changing `sql_preview`;
- changing report/framework result;
- SQL generation;
- SQL files;
- SQL apply;
- apply plan;
- live DB;
- write/schema operations;
- HANDOFF update.

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

## References

Specs:

- `docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`
- `docs/DB_READONLY_VALUE_PROFILING_SPEC.md`

Project records:

- `docs/DECISIONS.md`
- `docs/RUNTIME_CHECKS.md`

## Recommended Boundary

Этот spec готовит только standalone parser skeleton.

Он не разрешает:

- production normalization;
- approval auto-pass;
- `approved` status из parser-а;
- SQL preview input;
- SQL generation;
- SQL apply.

Следующий безопасный implementation step:

```text
создать standalone DbReadOnlyNormalizationProposalParser без pipeline wiring и без approved/apply-ready output
```
