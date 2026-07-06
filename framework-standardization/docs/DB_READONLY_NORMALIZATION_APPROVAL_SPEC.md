# DB-readonly Normalization Approval Spec

Mini-spec для будущего production-facing слоя между raw value profiling и SQL/apply.

Документ описывает, что должно появиться, чтобы raw DB values можно было превратить в controlled normalization proposals, но ещё не в SQL apply.

Реализацию в рамках этого шага не делать.

## Purpose

Определить безопасную границу между:

```text
diagnostics-only profiling
```

и:

```text
production SQL/apply architecture
```

Главная идея:

```text
parser может создавать normalization proposals,
но только approval flow может сделать proposal кандидатом для будущего SQL preview.
```

Этот spec не разрешает SQL generation, SQL files, apply plan или SQL apply.

## Current State

Текущий DB-readonly path уже показывает diagnostics на нескольких уровнях:

- `analyze_values` создаёт `attribute_value_structure.diagnostics.raw_profile`;
- `build_sql_preview` показывает raw profile summary в `sql_preview.diagnostics`;
- `build_report` показывает `raw_profile_summary` и `sql_preview_safety_summary`;
- `build_framework_result` может показывать top-level diagnostics/safety summary.

При этом:

- `normalized_values` не являются apply-ready data;
- `build_sql_preview` остаётся blocked preview;
- `safe_to_apply = 0`;
- `statements = array()`;
- SQL/apply запрещены до отдельной architecture.

## Why SQL/apply Is Premature

SQL/apply преждевременен без approval flow, потому что raw profiling отвечает только на вопрос:

```text
что сейчас лежит в raw DB values?
```

Он не отвечает на вопросы:

- какое canonical value нужно записать;
- достаточно ли parser confidence;
- какие parser warnings допустимы;
- кто approved proposal;
- какие values rejected;
- какие values требуют manual review;
- можно ли строить SQL diff;
- можно ли считать результат safe-to-apply.

Без approval flow parser output может быть только proposal, а не production decision.

## Required Entities

Будущий слой должен явно различать следующие сущности.

### Raw value

Исходное значение из DB.

Минимальные поля:

- `product_id`;
- `attribute_id`;
- `language_id`;
- `raw_text`;
- `target_attribute_id`;
- `source = local_dump_db_readonly`.

`raw_text` должен сохраняться без потери original value.

### Parsed value

Технический результат parser-а.

Примеры полей:

- `numeric_value`;
- `unit_marker`;
- `decimal_separator`;
- `range_detected`;
- `parser_warnings`;
- `parser_confidence`.

Parsed value не является approved normalized value.

### Normalized value proposal

Предложение normalized value, полученное из raw value и parsed value.

Минимальные поля:

- `proposal_id` или deterministic proposal key;
- `canonical_code`;
- `target_attribute_id`;
- `product_id`;
- `attribute_id`;
- `original_raw_value`;
- `parsed_value`;
- `proposed_normalized_value`;
- `proposed_unit`;
- `parser_confidence`;
- `parser_warnings`;
- `approval_status`;
- `source`.

Proposal не является apply input, пока не получил explicit approved status.

### Parser confidence

Числовая или категориальная оценка уверенности parser-а.

Пример:

```text
high
medium
low
```

или:

```text
0.0 ... 1.0
```

Confidence не должен автоматически превращаться в `approved`.

### Parser warnings

Список диагностических предупреждений parser-а.

Примеры:

- `range_detected`;
- `multiple_numbers_detected`;
- `unit_missing`;
- `ambiguous_decimal_separator`;
- `unsupported_format`;
- `empty_raw_value`.

Warnings не являются reject / approve decisions.

### Manual approval status

Явный статус review/approval для proposal.

Approval status должен быть отдельным полем, а не выводиться автоматически из parser confidence.

### Rejected proposal

Proposal, который человек или future approval policy отклонили.

Rejected proposal не должен попадать в SQL preview input.

### Unknown value

Raw value, для которого parser не смог создать reliable proposal или требуется отдельная обработка.

Unknown value не должен попадать в SQL preview input.

## Allowed Statuses

Допустимые статусы для normalization proposal:

```text
proposed
approved
rejected
needs_review
unknown
```

### proposed

Parser создал proposal, но approval ещё не выполнен.

Не является apply input.

### approved

Proposal явно approved через future approval flow.

Только approved normalized proposals могут стать input для будущего SQL preview.

Даже approved proposal сам по себе ещё не означает SQL apply.

### rejected

Proposal отклонён.

Не является apply input.

### needs_review

Proposal требует ручной проверки.

Не является apply input.

### unknown

Parser не смог безопасно интерпретировать raw value.

Не является apply input.

## Parser Responsibilities

Будущий parser может:

- парсить числа;
- распознавать `мм`;
- распознавать `mm`;
- распознавать decimal comma;
- распознавать decimal dot;
- распознавать диапазоны только как `needs_review`;
- сохранять original raw value;
- создавать parser diagnostics;
- создавать `normalized_value_proposals` со статусом `proposed`, `needs_review` или `unknown`.

Parser должен сохранять traceability:

```text
original_raw_value -> parsed_value -> normalized_value_proposal
```

