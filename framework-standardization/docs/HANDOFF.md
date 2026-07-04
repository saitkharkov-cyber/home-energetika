# Handoff — Framework Standardization

**Дата:** 03.07.2026 23:02
Проект: Home Energetika / Framework Standardization  
Репозиторий: `D:\Git\home-energetika`  
Рабочая папка: `framework-standardization`

---

## Текущий статус

Архитектурная документация Framework Standardization приведена в согласованное состояние.

Git clean.

Последние важные коммиты:

```text
Align framework documentation with stages pipeline
Document framework implementation structure
Document build framework result stage contract
Document build report stage contract
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

На момент handoff эти каталоги ещё не созданы.

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

## Следующий шаг

Завтра начать с создания минимального implementation skeleton.

Цель первого шага:

```text
создать каркас выполнения pipeline и запись stage_results
без бизнес-логики стандартизации
```

Создавать только минимальные файлы:

```text
src/Contract/StageInterface
src/Pipeline/PipelineEngine
src/Pipeline/PipelineFactory
src/DTO/AttributeJob
src/DTO/AttributeContext
src/DTO/FrameworkResult
src/DTO/StageResult
src/Stage/ValidateJobStage
src/Stage/ResolveCanonicalStage
src/Stage/ResolveScopeStage
src/Stage/ExportAttributesStage
src/Stage/AnalyzeNamesStage
src/Stage/AnalyzeValuesStage
src/Stage/BuildSqlPreviewStage
src/Stage/BuildReportStage
src/Stage/BuildFrameworkResultStage
src/Runner/FrameworkRunner
config/jobs/pump_diameter.example
```

---

## Что НЕ делать завтра на первом шаге

Не делать:

```text
реальное подключение к OpenCart
SQL apply
production DB
массовый запуск
UI
новые таблицы
сложный fuzzy matching
реальные переносы значений
интеграцию с импортами
универсальный plugin-system
```

Первый skeleton должен быть максимально скучным:

```text
DTO
StageInterface
PipelineEngine
9 пустых stages
stage_results
FrameworkRunner
example job
```

---

## Рекомендуемая команда Codex на завтра

```text
Работаем в репозитории D:\Git\home-energetika.

Работай только внутри папки:
framework-standardization

Задача: создать минимальный implementation skeleton Framework Standardization без бизнес-логики.

Перед началом прочитай:

- PROJECT_MASTER_SUMMARY.md
- docs/IMPLEMENTATION_STRUCTURE.md
- docs/STAGES_PIPELINE.md
- docs/ATTRIBUTE_JOB.md
- docs/ATTRIBUTE_CONTEXT.md
- docs/FRAMEWORK_RESULT.md

Создай только минимальный каркас:

- src/Contract/StageInterface
- src/Pipeline/PipelineEngine
- src/Pipeline/PipelineFactory
- src/DTO/AttributeJob
- src/DTO/AttributeContext
- src/DTO/FrameworkResult
- src/DTO/StageResult
- src/Stage/ValidateJobStage
- src/Stage/ResolveCanonicalStage
- src/Stage/ResolveScopeStage
- src/Stage/ExportAttributesStage
- src/Stage/AnalyzeNamesStage
- src/Stage/AnalyzeValuesStage
- src/Stage/BuildSqlPreviewStage
- src/Stage/BuildReportStage
- src/Stage/BuildFrameworkResultStage
- src/Runner/FrameworkRunner
- config/jobs/pump_diameter.example

Требования:

- Без реального подключения к OpenCart.
- Без SQL apply.
- Без production DB.
- Без бизнес-логики стандартизации.
- Stages могут быть пустыми/stub, но должны иметь правильные technical names.
- PipelineEngine должен запускать stages в утверждённом порядке.
- Каждая stage должна писать свой stage_result.
- FrameworkRunner должен показать минимальный dry-run на example job.
- Не создавать лишние сервисы, managers, analyzers.
- Не коммитить.

После выполнения показать:

- список созданных файлов
- кратко что делает skeleton
- git diff --stat
- git status
```

---

## Важное правило работы

Двигаться маленькими шагами.

Сначала каркас, потом проверка, потом коммит.

Не переходить к реальной логике, пока skeleton не будет понятен и согласован.
