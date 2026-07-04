# Handoff — Framework Standardization

**Дата:** 03.07.2026 23:02
Проект: Home Energetika / Framework Standardization  
Репозиторий: `D:\Git\home-energetika`  
Рабочая папка: `framework-standardization`

---

## Текущий статус

Архитектурная документация Framework Standardization приведена в согласованное состояние.

Implementation skeleton создан и развивается маленькими no-DB шагами.

Последний закрытый шаг:

```text
ResolveScopeStage no-DB boundary
```

Ожидаемое состояние:

```text
working tree clean
origin/main = main
```

Последние важные коммиты:

```text
13ba45c Add no-DB scope resolver boundary
8bd6c45 Add no-DB canonical resolver boundary
7a8e9c4 Set CLI timezone for PHP 5.6 dry-run
61b65e1 Document ValidateJobStage status
f817a53 Implement minimal ValidateJobStage checks
````

---

## Что уже сделано

Зафиксированы основные архитектурные документы:

```text
TECHNICAL_SPECIFICATION.md
README.md
PROJECT_MASTER_SUMMARY.md
docs/STAGES_PIPELINE.md
docs/ATTRIBUTE_JOB.md
docs/ATTRIBUTE_CONTEXT.md
docs/FRAMEWORK_RESULT.md
docs/ATTRIBUTE_EXPORTER.md
docs/VALUE_PARSER.md
docs/ANALYZE_NAMES_STAGE.md
docs/ANALYZE_VALUES_STAGE.md
docs/BUILD_SQL_PREVIEW_STAGE.md
docs/BUILD_REPORT_STAGE.md
docs/BUILD_FRAMEWORK_RESULT_STAGE.md
docs/CANONICAL_ATTRIBUTE_REGISTRATION.md
docs/IMPLEMENTATION_STRUCTURE.md
sql/CREATE_TABLE_canonical_attributes.sql
```

Документация проверена Codex read-only ревизией.

Результат проверки:

```text
OK
git status clean
устаревшие stage-термины очищены
9-stage модель согласована
stage_results согласованы
PROJECT_MASTER_SUMMARY.md актуален
```

---

## Текущая stage-модель

Pipeline состоит из 9 stages:

```text
ValidateJobStage
ResolveCanonicalStage
ResolveScopeStage
ExportAttributesStage
AnalyzeNamesStage
AnalyzeValuesStage
BuildSqlPreviewStage
BuildReportStage
BuildFrameworkResultStage
```

Технические имена `stage_results`:

```text
validate_job
resolve_canonical
resolve_scope
export_attributes
analyze_names
analyze_values
build_sql_preview
build_report
build_framework_result
```

---

## Главные архитектурные решения

Framework работает по принципу:

```text
один запуск = один Attribute Job = одна характеристика = один canonical attribute = один scope
```

Ключевая цепочка:

```text
Attribute Job
→ AttributeContext
→ Stages Pipeline
→ FrameworkResult
```

Framework не применяет изменения автоматически.

SQL preview формируется только для ручной проверки.

---

## Canonical DB

Текущая DB-модель содержит только одну canonical-таблицу:

```text
{DB_PREFIX}canonical_attributes
```

SQL-драфт:

```text
sql/CREATE_TABLE_canonical_attributes.sql
```

Решения:

```text
scope/category не хранится в БД
value_parser/value_type/allow_empty не хранятся в БД
synonyms не хранятся в БД на текущем этапе
результаты анализа не хранятся в БД
```

Таблица использует:

```text
MySQL 5.7
utf8
utf8_general_ci
{DB_PREFIX} placeholder
```

---

## Границы компонентов

`AttributeExporter`:

```text
read-only
читает факты из OpenCart
не нормализует значения
не утверждает синонимы
не выбирает канон
не пишет в БД
```

`ValueParser`:

```text
работает с одним raw value
не знает про БД
не знает про товары/категории
не знает про canonical attribute
возвращает ParseResult
```

`AnalyzeNamesStage`:

```text
анализирует имена атрибутов
предлагает кандидатов
не утверждает синонимы
```

`AnalyzeValuesStage`:

```text
анализирует значения через ValueParser
не анализирует имена
```

`BuildSqlPreviewStage`:

```text
строит SQL preview
фиксирует blockers
не выполняет SQL
```

`BuildReportStage`:

```text
строит человекочитаемый отчёт
не меняет смысловые данные
```

`BuildFrameworkResultStage`:

```text
собирает FrameworkResult из AttributeContext
result_status выводится детерминированно из накопленного состояния
```

---

## План будущей реализации

Структура будущей реализации зафиксирована в:

```text
docs/IMPLEMENTATION_STRUCTURE.md
```

Рекомендуемая будущая структура:

```text
framework-standardization/
├─ config/
│  ├─ jobs/
│  └─ runtime/
├─ src/
│  ├─ DTO/
│  ├─ Contract/
│  ├─ Pipeline/
│  ├─ Stage/
│  ├─ Canonical/
│  ├─ Scope/
│  ├─ Exporter/
│  ├─ Parser/
│  ├─ SqlPreview/
│  ├─ Report/
│  ├─ Result/
│  ├─ OpenCart/
│  └─ Runner/
├─ var/
│  ├─ reports/
│  ├─ sql-preview/
│  └─ logs/
└─ tests/
```

Часть каталогов уже создана в рамках PHP 5.6-compatible skeleton.

---

## Runtime constraint

Implementation skeleton остаётся на PHP.

Целевой runtime первого MVP:

```text
PHP 5.6-compatible CLI/tooling layer
```

Framework Standardization — отдельный инженерный tooling layer внутри `framework-standardization`.

Это не OpenCart-модуль и не модуль админки OpenCart.

Framework:

```text
запускается вручную инженером
работает по одному Attribute Job
готовит stage_results / report / sql_preview
не применяет SQL автоматически
```

Не создавать OpenCart module paths:

```text
admin/controller
admin/model
admin/view
catalog/controller
catalog/model
language
```

Не подключаться к `admin/index.php` или OpenCart MVC как runtime.

OpenCart на текущем этапе является источником данных.

Существующий PHP-импорт товаров является будущим потребителем canonical layer, но не частью первого skeleton.

---

## CLI dry-run entrypoint

PHP 5.6-compatible CLI dry-run entrypoint уже создан.

Файлы:

```text
bootstrap.php
bin/dry-run.php
config/jobs/pump_diameter.php
```

Проверки выполнять через локальный PHP 5.6:

```text
C:\php56\php.exe
```

Не полагаться на глобальный `php` из `PATH`.

Happy path запуск из корня репозитория:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Dry-run:

```text
выводит result_status
выводит 9 stage_results
не подключается к OpenCart
не подключается к DB
не применяет SQL
```

Ожидаемый happy path:

```text
result_status: ok
warnings_count: 0
errors_count: 0
все 9 stages ok
```

Это всё ещё отдельный инженерный PHP CLI/tooling layer.

Это не OpenCart-модуль и не модуль админки OpenCart.

---

## ValidateJobStage status

`ValidateJobStage` уже реализует минимальную structural/safety validation.

Проверяет:

```text
job_id
job_id format
job_name
canonical.canonical_code
scope.type = category
scope.category_id
source.type = opencart_db
source.language_id
value_rules.value_parser
value_rules.unknown_value_policy = block_sql / report_only
output.apply_changes = 0
```

Поведение:

```text
при ошибках пишет AttributeContext.errors
при ошибках пишет StageResult::failed
при успехе пишет StageResult::ok
warnings пока не добавляет
```

Не делает:

```text
OpenCart/DB connection
canonical lookup
category/language existence checks
parser registry checks
SQL apply
```

Dry-run happy path проходит.

---

## ResolveCanonicalStage status

`ResolveCanonicalStage` уже не stub.

Stage использует:

```text
CanonicalAttributeResolverInterface
```

Текущая реализация resolver:

```text
DryRunCanonicalAttributeResolver
```

Поведение:

```text
fixture только для canonical_code = pump_diameter
source = dry_run_fixture
unknown canonical даёт canonical_code_not_found
canonical записывается в AttributeContext
```

Важно:

```text
target_attribute_id = 0 только dry-run fixture
это не реальный OpenCart ID
```

`ResolveCanonicalStage` не делает:

```text
DB connection
OpenCart connection
SQL apply
реальную проверку target_attribute_id / target_attribute_group_id
```

---

## ResolveScopeStage status

`ResolveScopeStage` уже не stub.

Stage использует:

```text
ScopeResolverInterface
```

Текущая реализация resolver:

```text
DryRunScopeResolver
```

Поведение:

```text
fixture только для scope.type = category
fixture только для category_id = 11900213
source = dry_run_fixture
unknown category даёт scope_category_not_found
scope записывается в AttributeContext
raw_data.products записывается в AttributeContext
```

Важно:

```text
product_id = 0 только dry-run fixture
это не реальный OpenCart ID
```

`ResolveScopeStage` не делает:

```text
DB connection
OpenCart connection
SQL apply
реальное чтение categories/products
проверку реального имени категории
```

---

## Negative manual checks

Для negative checks создавать временные job-файлы:

```text
config/jobs/_manual_*.php
```

Не коммитить временные файлы и удалять их после проверки.

Unknown canonical:

```text
canonical_code = unknown_canonical
ожидаемая ошибка: canonical_code_not_found
downstream stages: skipped
build_report: ok
build_framework_result: ok
```

Unknown category:

```text
category_id = 99999999
ожидаемая ошибка: scope_category_not_found
downstream stages: skipped
build_report: ok
build_framework_result: ok
```

---

## Что всё ещё НЕ сделано

```text
ExportAttributesStage всё ещё stub / no real export
AnalyzeNamesStage stub
AnalyzeValuesStage stub
BuildSqlPreviewStage stub
BuildReportStage пока минимальный
BuildFrameworkResultStage пока минимальный
нет DB runtime config
нет OpenCart connection
нет SQL apply
нет реальных attribute/product reads
```

---

## Следующий шаг

Рекомендуемый следующий инженерный шаг:

```text
read-only mini-spec для ExportAttributesStage no-DB boundary
```

Цель:

```text
начать использовать canonical + scope из AttributeContext
оставаться без DB/OpenCart
не реализовывать real export на этом шаге
```

Не реализовывать этот шаг без отдельной команды.

---

## Что НЕ делать на следующем шаге

```text
DB connection
OpenCart connection
SQL apply
production DB
массовый запуск
UI
новые таблицы
реальное чтение атрибутов/товаров
интеграцию с импортами
универсальный plugin-system
```

---

## Рекомендуемая команда Codex на следующий шаг

```text
Работаем в репозитории D:\Git\home-energetika.

Работай только внутри папки:
framework-standardization

Задача: read-only mini-spec для ExportAttributesStage no-DB boundary.

Ничего не изменяй и не коммить.

Перед началом прочитай:

- docs/STAGES_PIPELINE.md
- docs/ATTRIBUTE_CONTEXT.md
- docs/ATTRIBUTE_EXPORTER.md
- docs/HANDOFF.md
- src/Stage/ExportAttributesStage
- src/DTO/AttributeContext.php
- src/Pipeline/PipelineFactory.php

Нужно предложить минимальный no-DB шаг, который начинает использовать canonical + scope из AttributeContext, но не подключается к DB/OpenCart и не делает real export.
```

---

## Важное правило работы

Двигаться маленькими шагами.

Сначала read-only mini-spec, потом реализация по отдельной команде, потом проверка, потом коммит.

Не переходить к реальной DB/OpenCart логике, пока no-DB boundaries не будут понятны и согласованы.
