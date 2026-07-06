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
abab5ff Document DB readonly normalization approval flow checks
```

Последний закрытый инженерный блок:

```text
DB-readonly diagnostics propagation + standalone normalization proposal parser + standalone approval flow skeleton
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
- `docs/DB_READONLY_FRAMEWORK_RESULT_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`

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
build_report            -> dry-run/reporting-only with raw_profile_summary and sql_preview_safety_summary
build_framework_result  -> dry-run/result-packaging with diagnostics_summary and safety_summary
```

DB-backed stages используют local dump DB только через read-only connection.

`DbReadOnlyScopeResolver` и `DbReadOnlyAttributeExporter` подключены только парой. Запрещённое состояние `DbReadOnlyScopeResolver + DryRunAttributeExporter` не должно возвращаться.

DB-readonly-compatible stages не являются production implementation.

## Standalone components

`src/Normalizer/DbReadOnlyNormalizationProposalParser.php` существует как standalone parser skeleton.

Parser:

- не подключён к pipeline;
- не подключён к `analyze_values`;
- не подключён к `sql_preview`, `build_report` или `build_framework_result`;
- может создавать только статусы `proposed`, `needs_review`, `unknown`;
- не должен создавать статусы `approved` или `rejected`;
- не создаёт SQL/apply output.

`src/Approval/DbReadOnlyNormalizationApprovalFlow.php` существует как standalone approval flow skeleton.

Approval flow:

- не подключён к pipeline;
- не подключён к parser автоматически;
- может явно переводить proposals в `approved`, `rejected`, `needs_review`, `unknown`, `proposed`;
- является единственным текущим standalone component, который может создавать `approved` / `rejected`;
- не создаёт SQL/apply output.

`approved` означает только future SQL preview candidate eligibility.

`approved` не означает:

- SQL apply;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

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
- Parser proposals не являются apply-ready data.
- Parser не может создавать `approved` / `rejected`.
- Только approval flow может создавать `approved` / `rejected`.
- `approved` не означает SQL apply.
- `build_sql_preview` остаётся blocked preview.
- `generated = 0`.
- `safe_to_apply = 0`.
- `statements = array()`.
- `sql_apply_allowed = 0`.
- `production_ready = 0`.
- `blocked_by` содержит `db_readonly_sql_preview_not_implemented`.
- SQL generation запрещён.
- SQL files не создавать.
- SQL diff не создавать.
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
создать mini-spec для local approval fixture / manual review bridge между standalone parser и standalone approval flow
```

Границы следующего шага:

- не делать pipeline wiring;
- не подключать parser к `analyze_values`;
- не подключать approval flow к SQL preview;
- не делать SQL generation;
- не делать SQL apply;
- не создавать SQL files;
- не создавать SQL diff;
- не создавать apply plan;
- не использовать live DB;
- не менять default dry-run path;
- storage рассматривать только как future/local fixture discussion, без DB/schema changes.

## Старт в новом чате

Сначала открыть и прочитать:

- `framework-standardization/docs/HANDOFF.md`
- `framework-standardization/docs/DECISIONS.md`
- `framework-standardization/docs/RUNTIME_CHECKS.md`
- `framework-standardization/docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`
- `framework-standardization/docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`
- `framework-standardization/docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`

Затем проверить:

```text
git status
git log --oneline -5
```

Ожидаемая точка:

```text
HEAD/main/origin/main = abab5ff Document DB readonly normalization approval flow checks
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
