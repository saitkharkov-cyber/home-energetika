# HANDOFF — framework-standardization / generic canonical apply

Дата: 10-07-2026
Проект: `saitkharkov-cyber/home-energetika`
Рабочая область: `framework-standardization`
Functional stable point before this handoff update:

`85862c6 Document project date time output format`

Codex resume:  `codex resume 019f35ab-7752-7251-a297-f16421ea092a`

## 1. Общий контекст

Работа ведётся над framework для безопасной стандартизации характеристик OpenCart-каталога.

Текущая целевая характеристика:

* target meaning: `максимальный напор`
* category scope: `11900213` / Скважинные насосы
* canonical attribute: `12`
* alias attributes: `101,119,81`
* normalizer: `simple_meters`
* contract: `framework-standardization/config/attribute-contracts/max_head_11900213.php`

Цель последних этапов — перейти от hardcoded max_head prototype к generic contract-driven canonical apply engine.

## 2. Важные правила работы

Вся документация, комментарии, пояснения и отчёты — только на русском языке.

Английский допускается только для технических идентификаторов:

* PHP array keys;
* class names;
* namespaces;
* CLI options;
* file paths;
* SQL identifiers;
* literal / enum-like values.

Команды для PowerShell 7+ давать одной строкой через `&&`.

Команды commit должны включать tail:

`&& git log --oneline --decorate -5 && git status --short`

Не просить Codex читать `docs/RULES.md`; нужные правила вставлять в prompt явно.

Не менять `HANDOFF.md` в середине работы. `HANDOFF` обновляется только при закрытии / переносе чата.

Не выполнять SQL/apply/production/cache/cache rebuild без отдельного явного gate.

## 3. Последние стабильные коммиты

Последний подтверждённый log:

```text
85862c6 Document project date time output format
e48abda Update framework glossary
5b03a9e Document generic controlled attribute apply write path
b4ce8be Implement generic controlled attribute apply write path
7ef6975 Document generic attribute apply write-path structure
```

## 4. Что уже сделано по max_head

### 4.1 Human decision / contract / policy

Были зафиксированы решения по max_head:

* canonical attribute: `12`
* aliases: `101,119,81`
* category scope: `11900213`
* canonical unit: meters
* accepted simple values normalize to decimal meters
* ranges / upper-bound / mixed ambiguous values remain unresolved

Unresolved values:

* всего `14`
* не применяются
* не попадают в SQL preview/apply-plan/apply

### 4.2 DB-readonly stages

Были реализованы и проверены standalone DB-readonly команды:

* attribute discovery;
* raw values inventory;
* normalization proposals;
* normalization review-chain;
* normalization review sample;
* SQL preview;
* apply-plan preview.

Все эти команды работали только read-only / console output.

SQL/apply не выполнялся на этих этапах.

### 4.3 Manual review gates

Закрыты ручные gates:

* sample review generated proposals;
* SQL preview review;
* apply-plan preview review.

Подтверждено:

* simple normalized values выглядят корректно;
* unresolved ranges / upper-bound / mixed values исключены;
* SQL preview корректен;
* apply-plan preview корректен;
* `UPDATE` только по `oc_product_attribute`;
* `INSERT` только canonical rows;
* `DELETE/ALTER/TRUNCATE/DROP/CREATE TABLE` отсутствуют;
* source alias rows не удаляются на Phase 1;
* SQL/apply по-прежнему запрещён до отдельного gate.

## 5. Prototype max_head apply уже был выполнен на controlled local dump

Hardcoded prototype:

* `framework-standardization/bin/db-controlled-apply-max-head.php`
* `framework-standardization/src/Apply/DbControlledMaxHeadApplyCommand.php`

Результат prototype controlled local apply:

* `actual_updated_count: 400`
* `actual_inserted_count: 81`
* `sql_applied: 1`
* `product_data_changed: 1`
* `post_apply_verification_ok: 1`
* production/cache не трогались

Idempotency follow-up на already-applied local dump:

* `actual_updated_count: 0`
* `actual_inserted_count: 0`
* `already_applied_count: 562`
* `sql_applied: 0`
* `product_data_changed: 0`
* `post_apply_verification_ok: 1`

