# Generic canonical write-path implementation plan

Дата: 2026-07-09

## 1. Purpose

Этот документ фиксирует bounded plan для переноса canonical UPDATE/INSERT write-path из max_head prototype в generic engine.

Цель будущего переноса — использовать уже доказанную safety model `DbControlledMaxHeadApplyCommand`, но убрать hardcoded max_head assumptions и перевести write-path на explicit contract.

Этот документ не реализует PHP write-path и не разрешает SQL/apply.

## 2. Current status

Текущий статус:

- max_head prototype уже имел real UPDATE/INSERT и был применён на controlled local dump;
- generic canonical apply command сейчас является diagnostic dry-run;
- generic source-based plan logic проверена на synthetic fixture;
- generic UPDATE/INSERT write-path ещё не реализован;
- `--confirm-apply` в generic canonical apply сейчас hard-stop.

Связанные файлы:

- prototype command: `framework-standardization/bin/db-controlled-apply-max-head.php`;
- prototype class: `framework-standardization/src/Apply/DbControlledMaxHeadApplyCommand.php`;
- generic command: `framework-standardization/bin/db-controlled-attribute-apply.php`;
- generic class: `framework-standardization/src/Apply/DbControlledAttributeApplyCommand.php`;
- fixture dry-run command: `framework-standardization/bin/fixture-canonical-apply-dry-run.php`;
- fixture dry-run runner: `framework-standardization/src/Fixture/GenericCanonicalApplyFixtureDryRun.php`.

## 3. Preconditions before implementation

Перед реализацией write-path должны быть соблюдены:

- working tree clean;
- fixture dry-run зелёный:
  - `source_based_plan_available: 1`;
  - `dry_run_expected_counts_ok: 1`;
  - `sql_applied: 0`;
  - `product_data_changed: 0`;
- production/cache не используется;
- runtime allowlist и contract validation уже есть;
- human gate на implementation write-path получен.

Если хотя бы одно precondition не выполнено, generic write-path implementation нельзя начинать.

## 4. Write-path scope

Generic write-path должен поддерживать только Phase 1 canonical value apply:

- UPDATE existing canonical row;
- INSERT missing canonical row;
- source alias rows не менять;
- alias cleanup не выполнять;
- canonical rows не удалять;
- `oc_attribute` / `oc_attribute_description` не менять;
- production/cache не трогать;
- cache rebuild не выполнять.

Alias cleanup остаётся отдельной Phase 2 и не должна попадать в Phase 1 write-path.

## 5. Contract-driven behavior

Все параметры должны идти из explicit contract:

- `category_scope_id`;
- `canonical_attribute_id`;
- `alias_attribute_ids`;
- `allowed_table`;
- allowed columns;
- expected counts;
- runtime allowlist;
- `confirmation_required`;
- `allow_confirm_apply`;
- `normalizer_key`.

Запрещено hardcode max_head в generic write-path.

Generic class не должен использовать hardcoded:

- target meaning;
- category scope;
- canonical attribute;
- alias attributes;
- canonical unit;
- expected counts.

## 6. Confirm apply gate

Даже после implementation:

- `--confirm-apply` должен оставаться запрещён до отдельного gate;
- для первого implementation можно оставить confirm path disabled;
- если confirm path включается позже, apply class должен сам проверять:
  - `confirmation_required`;
  - runtime allowlist `allow_confirm_apply`;
  - `production_ready === false` для local-only;
  - `cache_rebuild_allowed === false`.

CLI validation недостаточна. Core class тоже должен проверять contract gates.

Если gate на confirm apply ещё не выдан, generic command должен продолжать hard-stop для `--confirm-apply`.

## 7. Transaction and rollback requirements

Будущий real write-path должен:

- начинать transaction до UPDATE/INSERT;
- выполнять только planned rows;
- проверять `actual_updated_count` / `actual_inserted_count`;
- выполнять post-apply verification до commit;
- commit только если verification ok;
- rollback на mismatch/error;
- быть idempotent при repeat run;
- не менять source alias rows.

Transaction markers должны быть явно напечатаны в summary:

- `transaction_started`;
- `transaction_committed`;
- `transaction_rolled_back`;
- `rollback_reason`.

## 8. SQL boundaries

Разрешённые SQL операции только в будущей confirm path:

- UPDATE только `oc_product_attribute` canonical row;
- INSERT только `oc_product_attribute` canonical row.

Запрещены:

- DELETE;
- ALTER;
- TRUNCATE;
- UPDATE/DELETE `oc_attribute`;
- UPDATE/DELETE `oc_attribute_description`;
- любые production/cache actions.

UPDATE/INSERT должны быть row-bounded:

- concrete `product_id`;
- concrete `language_id`;
- concrete `canonical_attribute_id`;
- normalized value из approved/source-based plan.

Широкий UPDATE без `product_id` запрещён.

## 9. Verification requirements

До включения `--confirm-apply` нужны проверки:

1. Syntax checks.
2. Fixture dry-run остаётся зелёным.
3. DB dry-run на controlled local dump не выполняет SQL.
4. Confirm path disabled/hard-stop, если gate ещё не выдан.
5. Grep/search показывает:
   - UPDATE/INSERT есть только в controlled confirm path, если path реализован;
   - нет DELETE/ALTER/TRUNCATE;
   - нет `oc_attribute` / `oc_attribute_description` writes;
   - нет cache rebuild.
6. Runtime check doc-only после реализации.

Проверки должны подтверждать, что implementation-only step не выполнил SQL/apply.

## 10. Acceptance criteria for implementation-only step

Для будущего implementation-only step accept:

- generic class содержит write-path code или prepared structure;
- dry-run output не меняет current behavior;
- `--confirm-apply` всё ещё hard-stop, если gate не выдан;
- fixture dry-run зелёный;
- DB dry-run безопасный;
- SQL/apply не выполнялись;
- production/cache не трогались.

Если implementation меняет dry-run semantics, это должно быть зафиксировано отдельно и не должно включать apply.

## 11. Non-goals

Документ НЕ делает:

- не реализует PHP write-path;
- не разрешает SQL/apply;
- не разрешает `--confirm-apply`;
- не разрешает production/cache;
- не меняет contract;
- не меняет runtime configs;
- не заменяет human decision.

Этот документ не является apply readiness decision.

## 12. Next bounded step

Следующий bounded step:

- implementation-only перенос write-path structure в generic class;
- confirm path оставить hard-stop;
- выполнить fixture dry-run и DB dry-run;
- не запускать `--confirm-apply`;
- не выполнять SQL/apply.

## 13. Boundaries

Этот шаг:

- doc-only update;
- не меняет PHP/code;
- не меняет fixture files;
- не меняет existing contract;
- не меняет runtime configs;
- не меняет `RUNTIME_CHECKS.md`;
- не меняет `HANDOFF.md`;
- не меняет `DECISIONS.md`;
- не выполняет SQL/apply;
- не запускает `--confirm-apply`;
- не трогает production/cache;
- не делает cache rebuild.
