# DB-readonly Paired Wiring Plan

Короткий mini-plan для будущего подключения пары:

```text
DbReadOnlyScopeResolver + DbReadOnlyAttributeExporter
```

Документ фиксирует будущий implementation step. Этот шаг не является реализацией и не меняет pipeline wiring.

## Текущее состояние

DB-readonly path существует отдельно от default dry-run path.

Сейчас:

- `DbReadOnlyCanonicalAttributeResolver` реализован и подключён в DB-readonly composition path;
- `DbReadOnlyScopeResolver` реализован и standalone-проверен, но не подключён;
- `DbReadOnlyAttributeExporter` реализован и standalone-проверен, но не подключён;
- `DbReadOnlyPipelineFactory` пока использует `DryRunScopeResolver`;
- `DbReadOnlyPipelineFactory` пока использует `DryRunAttributeExporter`;
- DB-backed stage в runner пока только `resolve_canonical`;
- default dry-run path остаётся no-DB fixture path.

Текущая DB-readonly composition модель:

```text
resolve_canonical  -> DB-backed
resolve_scope      -> dry-run
export_attributes  -> dry-run
остальные stages   -> dry-run / текущие fixture-compatible компоненты
```

## Цель paired wiring

Будущий implementation step должен подключить в DB-readonly composition path сразу пару:

```text
DbReadOnlyScopeResolver
DbReadOnlyAttributeExporter
```

После будущего wiring DB-readonly runner должен иметь DB-backed stages:

```text
resolve_canonical
resolve_scope
export_attributes
```

Default dry-run path при этом должен остаться прежним no-DB fixture path.

## Почему wiring должен быть парным

`DbReadOnlyScopeResolver` возвращает реальные DB `product_id` из local dump DB.

`DryRunAttributeExporter` является fixture-only компонентом и ожидает fixture data, включая:

```text
product_id = 0
```

Поэтому комбинация запрещена:

```text
DbReadOnlyScopeResolver + DryRunAttributeExporter
```

Допустима только парная DB-readonly комбинация:

```text
DbReadOnlyScopeResolver + DbReadOnlyAttributeExporter
```

Это решение зафиксировано в:

- `docs/DECISIONS.md`;
- `docs/DB_READONLY_SCOPE_EXPORT_MINI_SPEC.md`.

## Файлы для будущего implementation step

Ожидаемо может измениться:

```text
framework-standardization/src/Pipeline/DbReadOnlyPipelineFactory.php
```

Возможное изменение, только если нужно корректно отображать runner status:

```text
framework-standardization/bin/db-readonly-run.php
```

Если runner явно выводит список DB-backed stages, он должен отражать новый статус:

```text
db_backed_stage / db_backed_stages: resolve_canonical, resolve_scope, export_attributes
```

Формат вывода runner-а менять только минимально и только для корректного runtime report.

## Файлы, которые не должны изменяться

Будущий paired wiring не должен менять:

```text
framework-standardization/bin/dry-run.php
framework-standardization/src/Pipeline/PipelineFactory.php
framework-standardization/config/jobs/pump_diameter.php
```

Также не должны изменяться:

- dry-run components;
- default dry-run job;
- default dry-run composition;
- ignored runtime config `framework-standardization/config/runtime/local.dump.php`;
- dump files;
- OpenCart module paths;
- SQL apply logic;
- executable SQL files.

## Предлагаемая схема подключения

В `DbReadOnlyPipelineFactory` будущий implementation step должен заменить только DB-readonly composition для двух stages:

Текущее состояние:

```text
$scopeResolver = new DryRunScopeResolver();
$attributeExporter = new DryRunAttributeExporter();
```

Будущая схема:

```text
$scopeResolver = new DbReadOnlyScopeResolver($db, $tableName, $scopeRuntimeContext);
$attributeExporter = new DbReadOnlyAttributeExporter($db, $tableName, $exportRuntimeContext);
```

Контексты должны оставаться разделёнными:

- runtime/source facts: `language_id`, `source`;
- scope facts: `category_id`, `category_name`, `product_ids`;
- canonical facts: `target_attribute_id`, `target_attribute_group_id`, canonical names.

`DbReadOnlyAttributeExporter` должен получать `category_id` через `$scope`, а не через runtime context.

## Ожидаемый статус DB-backed stages

После будущего paired wiring:

```text
validate_job       -> current validation stage
resolve_canonical  -> DB-backed
resolve_scope      -> DB-backed
export_attributes  -> DB-backed
analyze_names      -> current dry-run/fixture-compatible component
analyze_values     -> current dry-run/fixture-compatible component
build_sql_preview  -> current preview-only component
build_report       -> current report builder
build_framework_result -> current result builder
```

Runner status должен явно показывать, что DB-backed stages теперь:

```text
resolve_canonical
resolve_scope
export_attributes
```

## Проверки после будущей реализации

### 1. Syntax check изменённых PHP-файлов

Команды выполнять через PHP 5.6:

```text
C:\php56\php.exe -l framework-standardization\src\Pipeline\DbReadOnlyPipelineFactory.php
```

Если менялся runner:

```text
C:\php56\php.exe -l framework-standardization\bin\db-readonly-run.php
```

### 2. Default dry-run

Команда:

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

Эта проверка подтверждает, что default no-DB fixture path не сломан.

### 3. DB-readonly runner

Команда:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Ожидаемо:

```text
result_status: ok
warnings_count: 0
errors_count: 0
resolve_canonical DB-backed
resolve_scope DB-backed
export_attributes DB-backed
```

Дополнительно проверить:

- `resolve_scope` возвращает реальные `product_id`;
- `export_attributes` работает с этими реальными `product_id`;
- в DB-readonly export facts нет `product_id = 0`;
- в DB-readonly export facts нет `attribute_id = 0`;
- `target_attribute_id` соответствует resolved canonical data;
- runtime config остаётся local dump config.

### 4. Git status

Команда:

```text
git status
```

Проверить:

- ignored runtime config не попал в git;
- dump files не попали в git;
- временные manual-check файлы не остались в working tree.

## Hybrid path safety

После будущего wiring должны одновременно выполняться два условия:

```text
default dry-run path = no-DB fixture path
DB-readonly path = local dump DB read-only path
```

Нельзя делать DB-backed components глобальными default-компонентами.

Нельзя менять `PipelineFactory::createDefault()`.

Нельзя менять `bin/dry-run.php`.

DB-backed scope/export подключаются только в `DbReadOnlyPipelineFactory`.

## Rollback / failure boundary

Если будущий paired wiring ломает DB-readonly runner:

- откатить только paired wiring в `DbReadOnlyPipelineFactory`;
- вернуть `DryRunScopeResolver`;
- вернуть `DryRunAttributeExporter`;
- оставить standalone classes без удаления, если они не ломают default path;
- не трогать default dry-run path.

Если менялся только runner report и он оказался неверным:

- откатить только изменение runner output;
- не менять DB-readonly components без отдельной причины.

Failure boundary:

```text
paired wiring должен быть атомарным для resolve_scope + export_attributes
```

Не оставлять промежуточное состояние:

```text
DbReadOnlyScopeResolver + DryRunAttributeExporter
```

## Out of Scope

Этот plan не включает:

- реализацию paired wiring;
- изменение PHP-кода;
- изменение `DbReadOnlyPipelineFactory`;
- изменение runners;
- изменение default dry-run path;
- подключение live DB;
- SQL apply;
- executable SQL;
- write/schema operations;
- расширение на другие категории;
- расширение beyond `pump_diameter`;
- production normalization;
- SQL preview generation;
- OpenCart module paths.

Запрещённые operation families остаются:

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