Важно: это был hardcoded prototype, не generic path.

## 6. Alias cleanup по max_head уже был выполнен на controlled local dump

Hardcoded alias cleanup:

* удалено `81` safely removable alias rows;
* осталось `14` unresolved/excluded alias rows;
* source unresolved rows намеренно сохранены;
* canonical rows не удалялись;
* production/cache не трогались.

Generic alias cleanup через contract также был реализован как dry-run / diagnostic path и подтвердил current already-cleaned state:

* `planned_delete_count: 0`
* `remaining_alias_rows: 14`
* `remaining_not_removable_rows: 14`
* `post_cleanup_verification_ok: 1`
* `sql_applied: 0`

## 7. Generic canonical apply diagnostic dry-run

Generic command/class:

* `framework-standardization/bin/db-controlled-attribute-apply.php`
* `framework-standardization/src/Apply/DbControlledAttributeApplyCommand.php`

На already-cleaned local dump diagnostic dry-run показывает:

```text
update_existing_canonical_row_count: 0
insert_missing_canonical_row_count: 0
already_applied_count: 0
source_based_already_applied_count: 0
canonical_only_verified_count: 481
source_based_plan_available: 0
dry_run_limitation: canonical apply dry-run after alias cleanup has limited source rows
unresolved_excluded_count: 14
duplicate_or_conflict_count: 0
expected_counts_match: 0
post_apply_verification_ok: 0
sql_applied: 0
product_data_changed: 0
```

Это ожидаемо, потому что local dump уже after alias cleanup: source alias rows для 481 applied canonical values удалены, поэтому source-based proof невозможен на текущем dump.

Нельзя искусственно делать `expected_counts_match: 1` для этого состояния.

## 8. Synthetic fixture для source-based generic apply

Создан fixture-only dry-run:

* `framework-standardization/bin/fixture-canonical-apply-dry-run.php`
* `framework-standardization/src/Fixture/GenericCanonicalApplyFixtureDryRun.php`
* `framework-standardization/config/attribute-contracts/fixtures/max_head_synthetic_fixture.php`
* `framework-standardization/fixtures/generic-canonical-apply/max_head_synthetic_rows.php`

Fixture подтверждает source-based plan logic без DB:

```text
update_existing_canonical_row_count: 1
insert_missing_canonical_row_count: 1
already_applied_count: 1
unresolved_excluded_count: 1
duplicate_or_conflict_count: 2
out_of_scope_ignored_count: 1
source_based_plan_available: 1
expected_counts_match: 1
dry_run_expected_counts_ok: 1
post_apply_verification_ok: 0
sql_applied: 0
product_data_changed: 0
```

`--confirm-apply` в fixture запрещён:

```text
fixture_canonical_apply_dry_run_error: fixture_confirm_apply_not_allowed
```

## 9. Generic write-path plan / structure / implementation

### 9.1 План

Документирован plan:

`9572f0e Document generic canonical write-path implementation plan`

Файл:

`framework-standardization/docs/GENERIC_CANONICAL_WRITE_PATH_IMPLEMENTATION_PLAN.md`

Он фиксирует:

* future generic UPDATE/INSERT write-path;
* contract-driven behavior;
* transaction/rollback/verification requirements;
* SQL boundaries;
* no production/cache;
* no SQL/apply без separate gate.

### 9.2 Structure

Коммит:

`50fe53b Add generic attribute apply write-path structure`

Добавлено:

* implementation-only structure;
* output markers:

  * `write_path_structure_present`
  * `confirm_apply_enabled`
  * `write_path_execution_enabled`
  * `implementation_only`

Runtime check:

`7ef6975 Document generic attribute apply write-path structure`

### 9.3 Real generic controlled write-path

Коммит:

`b4ce8be Implement generic controlled attribute apply write path`

Изменён файл:

`framework-standardization/src/Apply/DbControlledAttributeApplyCommand.php`

Реализовано:

