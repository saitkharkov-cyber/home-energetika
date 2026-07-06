# DB-readonly SQL Preview Boundary Spec

Mini-spec для границы `build_sql_preview` после появления read-only raw value profiling.

Документ описывает, что может и чего не может делать `DbReadOnlySqlPreviewBuilder`, когда upstream `analyze_values` отдаёт:

```text
attribute_value_structure.diagnostics.raw_profile
```

Реализацию в рамках этого шага не делать.

## Purpose

Ответить на главный вопрос:

```text
должен ли build_sql_preview просто отображать profiling diagnostics как blocked preview,
не создавая SQL statements и не переходя в safe-to-apply режим?
```

Решение:

```text
да, должен оставаться blocked preview
```

`raw_profile` можно использовать только как read-only diagnostics.

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

`DbReadOnlySqlPreviewBuilder` сейчас:

- не генерирует executable SQL;
- не создаёт SQL files;
- не создаёт apply plan;
- возвращает blocked preview;
- держит `safe_to_apply = 0`;
- держит `generated = 0`;
- держит `statements = array()`;
- держит `apply_changes = 0`;
- фиксирует blocker `db_readonly_sql_preview_not_implemented`.

## Upstream Data After Value Profiling

После DB-readonly `analyze_values` доступны:

```text
attribute_value_structure.raw_values
attribute_value_structure.normalized_values
attribute_value_structure.unknown_values
attribute_value_structure.invalid_values
attribute_value_structure.empty_values
attribute_value_structure.diagnostics
attribute_value_structure.diagnostics.raw_profile
value_report
```

Ключевая граница:

```text
normalized_values = array()
```

`raw_profile` содержит profiling facts по raw text, например:

- `total_values`;
- `unique_raw_values_count`;
- `empty_values_count`;
- `top_raw_values`;
- `raw_value_frequencies`;
- `examples`;
- length diagnostics;
- suspicious diagnostics;
- `source = local_dump_db_readonly`.

Эти данные не являются normalized/apply-ready values.

## Что DbReadOnlySqlPreviewBuilder может читать

`DbReadOnlySqlPreviewBuilder` может читать из:

```text
attribute_value_structure.diagnostics.raw_profile
```

только read-only summary fields.

Разрешено читать:

- `total_values`;
- `unique_raw_values_count`;
- `empty_values_count`;
- `suspicious_no_digits_count`;
- `suspicious_long_value_count`;
- `suspicious_multiple_numbers_count`;
- `top_raw_values`;
- `source`.

Разрешено считать:

- `top_raw_values_count`;
- наличие/отсутствие raw profile;
- counts для diagnostics display.

Запрещено интерпретировать эти данные как:

- approval;
- rejection;
- SQL blocker с production meaning;
- normalized value;
- apply plan input.

## Разрешённый diagnostics output

В будущем можно добавить в:

```text
sql_preview.diagnostics
```

read-only summary из profiling:

```text
raw_profile_total_values
unique_raw_values_count
empty_values_count
suspicious_no_digits_count
suspicious_long_value_count
suspicious_multiple_numbers_count
top_raw_values_count
raw_profile_source
```

Все эти поля должны быть diagnostics-only.

Они не должны менять:

```text
safe_to_apply
generated
statements
operations
blocked_by
```

## Почему build_sql_preview должен оставаться blocked preview

Сейчас нет отдельной production architecture для:

- value normalization approval;
- parser approval flow;
- canonical numeric value extraction;
- unit conversion;
- unknown/invalid policy as apply blocker;
- synonym approval;
- SQL diff;
- apply safety model.

Поэтому `build_sql_preview` не может создавать executable SQL или apply plan на основании raw profiling.

`raw_profile` отвечает на вопрос:

```text
что сейчас лежит в raw DB values?
```

Он не отвечает на вопрос:

```text
что надо записать в DB?
```

## Safe Fields

Следующие поля должны оставаться безопасными:

```text
safe_to_apply = 0
generated = 0
statements = array()
apply_changes = 0
```

Также:

```text
operations.would_create = array()
operations.would_update = array()
operations.skipped = array()
operations.blocked = array()
```

`blocked_by` должен продолжать содержать явную причину, например:

```text
db_readonly_sql_preview_not_implemented
```

