# Build SQL Preview Stage

Документ описывает контракт `BuildSqlPreviewStage` — stage формирования SQL preview в Framework Standardization.

`BuildSqlPreviewStage` работает после анализа имён и значений.

```text
AnalyzeNamesStage
→ AnalyzeValuesStage
→ BuildSqlPreviewStage
````

---

## Назначение

`BuildSqlPreviewStage` формирует SQL preview для ручной проверки инженером.

Главная задача stage:

```text
AttributeContext
→ safety checks
→ SQL preview
→ blockers / warnings
```

SQL preview — это не автоматическое применение изменений.

Это текст SQL, который инженер должен проверить вручную перед любым применением.

---

## Главный принцип

`BuildSqlPreviewStage` не выполняет SQL.

Stage может только подготовить SQL statements и записать их в:

```text
AttributeContext.sql_preview
```

Запрещено выполнять:

```text
INSERT
UPDATE
DELETE
ALTER
CREATE
DROP
```

в продуктивной БД из этой stage.

---

## Место в pipeline

Порядок выполнения:

```text
ValidateJobStage
→ ResolveCanonicalStage
→ ResolveScopeStage
→ ExportAttributesStage
→ AnalyzeNamesStage
→ AnalyzeValuesStage
→ BuildSqlPreviewStage
→ BuildReportStage
→ BuildFrameworkResultStage
```

К моменту запуска `BuildSqlPreviewStage` уже должны быть заполнены:

* `AttributeContext.canonical`;
* `AttributeContext.scope`;
* `AttributeContext.synonym_candidates`;
* `AttributeContext.attribute_value_structure`;
* `AttributeContext.value_report`;
* `AttributeContext.warnings`;
* `AttributeContext.errors`;
* `AttributeContext.job.raw_job.output`.

---

## Техническое имя stage

```text
build_sql_preview
```

Результат выполнения записывается в:

```text
AttributeContext.stage_results.build_sql_preview
```

---

## Ответственность BuildSqlPreviewStage

Stage отвечает за:

* проверку, запрошен ли SQL preview;
* проверку safety blockers;
* формирование SQL preview;
* установку `safe_to_apply`;
* заполнение `blocked_by`;
* объяснение причин блокировки;
* подготовку данных для отчёта и `FrameworkResult`.

---

## Что BuildSqlPreviewStage не делает

`BuildSqlPreviewStage` не должна:

* выполнять SQL;
* менять БД;
* утверждать синонимы;
* выбирать канон;
* нормализовать значения;
* анализировать имена;
* читать OpenCart напрямую;
* изменять импорты;
* скрывать blockers от инженера.

---

## Читает из AttributeContext

```text
AttributeContext.job.raw_job.output
AttributeContext.job.raw_job.value_rules
AttributeContext.canonical
AttributeContext.scope
AttributeContext.synonym_candidates
AttributeContext.attribute_name_structure
AttributeContext.attribute_value_structure
AttributeContext.value_report
AttributeContext.warnings
AttributeContext.errors
```

---

## Пишет в AttributeContext

```text
AttributeContext.sql_preview
AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.build_sql_preview
```

---

## Структура sql_preview

Минимальная структура:

```text
sql_preview
├─ enabled
├─ generated
├─ safe_to_apply
├─ blocked_by
├─ statements
└─ notes
```

---

### enabled

Флаг, был ли SQL preview запрошен в `Attribute Job`.

Берётся из:

```text
output.generate_sql_preview
```

Если `enabled = 0`, stage должна завершиться со статусом:

```text
skipped
```

---

### generated

Флаг, был ли SQL preview фактически сформирован.

```text
generated = 1
```

означает, что statements подготовлены.

```text
generated = 0
```

означает, что SQL preview не сформирован из-за блокировок, ошибок или потому что preview не был запрошен.

---

### safe_to_apply

Предварительная оценка безопасности SQL preview.

Важно:

```text
safe_to_apply = 1
```

не означает автоматическое применение SQL.

Это означает только:

```text
Framework не видит известных блокировок,
но инженер всё равно должен проверить SQL вручную.
```

---

### blocked_by

Список причин, по которым SQL preview нельзя считать безопасным.

Примеры:

```text
errors_exist
manual_approval_required
ambiguous_candidates_exist
unknown_values_exist
invalid_values_exist
canonical_not_active
canonical_not_locked
apply_changes_not_allowed
```

---

### statements

Список SQL statements.

На текущем этапе statements являются только preview.

---

### notes

Пояснения для инженера.

Примеры:

```text
SQL preview was generated for manual review only.
Replace {DB_PREFIX} with the real OpenCart DB prefix before execution.
Backup database before applying any generated SQL.
```

---

## Preconditions

Stage может выполняться только если:

```text
AttributeContext.canonical заполнен
AttributeContext.scope заполнен
AttributeContext.attribute_name_structure заполнен
AttributeContext.attribute_value_structure заполнен
AttributeContext.job.raw_job.output заполнен
```

Если `output.generate_sql_preview = 0`, stage не является ошибочной.

Она должна завершиться как:

```text
skipped
```

---

## Safety blockers

SQL preview не должен считаться безопасным, если есть хотя бы одна блокировка.

---

### 1. Errors exist

Если:

```text
AttributeContext.errors не пустой
```

то:

```text
safe_to_apply = 0
blocked_by += errors_exist
```

---

### 2. Manual approval required

Если есть предложенные кандидаты в синонимы:

```text
AttributeContext.synonym_candidates.proposed не пустой
```

и они не прошли ручное утверждение, то:

```text
safe_to_apply = 0
blocked_by += manual_approval_required
```

Причина:

```text
Framework не имеет права автоматически решить,
что найденный атрибут является синонимом.
```

---

### 3. Ambiguous candidates exist

Если есть спорные кандидаты:

```text
AttributeContext.synonym_candidates.ambiguous не пустой
```

то:

```text
safe_to_apply = 0
blocked_by += ambiguous_candidates_exist
```

---

### 4. Unknown values exist

Если:

```text
unknown_value_policy = block_sql
```

и:

```text
AttributeContext.attribute_value_structure.unknown_values не пустой
```

то:

```text
safe_to_apply = 0
blocked_by += unknown_values_exist
```

---

### 5. Invalid values exist

Если:

```text
AttributeContext.attribute_value_structure.invalid_values не пустой
```

то SQL preview не должен считаться безопасным.

```text
safe_to_apply = 0
blocked_by += invalid_values_exist
```

---

### 6. Canonical not active

Если:

```text
canonical.status != active
```

то:

```text
safe_to_apply = 0
blocked_by += canonical_not_active
```

---

### 7. Canonical not locked

Если:

```text
canonical.locked != 1
```

то:

```text
safe_to_apply = 0
blocked_by += canonical_not_locked
```

---

### 8. apply_changes is not allowed

На текущем этапе:

```text
output.apply_changes
```

должен всегда быть:

```text
0
```

Если:

```text
output.apply_changes = 1
```

то это критическая ошибка:

```text
apply_changes_not_allowed
```

и SQL preview не должен считаться безопасным.

---

## Что может попасть в SQL preview

На первом этапе SQL preview может включать только подготовленные statements для ручной проверки.

Потенциальные типы операций:

```text
1. перенос значений со старого attribute_id на target_attribute_id
2. вставка отсутствующих product_attribute значений
3. обновление существующих значений target_attribute_id
4. диагностические SELECT-запросы перед применением
```

Важно:

конкретный набор SQL statements зависит от будущего решения по стратегии стандартизации.

Этот документ фиксирует safety-контракт, а не финальный SQL-алгоритм переноса.

---

## Чего не должно быть в SQL preview первого этапа

На первом этапе SQL preview не должен включать:

* удаление атрибутов из `{DB_PREFIX}attribute`;
* удаление групп атрибутов;
* автоматическое удаление старых значений;
* изменение импортов;
* изменение таблиц ExcelPort / Suppler;
* автоматическое создание канонических атрибутов;
* любые операции без ручного подтверждения инженера.

---

## Placeholder DB prefix

SQL preview должен использовать placeholder:

```text
{DB_PREFIX}
```

Пример:

```sql
UPDATE `{DB_PREFIX}product_attribute`
SET `attribute_id` = 123
WHERE `attribute_id` = 456;
```

Перед ручным применением инженер должен заменить:

```text
{DB_PREFIX}
```

на реальный префикс OpenCart.

Пример:

```text
oc_
```

---

## Минимальный SQL preview format

Каждый statement должен быть представлен не просто строкой, а структурой:

```text
statement
├─ type
├─ description
├─ sql
├─ affected_scope
├─ safety_notes
└─ requires_manual_review
```

---

### type

Тип statement.

Примеры:

```text
diagnostic_select
move_attribute_values
insert_missing_target_values
update_target_values
```

---

### description

Человекочитаемое описание, зачем нужен statement.

---

### sql

Текст SQL.

---

### affected_scope

Описание области, которую затрагивает statement.

Пример:

```text
category_id: 11900213
product_count: 120
source_attribute_id: 456
target_attribute_id: 123
```

---

### safety_notes

Пояснения и ограничения.

---

### requires_manual_review

На текущем этапе всегда:

```text
1
```

---

## Diagnostic SELECT statements

Перед любыми изменяющими statements SQL preview должен уметь включать диагностические SELECT-запросы.

Примеры задач diagnostic SELECT:

* показать товары, у которых есть старый attribute_id;
* показать товары, у которых уже есть target_attribute_id;
* найти конфликты значений;
* показать значения, которые будут затронуты;
* проверить количество строк перед обновлением.

Пример:

```sql
SELECT
  pa.product_id,
  pa.attribute_id,
  pa.language_id,
  pa.text
