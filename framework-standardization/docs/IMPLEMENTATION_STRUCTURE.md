# Implementation Structure

Документ фиксирует будущую структуру реализации Framework Standardization.

Это не реализация, а архитектурный план.

Код реализации, каталоги `src/`, `config/`, `var/` и `tests/` создаются только после отдельного решения.

---

## Рекомендуемая структура каталогов

```text
framework-standardization/
├─ docs/
├─ sql/
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

---

## Назначение каталогов

`docs/` содержит архитектурные контракты и проектные решения.

`sql/` содержит SQL-драфты, которые не применяются автоматически.

`config/jobs/` будет содержать конфигурации запуска одной характеристики.

`config/runtime/` будет содержать настройки окружения запуска: источник данных, `db_prefix`, `language_id`, лимиты и режимы вывода.

`src/DTO/` будет содержать архитектурные структуры данных.

`src/Contract/` будет содержать интерфейсы и общие контракты компонентов.

`src/Pipeline/` будет содержать Pipeline Engine и сборку pipeline.

`src/Stage/` будет содержать stages текущей модели pipeline.

`src/Canonical/` будет содержать работу с canonical registry и разрешение канона.

`src/Scope/` будет содержать разрешение scope и category в фактическую область товаров.

`src/Exporter/` будет содержать read-only экспорт фактов из OpenCart.

`src/Parser/` будет содержать `ValueParser` и registry parser-ов.

`src/SqlPreview/` будет содержать построение SQL preview и safety blockers.

`src/Report/` будет содержать построение человекочитаемого отчёта.

`src/Result/` будет содержать сборку `FrameworkResult` из `AttributeContext`.

`src/OpenCart/` будет содержать низкоуровневый доступ к OpenCart DB и helpers для имён таблиц.

`src/Runner/` будет содержать entrypoint запуска одного `Attribute Job`.

`var/reports/` будет содержать сгенерированные отчёты.

`var/sql-preview/` будет содержать сгенерированные SQL preview.

`var/logs/` будет содержать логи запусков.

`tests/` будет содержать тесты будущей реализации.

---

## Ключевые компоненты

DTO и структуры:

* `AttributeJob`
* `AttributeContext`
* `FrameworkResult`
* `StageResult`
* `ParseResult`
* `SqlPreview`
* `Report`

Контракты и pipeline:

* `StageInterface`
* `PipelineEngine`
* `PipelineFactory`

Stages:

* `ValidateJobStage`
* `ResolveCanonicalStage`
* `ResolveScopeStage`
* `ExportAttributesStage`
* `AnalyzeNamesStage`
* `AnalyzeValuesStage`
* `BuildSqlPreviewStage`
* `BuildReportStage`
* `BuildFrameworkResultStage`

Доменные компоненты:

* `CanonicalAttributeResolver`
* `ScopeResolver`
* `AttributeExporter`
* `ValueParserRegistry`
* `SqlPreviewBuilder`
* `ReportBuilder`
* `FrameworkResultBuilder`

Runner:

* `JobLoader`
* `FrameworkRunner`

---

## Pipeline Engine

Pipeline Engine должен жить в:

```text
src/Pipeline/
```

Он не должен знать бизнес-детали атрибутов.

Его ответственность:

* выполнять stages в утверждённом порядке;
* поддерживать safe mode;
* записывать `stage_results`;
* обновлять runtime-состояние;
* останавливать pipeline на критических ошибках;
* позволять `BuildReportStage` и `BuildFrameworkResultStage` сформировать безопасный результат после ошибки, если это возможно.

---

## Stages

Stages должны жить в:

```text
src/Stage/
```

Правило:

```text
одна stage
→ один класс
→ одно technical name
→ один раздел stage_results
```

Stage является orchestration layer.

Она читает и пишет `AttributeContext`, но бизнес-логику делегирует профильным компонентам.

Например:

* `ExportAttributesStage` вызывает `AttributeExporter`;
* `AnalyzeValuesStage` использует `ValueParserRegistry`;
* `BuildSqlPreviewStage` вызывает `SqlPreviewBuilder`;
* `BuildReportStage` вызывает `ReportBuilder`;
* `BuildFrameworkResultStage` вызывает `FrameworkResultBuilder`.

---

## DTO

DTO должны жить в:

```text
src/DTO/
```

Там должны находиться:

* `AttributeJob`;
* `AttributeContext`;
* `FrameworkResult`;
* вспомогательные структуры выполнения.

DTO не должны превращаться в сервисы.

Они не должны:

* читать БД;
* запускать stages;
* выполнять SQL;
* принимать решения о синонимах;
* нормализовать значения.

---

## ValueParser и AttributeExporter

`ValueParser` должен жить в:

```text
src/Parser/
```

Он не знает про БД, товары, категории, каноны, импорты и SQL preview.

Он получает одно значение и возвращает `ParseResult`.

`AttributeExporter` должен жить в:

```text
src/Exporter/
```

Он является read-only компонентом.

Он читает факты из OpenCart и не принимает решений о синонимах.

`AttributeExporter` не должен:

* нормализовать значения;
* выполнять `ValueParser`;
* выбирать канон;
* утверждать кандидатов;
* формировать SQL preview;
* изменять БД.

---

## Минимальный MVP-скелет

Рекомендуемый порядок создания минимального implementation skeleton:

1. `StageInterface`
2. `PipelineEngine`
3. `AttributeJob`
4. `AttributeContext`
5. `FrameworkResult`
6. `StageResult`
7. 9 пустых stages с правильными technical names:

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

8. `FrameworkRunner`
9. один example job

Цель первого skeleton — проверить каркас выполнения pipeline и запись `stage_results`, а не реализовать бизнес-логику.

---

## Что можно оставить заглушками

На первом implementation шаге можно оставить заглушками:

* OpenCart connection;
* canonical repository;
* scope resolver;
* attribute exporter;
* SQL preview builder;
* все value parsers кроме одного тестового.

Заглушки должны сохранять контракт входов/выходов и не подменять архитектурные решения.

---

## Что не делать на первом шаге

На первом шаге не нужно:

* писать SQL apply;
* подключаться к production;
* делать UI;
* делать массовый запуск;
* создавать новые таблицы;
* смешивать parser, exporter, stage и result-builder;
* делать универсальный plugin-system.

---

## Риски

Основные риски:

* переусложнение до первого запуска;
* смешивание DTO и поведения;
* преждевременная DB-driven конфигурация;
* превращение `BuildSqlPreviewStage` в решателя спорных случаев;
* разрастание stages вместо делегирования компонентам.

---

## Рекомендованный первый implementation step

Создать минимальный каркас:

```text
StageInterface
PipelineEngine
DTO
9 пустых stages
```

Этот шаг должен быть выполнен только после отдельной команды.

Первый implementation step не должен содержать бизнес-логику стандартизации.
