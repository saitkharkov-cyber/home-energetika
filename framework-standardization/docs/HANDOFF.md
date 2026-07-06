# Handoff - Framework Standardization

06.07.2026

Проект: Home Energetika / Framework Standardization  
Репозиторий: `D:\Git\home-energetika`  
Рабочая папка: `framework-standardization`

Framework Standardization - отдельный PHP 5.6-compatible CLI/tooling layer для инженерной стандартизации характеристик.

Проект работает только с default no-DB dry-run path и отдельным DB-readonly path на локальном dump/local DB. Live DB, SQL apply и OpenCart module runtime не подключены.

## Текущий статус

Текущая стабильная точка:

```text
default dry-run path остаётся no-DB fixture path
DB-readonly path работает только с local dump DB через read-only connection
SQL apply не подключён
```

Последний закрытый инженерный шаг:

`DB readonly scope/export path paired wiring + compatibility adapters`

Последний инженерный коммит перед handoff update:

`9948721 Document DB readonly compatibility adapters decision`

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
- `docs/RUNTIME_CHECKS.md`
- `docs/DECISIONS.md`
- `docs/DB_READONLY_SCOPE_EXPORT_MINI_SPEC.md`
- `docs/DB_READONLY_PAIRED_WIRING_PLAN.md`

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
analyze_values          -> DB-readonly-compatible adapter
build_sql_preview       -> DB-readonly-compatible blocked preview
build_report            -> dry-run
build_framework_result  -> dry-run
```

DB-backed stages используют local dump DB только через read-only connection.

`DbReadOnlyScopeResolver` и `DbReadOnlyAttributeExporter` подключены только парой. Запрещённое состояние `DbReadOnlyScopeResolver + DryRunAttributeExporter` не должно возвращаться.

`DbReadOnlySqlPreviewBuilder` остаётся blocked preview: без executable SQL statements и без safe-to-apply режима.

DB-readonly-compatible adapters не являются production implementation.

## Текущие архитектурные границы

- Один запуск = один `Attribute Job`.
- Один `Attribute Job` = одна характеристика / один canonical attribute / один scope.
- Поток: `Attribute Job -> AttributeContext -> Pipeline -> FrameworkResult`.
- DB-readonly path ограничен `pump_diameter`, `category_id = 11900213`, `language_id = 1`.
- Default dry-run path остаётся no-DB fixture path.
- DB-readonly path работает только с local dump DB.
- SQL apply не выполняется.
- Executable SQL не добавлять.
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

Следующий шаг должен быть анализом / mini-spec, а не production implementation:

```text
mini-spec для следующего перехода после scope/export:
что делать с analyze_names / analyze_values / build_sql_preview
```

Цель mini-spec:

- определить, остаются ли текущие DB-readonly-compatible adapters временными;
- определить, какой stage следующим можно переводить из compatibility adapter в полноценную DB-readonly инженерную реализацию;
- определить data contract для следующего stage;
- сохранить default dry-run path без изменений;
- не переходить к production normalization;
- не делать SQL apply;
- не генерировать executable SQL.

До отдельного решения не начинать кодовую реализацию следующего stage.

## Старт в новом чате

Сначала открыть и прочитать:

- `framework-standardization/docs/HANDOFF.md`
- `framework-standardization/docs/DECISIONS.md`
- `framework-standardization/docs/RUNTIME_CHECKS.md`

Затем проверить:

```text
git status
git log --oneline -5
```

Ожидаемая точка:

`HEAD/main/origin/main = последний коммит с обновлённым HANDOFF после 9948721`

`working tree clean`

## Правило работы

Двигаться маленькими шагами:

```text
mini-spec -> implementation -> verification -> review -> commit -> push
```

PHP 5.6 checks выполнять через:

```text
C:\php56\php.exe
```
