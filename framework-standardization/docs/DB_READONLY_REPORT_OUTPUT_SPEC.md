# DB-readonly Report Output Spec

Mini-spec для будущего отображения read-only diagnostics в `build_report`.

Документ описывает, как `build_report` может в будущем показывать diagnostics из:

```text
attribute_value_structure.diagnostics.raw_profile
sql_preview.diagnostics
```

Реализацию в рамках этого шага не делать.

## Purpose

Сделать DB-readonly diagnostics видимыми человеку в итоговом report, не превращая profiling в production decision.

Главная идея:

```text
build_report может отображать diagnostics, но не должен принимать решений.
```

## Current State

Текущий DB-readonly path:

```text
resolve_canonical       -> DB-backed
resolve_scope           -> DB-backed
export_attributes       -> DB-backed
analyze_names           -> DB-readonly-compatible adapter
analyze_values          -> DB-readonly-compatible adapter with raw profiling
build_sql_preview       -> DB-readonly-compatible blocked preview
build_report            -> dry-run
build_framework_result  -> dry-run
```

`analyze_values` уже может отдавать read-only profiling facts:

```text
attribute_value_structure.diagnostics.raw_profile
```

`build_sql_preview` уже может отдавать read-only summary:

```text
sql_preview.diagnostics
```

`build_sql_preview` остаётся blocked preview:

```text
generated = 0
safe_to_apply = 0
statements = array()
blocked_by contains db_readonly_sql_preview_not_implemented
```

## Report Boundary

`build_report` остаётся reporting-only / dry-run stage.

Он может:

- читать `AttributeContext`;
- отображать уже накопленные facts;
- показывать raw value profiling summary;
- показывать SQL preview safety summary;
- показывать warnings/errors/stage statuses;
- объяснять, что данные являются diagnostics-only.

Он не должен:

- делать normalization;
- принимать reject / approve decisions;
- утверждать synonyms;
- создавать SQL;
- создавать SQL files;
- создавать apply plan;
- менять `safe_to_apply`;
- менять `statements`;
- менять `sql_preview`;
- менять pipeline wiring;
- менять runners;
- менять default dry-run path.

## Future Raw Profile Report Output

В будущем report может отображать raw profile summary:

```text
total values
unique raw values
empty values
suspicious counts
top raw values count
source
```

Рекомендуемые поля report summary:

```text
raw_profile.total_values
raw_profile.unique_raw_values_count
raw_profile.empty_values_count
raw_profile.suspicious_no_digits_count
raw_profile.suspicious_long_value_count
raw_profile.suspicious_multiple_numbers_count
raw_profile.top_raw_values_count
raw_profile.source
```

Опционально report может показывать top raw values как compact list:

```text
raw_text
count
example_product_ids
```

Ограничение:

```text
top raw values are diagnostics-only
```

Они не означают canonical normalized values.

## Future SQL Preview Safety Summary

В будущем report может отображать SQL preview safety summary:

```text
generated = 0
safe_to_apply = 0
statements count = 0
blocked_by contains db_readonly_sql_preview_not_implemented
```

Рекомендуемые поля report summary:

```text
sql_preview.generated
sql_preview.safe_to_apply
sql_preview.apply_changes
sql_preview.statement_count
sql_preview.blocked_by
sql_preview.diagnostics.raw_profile_present
sql_preview.diagnostics.raw_profile_total_values
sql_preview.diagnostics.unique_raw_values_count
sql_preview.diagnostics.empty_values_count
sql_preview.diagnostics.suspicious_no_digits_count
sql_preview.diagnostics.suspicious_long_value_count
sql_preview.diagnostics.suspicious_multiple_numbers_count
sql_preview.diagnostics.top_raw_values_count
```

Report must preserve the meaning:

```text
blocked preview means no SQL/apply readiness
```

## Diagnostics Are Not Decisions

The report must not turn diagnostics into production decisions.

Raw profile facts mean:

```text
observed raw DB value patterns
```

They do not mean:

- reject;
- approve;
- normalize;
- update;
- apply;
- SQL-ready.

Suspicious counters mean:

```text
review this data manually
```

They do not mean invalid values or blocked apply decisions.

## Safe Report Language

Report wording should be explicit:

```text
read-only diagnostics
profiling only
not normalized
no SQL generated
safe_to_apply = 0
```

Avoid wording like:

```text
approved
rejected
ready to apply
will update
valid normalized value
```

until a separate production normalization / SQL apply architecture exists.

## Default Dry-run Boundary

Default dry-run path must not change:

```text
bin/dry-run.php
PipelineFactory::createDefault()
config/jobs/pump_diameter.php
DryRunReportBuilder
```

Any future DB-readonly report output work must not alter default fixture report behavior unless explicitly approved.

## Verification Plan для будущей реализации

После будущей реализации report output выполнить:

### Syntax checks

```text
C:\php56\php.exe -l framework-standardization\src\Report\<changed-report-file>.php
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

- report содержит raw profile summary, если raw profile есть;
- report содержит SQL preview safety summary;
- report явно показывает `safe_to_apply = 0`;
- report явно показывает `statement_count = 0`;
- report не показывает apply-ready language;
- `sql_preview.safe_to_apply` не меняется;
- `sql_preview.statements` не меняется;
- executable SQL не появляется;
- runtime config и dump files не попали в git.

## Out of Scope

Вне scope:

- реализация в текущем шаге;
- PHP-код;
- HANDOFF.md update;
- pipeline wiring;
- runners;
- default dry-run path changes;
- runtime config changes;
- production normalization;
- reject / approve decisions;
- SQL generation;
- SQL files;
- apply plan;
- SQL apply;
- live DB;
- write/schema operations;
- OpenCart module paths.

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

Project records:

- `docs/DECISIONS.md`
- `docs/RUNTIME_CHECKS.md`

Implementation/context commits:

- `0a470df Add DB readonly raw value profiling`
- `ecd9196 Add DB readonly SQL preview raw profile diagnostics`
- `3d731fa Document DB readonly SQL preview diagnostics decision`

## Recommended Boundary

`build_report` may make diagnostics visible to a human.

It must not turn:

```text
raw profiling
sql preview diagnostics
```

into:

```text
production normalization
reject / approve decision
SQL generation
apply plan
safe-to-apply result
```