FROM `{DB_PREFIX}product_attribute` pa
WHERE pa.attribute_id = 456
  AND pa.product_id IN (...)
ORDER BY pa.product_id;
```

---

## Handling conflicts

Конфликт возникает, если у одного товара уже есть:

```text
source_attribute_id
```

и одновременно:

```text
target_attribute_id
```

для той же характеристики.

Такие ситуации нельзя автоматически перезаписывать.

Они должны попадать в блокировку или отдельный conflict report.

Примеры blockers:

```text
target_value_conflict
duplicate_attribute_values
conflicting_normalized_values
```

---

## SQL preview generation rules

Stage может сформировать SQL preview только если:

```text
output.generate_sql_preview = 1
```

При этом возможны два режима:

```text
generated = 1, safe_to_apply = 1
generated = 1, safe_to_apply = 0
```

То есть SQL preview может быть сформирован даже при блокировках, но обязан явно показать:

```text
blocked_by
safe_to_apply = 0
```

Если есть критические ошибки, SQL preview может не формироваться вообще:

```text
generated = 0
blocked_by += errors_exist
```

---

## Stage result

Результат stage записывается в:

```text
AttributeContext.stage_results.build_sql_preview
```

Минимальная структура:

```text
status
started_at
finished_at
errors
warnings
summary
```

Пример:

```yaml
status: blocked
started_at: 2026-07-03 12:20:00
finished_at: 2026-07-03 12:20:02
errors: []
warnings:
  - manual_approval_required
