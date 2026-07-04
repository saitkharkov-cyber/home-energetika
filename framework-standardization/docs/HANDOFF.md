# Handoff - Framework Standardization

Проект: Home Energetika / Framework Standardization  
Репозиторий: `D:\Git\home-energetika`  
Рабочая папка: `framework-standardization`

## Текущий статус

Framework Standardization - отдельный PHP 5.6-compatible CLI/tooling layer для инженерной работы со стандартизацией характеристик.

Текущая стабильная точка: все 9 stages имеют no-DB boundary, dry-run зелёный на PHP 5.6, DB/OpenCart/SQL apply не подключались.

Последний закрытый шаг: `BuildFrameworkResultStage no-DB boundary`.

Последний коммит: `c3445c3 Add no-DB framework result boundary`.

Ожидаемое состояние репозитория: `working tree clean`, `origin/main = main`.

## Документация

Ключевые документы:

- `docs/STAGES_PIPELINE.md`
- `docs/ATTRIBUTE_CONTEXT.md`
- `docs/IMPLEMENTATION_STRUCTURE.md`
- `docs/STAGE_BOUNDARIES.md`

Подробные stage boundaries вынесены в `docs/STAGE_BOUNDARIES.md`.

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

Все 9 stages сейчас имеют no-DB boundary.

## Главные архитектурные решения

- Один запуск = один `Attribute Job`.
- Один `Attribute Job` = одна характеристика / один canonical attribute / один scope.
- Поток: `Attribute Job -> AttributeContext -> Pipeline -> FrameworkResult`.
- SQL не применяется автоматически.
- OpenCart сейчас не подключён.

## Runtime-ограничения

- Runtime первого MVP: `PHP 5.6-compatible CLI/tooling layer`.
- Проверки выполнять через `C:\php56\php.exe`.
- Не полагаться на глобальный `php` из `PATH`.
- Framework Standardization - не OpenCart-модуль и не модуль админки OpenCart.
- Не создавать OpenCart module paths: `admin/controller`, `admin/model`, `admin/view`, `catalog/controller`, `catalog/model`, `language`.

## CLI entrypoint для dry-run

Файлы: `bootstrap.php`, `bin/dry-run.php`, `config/jobs/pump_diameter.php`.

Happy path command из корня репозитория:

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

## Что не делать

- No DB connection.
- No OpenCart connection.
- No SQL apply.
- No executable SQL.
- No production exporter/analyzer/parser/generator.
- No OpenCart module paths.
- No Composer/YAML/test framework без отдельного решения.

## Следующий шаг

Рекомендуемый следующий инженерный шаг: read-only mini-spec для DB/runtime boundary.

Цель: спроектировать read-only подключение к реальной OpenCart базе, оставить SQL apply вне scope и не реализовывать без отдельной команды.

## Правило работы

Двигаться маленькими шагами: read-only mini-spec -> implementation -> verification -> review -> commit -> push.

PHP 5.6 checks выполнять через `C:\php56\php.exe`.