Допустимо добавить дополнительную diagnostic reason только как non-apply marker, если это будет отдельно решено.

## Suspicious Diagnostics Boundary

Поля вида:

```text
suspicious_no_digits_count
suspicious_long_value_count
suspicious_multiple_numbers_count
```

не означают:

- reject;
- approve;
- invalid;
- unknown;
- blocker для SQL apply;
- safe-to-apply decision.

Они означают только:

```text
нужно посмотреть на raw values внимательнее
```

## normalized_values Boundary

`normalized_values` не должны использоваться как apply-ready data.

На текущем этапе DB-readonly `analyze_values` обязан оставлять:

```text
normalized_values = array()
```

Если в будущем появятся normalized values, это должно быть отдельным spec и отдельной architecture decision.

До этого:

- не читать `normalized_values` как source для SQL;
- не создавать SQL на основании `normalized_values`;
- не считать `normalized_count > 0` как readiness.

## Запрещено

`DbReadOnlySqlPreviewBuilder` не должен:

- генерировать executable SQL;
- создавать SQL files;
- создавать apply plan;
- делать `safe_to_apply = 1`;
- заполнять `statements`;
- создавать write operations;
- использовать live DB;
- выполнять SQL apply;
- выполнять write/schema operations;
- использовать raw_profile как reject/approve;
- использовать raw_profile как normalized data;
- менять default dry-run path;
- требовать pipeline wiring changes в рамках текущего documentation step.

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

## Default Dry-run Boundary

Default dry-run path не менять:

```text
bin/dry-run.php
PipelineFactory::createDefault()
config/jobs/pump_diameter.php
DryRunSqlPreviewBuilder
```

DB-readonly SQL preview boundary относится только к:

```text
DbReadOnlySqlPreviewBuilder
DbReadOnlyPipelineFactory
bin/db-readonly-run.php
```

Но в текущем documentation step эти файлы не менять.

## Future Implementation Option

Будущий безопасный implementation step может быть таким:

```text
добавить read-only raw_profile summary в sql_preview.diagnostics
```

Минимальный safe output:

```php
'diagnostics' => array(
    'raw_value_count' => ...,
    'normalized_count' => 0,
    'unknown_count' => 0,
    'invalid_count' => 0,
    'empty_count' => ...,
    'raw_profile_total_values' => ...,
    'unique_raw_values_count' => ...,
    'empty_values_count' => ...,
    'suspicious_no_digits_count' => ...,
    'suspicious_long_value_count' => ...,
    'suspicious_multiple_numbers_count' => ...,
    'top_raw_values_count' => ...,
    'source' => 'local_dump_db_readonly',
)
```

При этом обязательно сохранить:

```text
generated = 0
safe_to_apply = 0
statements = array()
blocked_by contains db_readonly_sql_preview_not_implemented
```

## Verification Plan для будущей реализации

После будущей реализации выполнить:

### Syntax checks

```text
C:\php56\php.exe -l framework-standardization\src\SqlPreview\DbReadOnlySqlPreviewBuilder.php
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

- `sql_preview.safe_to_apply = 0`;
- `sql_preview.generated = 0`;
- `sql_preview.apply_changes = 0`;
- `sql_preview.statements = array()`;
- `sql_preview.blocked_by` содержит `db_readonly_sql_preview_not_implemented`;
- `sql_preview.diagnostics` содержит read-only raw_profile summary, если implementation добавляет его;
- suspicious diagnostics не меняют status на failed;
- `normalized_values` не используются как apply-ready data;
- executable SQL не появляется;
- runtime config и dump files не попали в git.

### Git status

```text
git status
```

## Out of Scope

Вне scope:

- реализация в текущем шаге;
- PHP-код;
- pipeline wiring;
- runner changes;
- default dry-run changes;
- runtime config changes;
- HANDOFF.md update;
- production normalization;
- SQL diff;
- executable SQL;
- SQL files;
- SQL apply;
- apply plan;
- live DB;
- write/schema operations;
- OpenCart module paths.

## Recommended Boundary

Рекомендуемая граница:

```text
DbReadOnlySqlPreviewBuilder может отображать raw_profile summary как diagnostics-only,
но должен оставаться blocked preview.
```

Нельзя переходить к:

```text
generated = 1
safe_to_apply = 1
statements != array()
```

до отдельной production SQL/apply architecture.
