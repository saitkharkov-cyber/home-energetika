# DB-readonly Framework Result Spec

Mini-spec для будущего отображения read-only diagnostics на уровне `build_framework_result`.

Документ описывает, как `build_framework_result` может в будущем агрегировать и показывать diagnostics из report/framework result, не превращая их в production или apply-ready output.

Реализацию в рамках этого шага не делать.

## Purpose

Сделать DB-readonly diagnostics видимыми на верхнем уровне результата.

Главная идея:

```text
framework_result может суммировать diagnostics, но не должен принимать production/apply decisions.
```

## Current State

Текущий DB-readonly path:

```text
resolve_canonical       -> DB-backed
resolve_scope           -> DB-backed
export_attributes       -> DB-backed
analyze_names           -> DB-readonly-compatible adapter
analyze_values          -> DB-readonly-compatible profiling adapter
build_sql_preview       -> DB-readonly-compatible blocked preview with raw_profile diagnostics summary
build_report            -> dry-run/reporting-only with diagnostics output
build_framework_result  -> dry-run
```

Уже доступны upstream diagnostics:

- `attribute_value_structure.diagnostics.raw_profile`;
- `sql_preview.diagnostics`;
- `report.raw_profile_summary`;
- `report.sql_preview_safety_summary`.

`build_framework_result` пока остаётся dry-run packaging stage.

## Result Boundary

`build_framework_result` остаётся dry-run / result packaging stage.

Он может:

- читать уже собранный `AttributeContext`;
- читать `report`;
- читать `sql_preview`;
- читать `attribute_value_structure.diagnostics.raw_profile`;
- агрегировать summary для верхнего уровня результата;
- показывать safety markers для человека;
- сохранять явный non-apply статус.

Он не должен:

- делать normalization;
- принимать reject / approve decisions;
- создавать SQL;
- создавать SQL files;
- создавать apply plan;
- менять `safe_to_apply`;
- менять `statements`;
- менять `sql_preview`;
- менять `report`;
- менять pipeline wiring;
- менять runners;
- менять default dry-run path.

## Future Top-level Diagnostics Summary

Будущий framework result может добавить top-level diagnostics summary.

Рекомендуемые поля:

```text
diagnostics_summary.raw_profile_present
diagnostics_summary.raw_profile_total_values
diagnostics_summary.unique_raw_values_count
diagnostics_summary.suspicious_no_digits_count
diagnostics_summary.suspicious_long_value_count
diagnostics_summary.suspicious_multiple_numbers_count
diagnostics_summary.report_has_raw_profile_summary
diagnostics_summary.report_has_sql_preview_safety_summary
diagnostics_summary.sql_preview_safe_to_apply
diagnostics_summary.sql_preview_statement_count
diagnostics_summary.blocked_preview
diagnostics_summary.blocked_by_contains_db_readonly_sql_preview_not_implemented
```

Смысл этих полей:

```text
read-only visibility for humans
```

Они не означают:

- normalized values;
- rejected values;
- approved values;
- SQL-ready values;
- safe-to-apply state.

## Future Top-level Safety Summary

Будущий framework result может добавить top-level safety summary.

Рекомендуемые поля:

```text
safety_summary.generated = 0
safety_summary.safe_to_apply = 0
safety_summary.statements_count = 0
safety_summary.sql_apply_allowed = 0
safety_summary.production_ready = 0
safety_summary.apply_plan_created = 0
```

Эта summary должна подтверждать текущую boundary:

```text
diagnostics are visible, apply is not allowed
```

Если `sql_preview.generated` или `report.generated` имеют существующий dry-run meaning, будущая реализация должна не смешивать его с production readiness. Для DB-readonly safety summary `production_ready` и `sql_apply_allowed` должны оставаться `0`.

## Source Data

Разрешённые источники для будущей агрегации:

```text
attribute_value_structure.diagnostics.raw_profile
sql_preview
sql_preview.diagnostics
report.raw_profile_summary
report.sql_preview_safety_summary
stage_results
warnings
errors
```

Приоритет для report-level output:

1. Использовать `report.raw_profile_summary`, если он есть.
2. Использовать `report.sql_preview_safety_summary`, если он есть.
3. При необходимости читать upstream diagnostics напрямую только как fallback для summary.

Нельзя использовать эти данные как apply input.

## Diagnostics Are Not Decisions

Top-level framework result не должен превращать diagnostics в production decisions.

`raw_profile` и report summary отвечают на вопрос:

```text
что видно в текущих raw DB facts?
```

Они не отвечают на вопрос:

```text
что нужно записать в DB?
```

`suspicious_*` diagnostics означают только:

```text
нужно посмотреть вручную
```

Они не означают:

- reject;
- approve;
- invalid;
- unknown;
- blocker для apply;
- safe-to-apply decision.

## SQL Preview Boundary

`build_framework_result` не должен менять SQL preview.

Обязательная безопасная форма для DB-readonly path:

```text
sql_preview.safe_to_apply = 0
sql_preview.generated = 0
sql_preview.apply_changes = 0
sql_preview.statements = array()
sql_preview.blocked_by contains db_readonly_sql_preview_not_implemented
```

Если future result summary выводит эти поля наверх, он должен копировать или агрегировать их как facts, а не пересчитывать apply readiness.

## Default Dry-run Boundary

Default dry-run path не менять:

```text
bin/dry-run.php
PipelineFactory::createDefault()
config/jobs/pump_diameter.php
DryRunFrameworkResultBuilder
```

Любая будущая DB-readonly result summary должна быть совместима с default dry-run output и не должна ломать fixture path.

Если потребуется разное поведение для DB-readonly и default dry-run, это должно быть отдельным implementation decision в result-related component без изменения default composition.

## Verification Plan для будущей реализации

После будущей реализации выполнить:

### Syntax checks

```text
C:\php56\php.exe -l framework-standardization\src\Result\<changed-result-file>.php
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

- framework result содержит top-level diagnostics summary, если report diagnostics есть;
- framework result содержит top-level safety summary;
- `raw_profile_present = 1` для текущего DB-readonly happy path;
- `raw_profile_total_values = 385` для текущего local dump check, если dump не изменён;
- `unique_raw_values_count = 14` для текущего local dump check, если dump не изменён;
- `report_has_raw_profile_summary = 1`;
- `report_has_sql_preview_safety_summary = 1`;
- `sql_preview_safe_to_apply = 0`;
- `sql_preview_statement_count = 0`;
- blocked preview marker присутствует;
- `production_ready = 0`;
- `sql_apply_allowed = 0`;
- `safe_to_apply` не меняется;
- `statements` не меняются;
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
- SQL diff;
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

- `docs/DB_READONLY_REPORT_OUTPUT_SPEC.md`
- `docs/DB_READONLY_VALUE_PROFILING_SPEC.md`
- `docs/DB_READONLY_SQL_PREVIEW_BOUNDARY_SPEC.md`

Project records:

- `docs/DECISIONS.md`
- `docs/RUNTIME_CHECKS.md`

Implementation/context commits:

- `50daba1 Add DB readonly diagnostics to report output`
- `a60c5d8 Document DB readonly report diagnostics checks`
- `e74ffc8 Document DB readonly report diagnostics decision`

## Recommended Boundary

`build_framework_result` may make diagnostics visible at the top level.

It must not turn:

```text
raw profiling
sql preview diagnostics
report diagnostics
```

into:

```text
production normalization
reject / approve decision
SQL generation
apply plan
safe-to-apply result
production-ready result
```
