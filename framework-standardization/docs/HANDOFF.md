# HANDOFF — framework-standardization

Дата: 08.07.2026

## Текущая стабильная точка

Текущий stable point:

`d2a2bea Document max head apply readiness`

Рабочее дерево после коммита должно быть чистым.

Последняя цепочка коммитов:

```text
d2a2bea Document max head apply readiness
ce8b1a2 Document manual review of apply plan preview
177bef4 Document DB readonly apply plan preview check
b46d70f Add DB readonly apply plan preview command
423355f Document manual review of SQL preview
```

## Контекст проекта

Репозиторий:

`saitkharkov-cyber/home-energetika`

Рабочий проект:

`framework-standardization`

Текущий workflow строится вокруг безопасной стандартизации характеристик через последовательные gate.

Главная модель процесса:

```text
target attribute meaning
→ DB-readonly attribute name discovery
→ human canonical selection
→ explicit include/exclude alias decision
→ raw values inventory
→ canonical unit / normalized_value contract
→ range policy
→ normalization proposals
→ review-chain
→ sample review
→ human review decision
→ SQL preview
→ SQL preview review
→ apply-plan preview
→ apply-plan preview review
→ apply readiness decision
→ bounded apply command with explicit confirmation
→ post-apply verification
```

## Важные правила взаимодействия

- Работаем маленькими bounded steps.
- После каждого шага: diff/check → commit → push → clean status.
- Не использовать stash без крайней необходимости.
- Не спрашивать Codex “что дальше”.
- Следующий шаг определяет ChatGPT вместе с пользователем.
- Codex получает только конкретный bounded prompt.
- Не предлагать implementation без explicit `+` от пользователя.
- docs/RULES.md должен читать и соблюдать ChatGPT.
- Codex не должен получать docs/RULES.md как входной документ.
- Если правило важно для Codex-step, ChatGPT явно включает его в prompt.
- Не продолжать старую ветку `immediate pump_max_head fixture/job`.
- Не делать production/cache изменения.
- Не делать cache rebuild.
- Не делать SQL apply без отдельного явного gate.
- Не делать auto-canonical selection.
- Не делать auto-merge.
- Термин `gate` оставляем как рабочий термин проекта.

## Paused / rejected path

Старая ветка с immediate `pump_max_head` fixture/source/job считается остановленной и не продолжается.

Не возвращаться к:

```text
immediate pump_max_head fixture/job
автоматический merge
автоматический canonical selection
SQL apply без gate
production/cache изменения
```

## Текущий target

Текущая характеристика:

`максимальный напор`

Category scope:

`11900213`

Canonical attribute:

`12 — Максимальный напор`

Included aliases:

```text
101 — Максимальный напор, м.вод.ст.
119 — Максимальный напор, м
81 — Max напор, м
```

Excluded attributes:

```text
20 — Минимальный напор
171 — Максимальный расход Qmax, м³/ч
100 — Максимальный расход Qmax, м³/ч
120 — Номинальный напор, м
```

Canonical unit:

`m`

Normalized value:

`decimal meters`

## Что уже сделано

### Discovery / decision

Реализованы и проверены DB-readonly discovery/inventory этапы.

Зафиксированы:

- canonical selection;
- included aliases;
- excluded non-target attributes;
- raw values inventory;
- unit contract;
- range policy.

### Range policy

Диапазоны и upper-bound/mixed values остаются unresolved.

Примеры unresolved:

```text
100-104
104–118
50–51,5
до 51 м
```

Они не нормализуются автоматически и не попадают в SQL/apply.

### Proposals / review-chain

Сгенерированы proposals:

```text
481 accepted simple proposals
14 unresolved values
```

Review-chain:

```text
481 pending_review
14 unresolved
```

Sample review:

```text
50 строк sample просмотрены вручную
sample выглядит корректно
```

Human review decision:

```text
481 accepted simple proposals получили review-approved на уровне ревью
14 unresolved values остаются unresolved
```

Важно:

```text
review-approved не означает SQL/apply permission
```

### SQL preview

Реализована DB-readonly SQL preview command:

```text
framework-standardization/bin/db-readonly-sql-preview.php
framework-standardization/src/Preview/DbReadOnlySqlPreview.php
```

SQL preview summary:

```text
preview_update_existing_canonical_row_count: 400
preview_insert_missing_canonical_row_count: 81
keep_existing_source_row_count: 81
unresolved_excluded_count: 14
schema_blocker_count: 0
conflicts_count: 0
```

Схема хранения подтверждена:

```text
table_name: oc_product_attribute
relevant_columns: product_id,attribute_id,language_id,text
schema_status: ok
```

SQL preview вручную просмотрен.

Подтверждено:

- UPDATE только `oc_product_attribute`;
- UPDATE только `attribute_id = 12`;
- INSERT только `attribute_id = 12`;
- `language_id` сохраняется;
- unresolved values не попали в SQL preview;
- DELETE/ALTER/DROP/TRUNCATE/CREATE TABLE отсутствуют;
- SQL не выполнялся;
- SQL files не создавались.