summary:
  generated: 1
  safe_to_apply: 0
  statements: 3
  blocked_by:
    - manual_approval_required
    - unknown_values_exist
```

---

## Пример sql_preview с блокировкой

```yaml
sql_preview:
  enabled: 1
  generated: 1
  safe_to_apply: 0
  blocked_by:
    - manual_approval_required
    - canonical_not_locked
  notes:
    - SQL preview is for manual review only.
    - Proposed synonym candidates require manual approval.
  statements:
    - type: diagnostic_select
      description: Show products using source attribute candidate.
      sql: |
        SELECT
          pa.product_id,
          pa.attribute_id,
          pa.language_id,
          pa.text
        FROM `{DB_PREFIX}product_attribute` pa
        WHERE pa.attribute_id = 456
          AND pa.product_id IN (...);
      affected_scope:
        category_id: 11900213
        source_attribute_id: 456
        target_attribute_id: 123
      safety_notes:
        - Diagnostic query only.
      requires_manual_review: 1
```

---

## Пример sql_preview без известных блокировок

```yaml
sql_preview:
  enabled: 1
  generated: 1
  safe_to_apply: 1
  blocked_by: []
  notes:
    - SQL preview is for manual review only.
    - Backup database before applying SQL manually.
  statements:
    - type: diagnostic_select
      description: Show rows that will be affected.
      sql: |
        SELECT
          pa.product_id,
          pa.attribute_id,
          pa.language_id,
          pa.text
        FROM `{DB_PREFIX}product_attribute` pa
        WHERE pa.attribute_id = 456
          AND pa.product_id IN (...);
      requires_manual_review: 1