* real generic controlled canonical apply write-path через explicit contract;
* bounded UPDATE existing canonical rows;
* bounded INSERT missing canonical rows;
* transaction;
* rollback on verification mismatch;
* rollback on exception;
* post-apply verification before commit;
* hard fail / non-zero behavior через exception;
* rollback в catch только если transaction была начата этой command.

Runtime check:

`5b03a9e Document generic controlled attribute apply write path`

## 10. Generic controlled write-path details

Generic write-path contract-driven.

Параметры берутся из contract:

* `category_scope_id`
* `canonical_attribute_id`
* `alias_attribute_ids`
* `allowed_table`
* `allowed_columns`
* expected counts
* `runtime_allowlist`
* `confirmation_required`
* `allow_confirm_apply`
* `normalizer_key`

Hardcoded max_head behavior в generic write-path не добавлялся.

Confirm path gated через checks:

* runtime должен соответствовать controlled local dump;
* contract должен требовать confirmation;
* runtime allowlist должен разрешать `allow_confirm_apply`;
* `production_ready` disabled;
* `cache_rebuild_allowed` disabled;
* source-based plan available;
* expected counts match;
* affected rows canonical-only;
* affected products inside category scope;
* unresolved values excluded.

Разрешённые write operations внутри confirm path:

* `UPDATE oc_product_attribute`
* `INSERT INTO oc_product_attribute`

UPDATE ограничен:

* concrete `product_id`;
* concrete `attribute_id`;
* concrete `language_id`.

INSERT пишет:

* concrete `product_id`;
* canonical `attribute_id`;
* concrete `language_id`;
* normalized `text`.

Запрещено и не добавлено:

* DELETE;
* ALTER;
* TRUNCATE;
* DROP;
* CREATE TABLE;
* writes в `oc_attribute`;
* writes в `oc_attribute_description`;
* source alias row changes;
* canonical row delete;
* cache rebuild;
* production/cache actions.

## 11. Важный safety review по generic write-path

Перед коммитом `b4ce8be` diff был несколько раз проверен и исправлен.

Ключевые исправления:

1. Post-apply verification теперь перечитывает planned canonical rows из DB и сверяет:

   * `product_id`;
   * `attribute_id`;
   * `language_id`;
   * `text`.

2. Если `transaction_already_active` или `transaction_not_available`, confirm apply падает hard fail.

3. В `catch` rollback делается только если transaction была начата этой command:

   * `$transactionStarted === 1`.

4. Если `post_apply_verification_failed`, после rollback бросается `RuntimeException`.

5. Confirm apply должен завершаться non-zero при exception / verification failure.

6. Dry-run без `--confirm-apply` не входит в write path.

## 12. Последние проверки после implementation

Syntax checks:

```text
No syntax errors detected in framework-standardization\src\Apply\DbControlledAttributeApplyCommand.php
No syntax errors detected in framework-standardization\bin\db-controlled-attribute-apply.php
```

DB dry-run без `--confirm-apply`:

```text
transaction_started: 0
sql_applied: 0
product_data_changed: 0
source_based_plan_available: 0
canonical_only_verified_count: 481
post_apply_verification_ok: 0
```

Fixture dry-run:

```text
source_based_plan_available: 1
expected_counts_match: 1
dry_run_expected_counts_ok: 1
sql_applied: 0
product_data_changed: 0
```

Safety search:

* UPDATE / INSERT только в confirm write-path;
* DELETE / ALTER / TRUNCATE / DROP / CREATE TABLE не добавлены;
* `oc_attribute` / `oc_attribute_description` не используются как write target;
* cache rebuild не добавлен;
* production/cache actions не добавлены;
* UPDATE содержит `WHERE product_id + attribute_id + language_id`;
* INSERT пишет только canonical attribute row;
* source alias rows не меняются.

## 13. Что НЕ сделано

Важно:

* generic `--confirm-apply` ещё НЕ запускался;
* generic SQL/apply ещё НЕ выполнялся;
* generic UPDATE/INSERT ещё НЕ применялись;
* product data generic path не менял;
* production/cache не трогались;
* cache rebuild не выполнялся.

Текущий шаг не является production readiness.

## 14. Следующий gate

Следующий gate должен быть отдельным и явным:

```text
controlled local generic --confirm-apply decision
```