### Apply-plan preview

Реализована DB-readonly apply-plan preview command:

```text
framework-standardization/bin/db-readonly-apply-plan-preview.php
framework-standardization/src/ApplyPlan/DbReadOnlyApplyPlanPreview.php
```

Apply-plan preview summary:

```text
apply_plan_preview_generated: 1
update_existing_canonical_row_count: 400
insert_missing_canonical_row_count: 81
keep_existing_source_row_count: 81
unresolved_excluded_count: 14
schema_blocker_count: 0
conflicts_count: 0
executable_apply_plan: 0
sql_apply_allowed: 0
```

Short-check выполнен.

Проверено:

- preflight checks ok;
- UPDATE group = 400;
- INSERT group = 81;
- keep source alias rows = 81;
- unresolved excluded = 14;
- executable_apply_plan = 0;
- sql_apply_allowed = 0;
- sql_applied = 0;
- product_data_changed = 0;
- опасный SQL не найден, кроме текстовых rollback notes;
- первые UPDATE имеют ожидаемый формат:

```sql
UPDATE oc_product_attribute SET text = '46.5' WHERE product_id = 1068 AND attribute_id = 12 AND language_id = 1;
```

INSERT формат ранее подтверждён:

```sql
INSERT INTO oc_product_attribute (product_id, attribute_id, language_id, text) VALUES (..., 12, 1, '...');
```

### Apply readiness

Зафиксирован decision-документ:

```text
framework-standardization/docs/APPLY_READINESS_MAX_HEAD_SCOPE_11900213.md
```

Решение:

```text
apply-ready for local dump / staging-like controlled DB only
```

Важно:

- production apply запрещён;
- cache rebuild запрещён;
- SQL apply ещё не выполнялся;
- следующий gate — bounded apply command with explicit confirmation;
- apply-команда должна иметь dry-run/preview режим по умолчанию;
- apply-команда должна требовать явный флаг, например `--confirm-apply`;
- без явного флага команда должна завершаться без изменений;
- перед apply нужен backup или dump snapshot;
- после apply нужен отдельный verification step.

## Документы, которые обязательно читать в новом чате

В новом чате сначала читать:

1. `framework-standardization/docs/START_HERE.md`
2. `framework-standardization/docs/HANDOFF.md`
3. `framework-standardization/README.md`
4. `framework-standardization/PROJECT_MASTER_SUMMARY.md`
5. `PROJECT_MASTER_SUMMARY.md`
6. `framework-standardization/docs/DECISIONS.md`
7. `framework-standardization/docs/RUNTIME_CHECKS.md`
8. `framework-standardization/docs/RULES.md`
9. Актуальные docs/specs:
   - `framework-standardization/docs/HUMAN_DECISION_MAX_HEAD_SCOPE_11900213.md`
   - `framework-standardization/docs/MAX_HEAD_UNIT_CONTRACT_SCOPE_11900213.md`
   - `framework-standardization/docs/MAX_HEAD_RANGE_POLICY_SCOPE_11900213.md`
   - `framework-standardization/docs/HUMAN_REVIEW_MAX_HEAD_PROPOSALS_SCOPE_11900213.md`
   - `framework-standardization/docs/SQL_PREVIEW_PLAN_MAX_HEAD_SCOPE_11900213.md`
   - `framework-standardization/docs/APPLY_READINESS_MAX_HEAD_SCOPE_11900213.md`

## Следующий рекомендуемый direction

Следующий gate:

```text
bounded apply command with explicit confirmation
```

Но это не значит немедленный apply.

Рекомендуемый следующий маленький шаг:

```text
реализовать apply-команду, которая по умолчанию работает в dry-run/preview режиме,
а реальные изменения делает только при явном --confirm-apply
и только на local dump / staging-like controlled DB
```

Границы будущей apply-команды:

Разрешено только при explicit confirmation:

- UPDATE `oc_product_attribute.text` для existing canonical rows;
- INSERT missing canonical rows в `oc_product_attribute`;
- только `attribute_id = 12`;
- только конкретные `product_id`;
- только конкретный `language_id`;
- только review-approved values;
- только category_scope `11900213`.

Запрещено:

- DELETE;
- ALTER;
- DROP;
- TRUNCATE;
- CREATE TABLE;
- wide UPDATE без `product_id`;
- production/cache;
- cache rebuild;
- unresolved values;
- source alias rows modification;
- auto-merge;
- auto-canonical selection;
- apply без `--confirm-apply`.

После apply нужен отдельный gate:

```text
post-apply verification
```

Минимальная verification:

- updated count = 400;
- inserted count = 81;
- affected rows только `attribute_id = 12`;
- affected products только scope `11900213`;
- unresolved не применены;
- source alias rows сохранены;
- product data changed only expected rows;
- no conflicts/duplicates.