```

---

## Ошибки

Критические ошибки:

```text
sql_preview_build_failed
apply_changes_not_allowed
sql_preview_rules_invalid
```

Ошибки записываются в:

```text
AttributeContext.errors
AttributeContext.stage_results.build_sql_preview.errors
```

---

## Предупреждения

Возможные предупреждения:

```text
manual_approval_required
ambiguous_candidates_exist
unknown_values_exist
invalid_values_exist
canonical_not_active
canonical_not_locked
sql_preview_generated_with_blockers
```

---

## Влияние на FrameworkResult

`FrameworkResult` должен получить из `BuildSqlPreviewStage`:

```text
sql_preview.enabled
sql_preview.generated
sql_preview.safe_to_apply
sql_preview.blocked_by
sql_preview.statements
sql_preview.notes
```

Если `safe_to_apply = 0`, итоговый `FrameworkResult.result_status` должен быть:

```text
blocked
```

если при этом нет критических ошибок.

---

## Граница с AnalyzeNamesStage

`AnalyzeNamesStage` формирует:

```text
synonym_candidates.proposed
synonym_candidates.ambiguous
```

`BuildSqlPreviewStage` использует эти данные только как safety-сигналы.

Она не утверждает кандидатов и не меняет их статус.

---

## Граница с AnalyzeValuesStage

`AnalyzeValuesStage` формирует:

```text
normalized_values
unknown_values
invalid_values
empty_values
value_report
```

`BuildSqlPreviewStage` использует эти данные для проверки безопасности и формирования SQL preview.

Она не вызывает `ValueParser`.

---

## Граница с Manual Approval

На текущем этапе ручное утверждение выполняется инженером вне Framework.

Будущая stage:

```text
ManualApprovalStage
```

может быть добавлена позже.

До появления такой stage любые `proposed_synonym_candidates` должны считаться требующими ручного подтверждения.

---

## Требования к реализации

Реализация `BuildSqlPreviewStage` должна быть:

* безопасной;
* объяснимой;
* детерминированной;
* без выполнения SQL;
* без записи в БД;
* с явными blockers;
* с явными warnings;
* пригодной для ручной проверки;
* расширяемой под будущие стратегии SQL preview.

---

## Статус документа

Документ является архитектурным контрактом `BuildSqlPreviewStage`.

Код реализации должен следовать этому контракту.

````

В `PROJECT_MASTER_SUMMARY.md` добавить:

```markdown
| `docs/BUILD_SQL_PREVIEW_STAGE.md` | Контракт stage формирования безопасного SQL preview |
````

В список текущих контрактов:

```text
docs/BUILD_SQL_PREVIEW_STAGE.md
```

Следующий логичный шаг в summary заменить на:

````markdown
## Следующий логичный шаг

Описать контракт `BuildReportStage`:

```text
docs/BUILD_REPORT_STAGE.md
````

Документ должен зафиксировать, как Framework формирует человекочитаемый отчёт для инженера на основе `AttributeContext`, warnings, errors, diagnostics и SQL preview.

