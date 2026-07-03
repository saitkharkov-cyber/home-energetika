# Project Master Summary

Проект: **Framework Standardization**  
Репозиторий: `home-energetika`  
Папка проекта: `framework-standardization`

---

## Назначение проекта

Проект предназначен для безопасной стандартизации характеристик каталога Home Energetika.

Основная задача:

```text
найти разные варианты одной характеристики
и привести их к утверждённому каноническому атрибуту OpenCart
````

Пример:

```text
Диаметр насоса (мм)
Диаметр корпуса
Диаметр, мм
Ø насоса
```

могут быть проанализированы как кандидаты на приведение к одному каноническому атрибуту.

---

## Стартовые документы

| Документ                     | Назначение                                           |
| ---------------------------- | ---------------------------------------------------- |
| `TECHNICAL_SPECIFICATION.md` | Техническое задание проекта                          |
| `README.md`                  | Навигация по проекту                                 |
| `PROJECT_MASTER_SUMMARY.md`  | Сводка архитектуры и текущего состояния              |
| `docs/ATTRIBUTE_PIPELINE.md` | Архитектурный контракт конвейера обработки атрибутов |
| `docs/STAGES_PIPELINE.md`    | Контракт stage pipeline: порядок stages, входы/выходы, ошибки и правила остановки |
| `docs/CANONICAL_ATTRIBUTE_REGISTRATION.md` | Контракт регистрации канонического атрибута |
| `docs/ATTRIBUTE_JOB.md`      | Контракт одной задачи обработки характеристики       |
| `docs/ATTRIBUTE_EXPORTER.md` | Контракт для чтения фактов из OpenCart для Framework Standardization. |
| `docs/ATTRIBUTE_CONTEXT.md`  | Контракт рабочего состояния Framework                |
| `docs/FRAMEWORK_RESULT.md`   | Контракт финального результата Framework             |
| `docs/VALUE_PARSER.md`       | Контракт компонента нормализации одного значения характеристики |
| `sql/CREATE_TABLE_canonical_attributes.sql` | SQL-драфт таблицы canonical attributes |
| `docs/ANALYZE_NAMES_STAGE.md` | Контракт stage анализа имён атрибутов и формирования кандидатов в синонимы |
| `docs/ANALYZE_VALUES_STAGE.md` | Контракт stage анализа и нормализации значений атрибутов |
| `docs/BUILD_SQL_PREVIEW_STAGE.md` | Контракт stage формирования безопасного SQL preview |
| `docs/BUILD_REPORT_STAGE.md` | Контракт stage формирования человекочитаемого отчёта для инженера |

---

## Текущая архитектурная модель

Framework работает с одной задачей обработки характеристики:

```text
Attribute Job
```

`Attribute Job` описывает:

* какую характеристику обрабатываем;
* какой канонический атрибут выбран;
* в какой области выполняем анализ;
* какой parser / тип / правила применяются;
* какой результат нужно подготовить.

Framework не выбирает характеристику сам.
Характеристика выбирается инженером вручную.

---

## Канонический атрибут

Канонический атрибут — это глобальный целевой атрибут OpenCart.

Ключевое решение:

```text
Канонический атрибут = глобальная сущность OpenCart.
Категория = область анализа и применения.
```

Категория не является частью идентичности канона.

Перед стандартизацией характеристики в категории нужно сначала проверить, существует ли уже подходящий глобальный канон по смыслу.

---

## Что выносится в БД

На текущем этапе в БД выносится только одна таблица:

```text
{DB_PREFIX}canonical_attributes
```

Ответственность таблицы:

```text
какой реальный attribute_id OpenCart считается глобальным каноном
```

Утверждённая структура:

```text
{DB_PREFIX}canonical_attributes
├─ canonical_id
├─ canonical_code              UNIQUE
├─ target_attribute_id         UNIQUE
├─ target_attribute_name
├─ target_attribute_group_id
├─ target_attribute_group_name
├─ status                      draft / active
├─ locked                      0 / 1
├─ comment
├─ created_at
└─ updated_at
```

Типы полей:

```text
canonical_id
→ INT UNSIGNED AUTO_INCREMENT PRIMARY KEY

canonical_code
→ VARCHAR(64) NOT NULL UNIQUE

target_attribute_id
→ INT UNSIGNED NOT NULL UNIQUE

target_attribute_name
→ VARCHAR(255) NOT NULL

target_attribute_group_id
→ INT UNSIGNED NOT NULL

target_attribute_group_name
→ VARCHAR(255) NOT NULL

status
→ ENUM('draft', 'active') NOT NULL DEFAULT 'draft'

locked
→ TINYINT(1) NOT NULL DEFAULT 0

comment
→ TEXT NULL

created_at
→ DATETIME NOT NULL