## Parser Non-responsibilities

Parser не должен:

- сам принимать production decision;
- сам выставлять `approved`;
- создавать SQL;
- создавать SQL files;
- создавать apply plan;
- менять DB;
- менять `safe_to_apply`;
- менять `statements`;
- выполнять SQL apply;
- использовать live DB;
- выполнять write/schema operations.

Parser output без approval остаётся proposal/diagnostics.

## Approval Boundary

Approval boundary:

```text
only approved normalized proposals can become input for future SQL preview
```

Не являются apply input:

- raw diagnostics;
- raw_profile;
- parser diagnostics;
- `proposed`;
- `needs_review`;
- `unknown`;
- `rejected`;
- suspicious diagnostics;
- unapproved normalized proposals.

Даже approved normalized proposal не разрешает SQL apply сам по себе. Для SQL preview/apply нужен отдельный production SQL/apply spec.

## Data Contract

Будущий output `analyze_values` или отдельного normalization stage может добавить:

```text
attribute_value_structure.normalized_value_proposals
attribute_value_structure.parser_diagnostics
attribute_value_structure.approval_summary
```

### normalized_value_proposals

Рекомендуемый shape:

```text
proposal_id
canonical_code
target_attribute_id
product_id
attribute_id
language_id
original_raw_value
parsed_value
proposed_normalized_value
proposed_unit
parser_confidence
parser_warnings
approval_status
source
```

### approval_status

Значение только из allowed statuses:

```text
proposed
approved
rejected
needs_review
unknown
```

### parser_diagnostics

Рекомендуемые поля:

```text
total_raw_values
parsed_count
proposal_count
approved_count
rejected_count
needs_review_count
unknown_count
range_detected_count
multiple_numbers_count
unit_missing_count
low_confidence_count
source
```

### original_raw_value

Обязательное поле для traceability.

Нельзя терять связь с исходным `raw_text`.

### target_attribute_id

Должен приходить из resolved canonical data.

Fixture `attribute_id = 0` или `target_attribute_id = 0` недопустимы в DB-readonly proposal facts.

### product_ids/examples

Proposal должен сохранять real `product_id`.

Для grouped display можно добавлять examples:

```text
example_product_ids
example_raw_values
```

Но grouped examples не заменяют per-product proposal records, если future SQL preview должен знать конкретные product rows.

## SQL Preview Boundary

Будущий SQL preview может читать только approved normalized proposals.

Текущий `DbReadOnlySqlPreviewBuilder` должен оставаться blocked preview до отдельной production SQL/apply architecture.

Обязательная текущая форма:

```text
generated = 0
safe_to_apply = 0
apply_changes = 0
statements = array()
blocked_by contains db_readonly_sql_preview_not_implemented
```

Этот spec не разрешает менять эти поля.

## Report / Framework Result Boundary

Report и framework result могут в будущем показывать:

- proposal counts;
- approved/rejected/needs_review/unknown counts;
- parser warning counts;
- examples;
- approval status summary.

Но они не должны:

- принимать approval decisions;
- создавать SQL;
- менять `safe_to_apply`;
- менять `statements`;
- объявлять production readiness.

## Verification Plan для будущей реализации

После будущей реализации normalization proposal layer выполнить:

### Syntax checks

```text
C:\php56\php.exe -l framework-standardization\src\<changed-file>.php
```

Проверить каждый изменённый PHP-файл.

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

- raw values сохраняют `original_raw_value`;
- proposals содержат real `product_id`;
- proposals содержат real `target_attribute_id`;
- parser confidence заполнен;
- parser warnings сохраняются;
- `approval_status` использует только allowed statuses;
- parser не выставляет `approved` автоматически;
- `proposed` не считается apply input;
- `needs_review` не считается apply input;
- `unknown` не считается apply input;
- `rejected` не считается apply input;
- only approved proposals могут быть future SQL preview input;
- `safe_to_apply` остаётся `0`;
- `statements` остаётся `array()`;
- executable SQL не появляется;
- SQL files не появляются;
- apply plan не появляется;
- runtime config и dump files не попали в git.

## Out of Scope

Вне scope:

- реализация;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- OpenCart module runtime;
- write/schema operations;
- changing default dry-run path;
- changing runners;
- changing pipeline wiring;
- production SQL/apply architecture.

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

- `docs/DB_READONLY_VALUE_PROFILING_SPEC.md`
- `docs/DB_READONLY_SQL_PREVIEW_BOUNDARY_SPEC.md`
- `docs/DB_READONLY_REPORT_OUTPUT_SPEC.md`
- `docs/DB_READONLY_FRAMEWORK_RESULT_SPEC.md`

Project records:

- `docs/DECISIONS.md`
- `docs/RUNTIME_CHECKS.md`

## Recommended Next Step

Следующий безопасный engineering step после этого spec:

```text
mini-spec или standalone skeleton для normalization proposal parser без approval auto-pass и без SQL/apply
```

Границы следующего шага:

- parser может создавать proposals;
- parser не может создавать approved proposals;
- parser не может менять SQL preview;
- parser не может включать `safe_to_apply`;
- parser не может создавать SQL/apply output.
