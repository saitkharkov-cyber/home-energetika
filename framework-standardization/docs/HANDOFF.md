# Handoff - Framework Standardization

06.07.2026

Проект: Home Energetika / Framework Standardization  
Репозиторий: `D:\Git\home-energetika`  
Рабочая папка: `framework-standardization`

Framework Standardization - отдельный PHP 5.6-compatible CLI/tooling layer для инженерной стандартизации характеристик.

Проект работает с default no-DB dry-run path и отдельным DB-readonly path на локальном dump/local DB. Live DB, SQL apply и OpenCart module runtime не подключены.

## Текущий статус

Текущая стабильная точка:

```text
196fc0b Add DB readonly report output spec
```

Последний закрытый инженерный блок:

```text
DB-readonly value profiling + SQL preview raw_profile diagnostics + report output spec
```

Ожидаемое состояние репозитория:

```text
working tree clean
origin/main = main
```

## Ключевая документация

- `docs/STAGES_PIPELINE.md`
- `docs/ATTRIBUTE_CONTEXT.md`
- `docs/IMPLEMENTATION_STRUCTURE.md`
- `docs/STAGE_BOUNDARIES.md`
- `docs/DUMP_LOCAL_DB_CHECKLIST.md`
- `docs/DECISIONS.md`
- `docs/RUNTIME_CHECKS.md`
- `docs/DB_READONLY_SCOPE_EXPORT_MINI_SPEC.md`
- `docs/DB_READONLY_PAIRED_WIRING_PLAN.md`
- `docs/DB_READONLY_ANALYZE_PREVIEW_NEXT_STEP_SPEC.md`
- `docs/DB_READONLY_VALUE_PROFILING_SPEC.md`
- `docs/DB_READONLY_SQL_PREVIEW_BOUNDARY_SPEC.md`
- `docs/DB_READONLY_REPORT_OUTPUT_SPEC.md`

Оперативный статус находится в этом документе.

Архитектурные решения фиксируются в `docs/DECISIONS.md`.

Ручные проверки и runtime facts фиксируются в `docs/RUNTIME_CHECKS.md`.

## Stage-модель

Порядок Pipeline и technical names:

1. `validate_job`
2. `resolve_canonical`
3. `resolve_scope`
4. `export_attributes`
5. `analyze_names`
6. `analyze_values`
7. `build_sql_preview`
8. `build_report`
9. `build_framework_result`

Порядок stages не менять без отдельного архитектурного решения.

## Default dry-run path

Default dry-run path остаётся fixture/no-DB.

Entrypoint:

```text
framework-standardization/bin/dry-run.php
```

Job:

```text
framework-standardization/config/jobs/pump_diameter.php
```

Composition:

```text
PipelineFactory::createDefault()
```

Happy path command:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Ожидаемый результат:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

Default dry-run path не должен подключать DB-backed components.

## DB-readonly path

DB-readonly path существует отдельно от default dry-run.

Entrypoint:

```text
framework-standardization/bin/db-readonly-run.php
```

Job:

```text
framework-standardization/config/jobs/pump_diameter.db_readonly.php
```

Local ignored runtime config:

```text
framework-standardization/config/runtime/local.dump.php
```

Composition:

```text
DbReadOnlyPipelineFactory
```

Текущий DB-readonly stage status:

```text
resolve_canonical       -> DB-backed
resolve_scope           -> DB-backed
export_attributes       -> DB-backed
analyze_names           -> DB-readonly-compatible adapter
analyze_values          -> DB-readonly-compatible profiling adapter
build_sql_preview       -> DB-readonly-compatible blocked preview with raw_profile diagnostics summary
build_report            -> dry-run/reporting-only, spec exists for future DB-readonly diagnostics output
build_framework_result  -> dry-run
```

DB-backed stages используют local dump DB только через read-only connection.

`DbReadOnlyScopeResolver` и `DbReadOnlyAttributeExporter` подключены только парой. Запрещённое состояние `DbReadOnlyScopeResolver + DryRunAttributeExporter` не должно возвращаться.

`DbReadOnlyAttributeValueAnalyzer` выполняет только read-only profiling raw values. `attribute_value_structure.diagnostics.raw_profile` является diagnostics-only output и не является normalization.

`DbReadOnlySqlPreviewBuilder` остаётся blocked preview. Он может отображать summary из `raw_profile` только как diagnostics, без SQL generation и без safe-to-apply режима.

DB-readonly-compatible adapters не являются production implementation.

## Текущие архитектурные границы

- Один запуск = один `Attribute Job`.
- Один `Attribute Job` = одна характеристика / один canonical attribute / один scope.
- Поток: `Attribute Job -> AttributeContext -> Pipeline -> FrameworkResult`.
- DB-readonly path ограничен `pump_diameter`, `category_id = 11900213`, `language_id = 1`.
- Default dry-run path остаётся no-DB fixture path.
- DB-readonly path работает только с local dump DB через read-only connection.
- `raw_profile` является diagnostics-only.
- Profiling не является normalization.
- `suspicious_*` diagnostics не являются reject / approve decisions.
- `normalized_values` не являются apply-ready data.
- `build_sql_preview` остаётся blocked preview.
- `generated = 0`.
- `safe_to_apply = 0`.
- `statements = array()`.
- `blocked_by` содержит `db_readonly_sql_preview_not_implemented`.
- SQL generation запрещён.
- SQL files не создавать.
- Apply plan не создавать.
- SQL apply не выполнять.
- Live DB запрещена.
- Production normalization пока не делать.
- OpenCart module paths не создавать.

Запрещённые write/schema operations:

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

## Runtime-ограничения

- Runtime первого MVP: `PHP 5.6-compatible CLI/tooling layer`.
- Проверки выполнять через `C:\php56\php.exe`.
- Не полагаться на глобальный `php` из `PATH`.
- Framework Standardization - не OpenCart-модуль и не модуль админки OpenCart.
- Не создавать OpenCart module paths:

```text
admin/controller
admin/model
admin/view
catalog/controller
catalog/model
language
```

## Следующий инженерный шаг

Следующий шаг для новой Codex-сессии:

```text
реализация DB-readonly diagnostics output в build_report по docs/DB_READONLY_REPORT_OUTPUT_SPEC.md
```

Границы следующего шага:

- менять только report-related component;
- не менять SQL preview;
- не менять analyze_values;
- не менять wiring;
- не менять runners;
- не менять default dry-run path;
- не делать normalization;
- не делать SQL generation;
- не делать SQL apply;
- не создавать apply plan;
- сохранить reporting-only behavior.

## Старт в новом чате

Сначала открыть и прочитать:

- `framework-standardization/docs/HANDOFF.md`
- `framework-standardization/docs/DECISIONS.md`
- `framework-standardization/docs/RUNTIME_CHECKS.md`
- `framework-standardization/docs/DB_READONLY_REPORT_OUTPUT_SPEC.md`

Затем проверить:

```text
git status
git log --oneline -5
```

Ожидаемая точка:

```text
HEAD/main/origin/main = 196fc0b Add DB readonly report output spec
working tree clean
```

## Правило работы

Двигаться маленькими шагами:

```text
mini-spec -> implementation -> verification -> review -> commit -> push
```

PHP 5.6 checks выполнять через:

```text
C:\php56\php.exe
```