updated_at
→ DATETIME NOT NULL
```

---

## Что не выносится в БД

На текущем этапе в БД не выносятся:

* `scope` / `category_id`;
* `value_parser`;
* `value_type`;
* `allow_empty`;
* правила нормализации;
* правила валидации;
* синонимы;
* результаты анализа;
* найденные кандидаты.

Эти данные остаются внутри:

```text
Framework / Attribute Job / конфигурации запуска
```

---

## Scope

`scope` — это область анализа и применения.

На текущем этапе `scope` не хранится в БД.

Он передаётся через:

```text
Attribute Job
```

или конфигурацию запуска.

Пример:

```text
canonical_code: pump_diameter
category_id: 11900213
```

---

## Статусы и блокировка канона

Используются только два статуса:

```text
draft
active
```

`disabled` не используется.

`locked` — отдельный булев флаг:

```text
locked = 1
```

означает, что канон утверждён и не должен изменяться автоматически.

Изменение утверждённого канона возможно только вручную как отдельный инженерный процесс.

---

## Ограничения таблицы

Используются уникальные ограничения:

```text
UNIQUE(canonical_code)
UNIQUE(target_attribute_id)
```

`FOREIGN KEY` не используется.

Причина: не связывать служебную таблицу жёстко с таблицами OpenCart и не создавать риски для импортов, миграций и модулей.

Проверка канона выполняется перед добавлением записи:

* `target_attribute_id` существует;
* `target_attribute_group_id` существует;
* атрибут относится к указанной группе;
* имя атрибута совпадает;
* имя группы совпадает;
* `canonical_code` свободен;
* `target_attribute_id` ещё не используется как канон.

---

## AttributeExporter

`AttributeExporter` — read-only слой чтения фактов из БД.

Он:

* читает фактические атрибуты и значения из OpenCart;
* проверяет наличие целевого атрибута;
* возвращает найденные сырые атрибуты;
* считает `usage_count`;
* собирает `sample_values`.

Он не:

* нормализует значения;
* решает, что является синонимом;
* создаёт атрибуты;
* изменяет БД;
* применяет результат.

---

## AnalyzeStage

`AnalyzeStage` отвечает за анализ кандидатов.

Он:

* смотрит на найденные атрибуты;
* анализирует частотность;
* выявляет потенциальные синонимы;
* формирует диагностику;
* создаёт предупреждения.

Частотность — диагностический сигнал, а не автоматический выбор канона.

---

## ValueParser

`ValueParser` отвечает только за нормализацию одного значения.

Он не работает с БД и не принимает решений о синонимах.

Пример:

```text
"96 мм" → 96
"4\""   → 101.6
```

---

## FrameworkResult

`FrameworkResult` — финальная проекция из `AttributeContext`.

Он не является отдельным независимым состоянием.

Минимально содержит:

```text
FrameworkResult
├─ canonical_attribute
├─ proposed_synonym_candidates[]
├─ rejected_candidates[]
├─ value_report
├─ warnings[]
├─ sql_preview
├─ report
└─ unknown_values
```

`proposed_synonym_candidates` — это предложения Framework, а не финальное автоматическое решение.

Финальное решение принимает инженер.

---

## Связь с импортом

Существующие импорты сейчас не изменяются.

Импорт рассматривается как будущий потребитель canonical layer.

Будущий защитный слой:

```text
CanonicalAttributeResolver
```

должен стать границей интеграции с импортами.

Он будет решать задачу:

```text
category_id
+ incoming_attribute_name
+ incoming_attribute_group
+ source
→ canonical_id
→ target_attribute_id / not_found / ambiguous
```

На текущем этапе это только архитектурный ориентир.
Правки ExcelPort / Suppler сейчас не выполняются.

---

## Ручной контроль

Инженер вручную:

* выбирает характеристику;
* проверяет наличие глобального канона;
* утверждает канонический атрибут;
* задаёт область анализа;
* проверяет найденные кандидаты;
* проверяет SQL preview;
* принимает решение о публикации;
* вручную применяет результат на продуктиве.

Framework не публикует результат автоматически.

---

## Текущий статус

Зафиксированы архитектурные контракты:

```text
docs/ATTRIBUTE_PIPELINE.md
docs/STAGES_PIPELINE.md
docs/CANONICAL_ATTRIBUTE_REGISTRATION.md
docs/ATTRIBUTE_JOB.md
docs/ATTRIBUTE_EXPORTER.md
docs/ATTRIBUTE_CONTEXT.md
docs/FRAMEWORK_RESULT.md
docs/VALUE_PARSER.md
docs/ANALYZE_NAMES_STAGE.md
docs/ANALYZE_VALUES_STAGE.md
docs/BUILD_SQL_PREVIEW_STAGE.md
docs/BUILD_REPORT_STAGE.md
sql/CREATE_TABLE_canonical_attributes.sql
```

Базовый документ ATTRIBUTE_PIPELINE.md прошёл read-only ревью Codex.

Критичные противоречия закрыты.

```text
Attribute Job
→ AttributeContext
→ Attribute Pipeline
→ FrameworkResult
```
Последнее зафиксированное решение:

```text
В БД текущего этапа выносится только {DB_PREFIX}canonical_attributes.
Scope, правила обработки, синонимы и результаты анализа остаются вне БД.
```

---

## Следующий логичный шаг

Описать контракт `BuildFrameworkResultStage`:

```text
docs/BUILD_FRAMEWORK_RESULT_STAGE.md
````

Документ должен зафиксировать, как Framework собирает финальный `FrameworkResult` из `AttributeContext`, report, warnings, errors, SQL preview и stage summary.

---

## Правило работы

Работа ведётся маленькими шагами.

Порядок:

```text
анализ
→ рекомендация
→ решение инженера
→ фиксация
→ только потом реализация
```

Код и SQL не выполняются до утверждения архитектурного решения.