Перед запуском apply нужно сделать preflight.

Минимальный preflight перед любым `--confirm-apply`:

1. Проверить clean working tree:

```powershell
git status --short && git log --oneline --decorate -8
```

2. Проверить syntax:

```powershell
C:\php56\php.exe -l framework-standardization\src\Apply\DbControlledAttributeApplyCommand.php && C:\php56\php.exe -l framework-standardization\bin\db-controlled-attribute-apply.php
```

3. DB dry-run без `--confirm-apply`:

```powershell
C:\php56\php.exe framework-standardization\bin\db-controlled-attribute-apply.php framework-standardization\config\runtime\local.dump.php framework-standardization\config\attribute-contracts\max_head_11900213.php
```

Ожидаемо на current already-cleaned dump:

```text
source_based_plan_available: 0
canonical_only_verified_count: 481
expected_counts_match: 0
post_apply_verification_ok: 0
sql_applied: 0
product_data_changed: 0
```

Это означает, что current local dump уже после alias cleanup и не подходит для proving source-based generic apply.

4. Fixture dry-run:

```powershell
C:\php56\php.exe framework-standardization\bin\fixture-canonical-apply-dry-run.php framework-standardization\config\attribute-contracts\fixtures\max_head_synthetic_fixture.php framework-standardization\fixtures\generic-canonical-apply\max_head_synthetic_rows.php
```

Ожидаемо:

```text
source_based_plan_available: 1
expected_counts_match: 1
dry_run_expected_counts_ok: 1
sql_applied: 0
product_data_changed: 0
```

## 15. Важное предупреждение про current local dump

Текущий controlled local dump уже находится после:

1. hardcoded prototype canonical apply;
2. hardcoded alias cleanup.

Поэтому generic canonical apply на этом dump в dry-run показывает:

* `source_based_plan_available: 0`;
* `expected_counts_match: 0`;
* `post_apply_verification_ok: 0`.

Это нормальное diagnostic state, но такой dump не подходит для реального generic source-based apply, потому что source alias rows для 481 applied canonical values уже удалены.

Для реального controlled generic `--confirm-apply` нужен либо:

* pre-alias-cleanup dump;
* либо другой controlled fixture / local dump state, где source-based plan available.

Нельзя запускать `--confirm-apply` на current already-cleaned dump, ожидая 400 UPDATE / 81 INSERT.

Команда должна быть gated и, вероятно, упадёт из-за:

```text
source_based_plan_required_for_confirm_apply
```

или

```text
expected_counts_mismatch
```

Это ожидаемо и безопасно, но запуск без смысла.

## 16. Рекомендуемый следующий шаг в новом чате

Не начинать сразу с apply.

Рекомендуемый bounded step:

```text
preflight / decision: определить подходящее состояние controlled local dump для generic source-based apply
```

Задачи:

1. Проверить, есть ли pre-cleanup dump / backup before alias cleanup.
2. Если есть — подключать его как controlled local runtime config.
3. Если нет — не запускать generic confirm apply на current dump.
4. Рассмотреть отдельный fixture-backed controlled integration test или восстановление local dump до состояния before Phase 1/Phase 2.
5. Только после этого принимать explicit decision на `--confirm-apply`.

## 17. Начальный prompt для нового чата

```text
Подключись к GitHub repo `saitkharkov-cyber/home-energetika`.

Рабочий проект: `framework-standardization`.

Сначала прочитай `framework-standardization/docs/START_HERE.md`.

Дальше следуй порядку чтения документов, указанному в `START_HERE.md`.

Актуальные stable point, текущий target, статус проекта, paused/rejected path, runtime checks, decisions и следующий рекомендуемый bounded step бери из документов репозитория, а не из памяти и не из этого prompt.

После чтения документов подтверди:

1. текущую stable point;
2. актуальную workflow-модель;
3. paused/rejected path;
4. текущий target и статус;
5. что уже реализовано;
6. что ещё не выполнялось;
7. следующий рекомендуемый маленький bounded step.

Пока не формулируй Codex prompt на implementation.

Сначала только подтверди понимание проекта и предложи один следующий маленький bounded step.
```
