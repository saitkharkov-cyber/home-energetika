# Spec: generic controlled attribute workflow engine

Дата: 2026-07-09

## 1. Почему нужен generic engine

Max-head prototype успешно доказал safety model полного controlled workflow на controlled local dump:

- Phase 1 canonical value apply для `canonical_attribute_id = 12`;
- Phase 2 alias cleanup для aliases `101/119/81`;
- transactional apply;
- verification before commit;
- idempotent repeat run;
- no production/cache;
- no cache rebuild.

Но текущие команды hardcoded под одну характеристику:

- `framework-standardization/bin/db-controlled-apply-max-head.php`;
- `FrameworkStandardization\Apply\DbControlledMaxHeadApplyCommand`;
- `framework-standardization/bin/db-readonly-alias-cleanup-preview-max-head.php`;
- `FrameworkStandardization\Preview\DbReadOnlyAliasCleanupPreviewMaxHead`;
- `framework-standardization/bin/db-controlled-alias-cleanup-max-head.php`;
- `FrameworkStandardization\Apply\DbControlledAliasCleanupMaxHeadCommand`.

Их нельзя копировать под каждую новую характеристику. Также нельзя расширять max-head hardcode на real local DB как основной путь. Следующий архитектурный шаг должен быть generic engine + explicit contract/input.

## 2. Prototype/reference status

Max-head команды и классы остаются prototype/reference:

- `db-controlled-apply-max-head.php`;
- `DbControlledMaxHeadApplyCommand`;
- `db-readonly-alias-cleanup-preview-max-head.php`;
- `DbReadOnlyAliasCleanupPreviewMaxHead`;
- `db-controlled-alias-cleanup-max-head.php`;
- `DbControlledAliasCleanupMaxHeadCommand`.

В первом generic implementation step их не удалять, не переписывать и не превращать в wrapper. Они остаются проверенным reference для safety behavior, counts, transaction и verification semantics.

## 3. Target architecture

Целевые generic commands:

- `framework-standardization/bin/db-controlled-attribute-apply.php`;
- `framework-standardization/bin/db-readonly-alias-cleanup-preview.php`;
- `framework-standardization/bin/db-controlled-alias-cleanup.php`.

Целевые generic classes:

- `FrameworkStandardization\Apply\DbControlledAttributeApplyCommand`;
- `FrameworkStandardization\Preview\DbReadOnlyAliasCleanupPreview`;
- `FrameworkStandardization\Apply\DbControlledAliasCleanupCommand`.

Characteristic-specific data должны приходить из explicit contract/input, а не из hardcoded class fields.

Target architecture должна включать:

- core engine classes;
- contract/config;
- normalizer registry;
- CLI adapters;
- Web admin adapter;
- audit/runtime checks.

Первый expected contract path для max head:

`framework-standardization/config/attribute-contracts/max_head_11900213.php`

В этом doc-step contract file не создаётся и PHP не пишется.

## 4. Web admin adapter для shared hosting / production

Production находится на shared hosting, где CLI может быть недоступен. Поэтому generic architecture должна быть transport-agnostic и предусматривать future Web admin adapter.

Core engine должен быть transport-agnostic:

- business logic не должна жить в CLI-файлах;
- CLI и Web должны вызывать один и тот же service/engine;
- CLI является adapter для локалки/Codекса;
- Web является adapter для shared hosting / production.

Future web interface:

- отдельный защищённый web endpoint/admin page внутри OpenCart admin;
- не public route;
- не web-accessible standalone script без защиты.

Web interface должен поддерживать:

- readonly preview;
- dry-run plan;
- explicit confirm apply;
- post-apply verification;
- result summary.

Web safety:

- только авторизованный admin;
- CSRF token;
- feature flag / secret;
- disabled by default;
- allowlist runtime/environment;
- no GET apply;
- apply только POST;
- двухшаговое подтверждение: preview -> confirm screen -> apply;
- показать expected counts перед confirm;
- typed confirmation, например `APPLY MAX_HEAD 11900213`;
- transaction;
- verification before commit;
- rollback on mismatch/error;
- audit log / downloadable summary;
- no cache rebuild by default;
- production/cache gate отдельно.

Shared hosting constraints:

- нельзя полагаться на long-running CLI;
- операции должны быть bounded;
- если операция может быть долгой, нужен chunked plan/apply или staged batches;
- не хранить sensitive DB credentials в web-accessible path;
- не выводить secrets в UI;
- учитывать PHP `max_execution_time`;
- timeout-safe behavior.

Deployment model:

- generic engine сначала тестируется через CLI на local dump;
- затем тот же engine подключается к Web admin adapter;
- production/shared-hosting запуск только после отдельного production gate;
- Web adapter не должен bypass safety checks engine-а.

## 5. Contract model

Минимальный contract должен описывать:

- `target_key`;
- `target_meaning`;
- `category_scope_id`;
- `canonical_attribute_id`;
- `alias_attribute_ids`;
- `canonical_unit`;
- `normalizer_key`;
- `source_alias_policy`;
- `unresolved_policy`;
- `expected_canonical_update_count` или expected canonical plan constraints;
- `expected_canonical_insert_count` или expected canonical plan constraints;
- `expected_alias_delete_count`;
- `expected_remaining_alias_rows`;
- `allowed_table`;
- `allowed_columns`;
- `allowed_operations`;
- `runtime_allowlist`;
- `confirmation_required`.

Для `max_head_11900213` contract должен содержать:

- `target_key: max_head`;
- `target_meaning: максимальный напор`;
- `category_scope_id: 11900213`;
- `canonical_attribute_id: 12`;
- `alias_attribute_ids: [101, 119, 81]`;
- `canonical_unit: m`;
- `normalizer_key: simple_meters`;
- `expected_alias_delete_count: 81`;
- `expected_remaining_alias_rows: 14`.

Contract является результатом human-gated workflow. Он не должен создаваться автоматическим guessing из `config/jobs`.

## 6. Runtime allowlist model

Generic engine не должен принимать любой runtime.

Нужно разделить:

- controlled local dump runtime;
- future real local DB runtime.

Runtime должен быть явно разрешён contract-ом или отдельным allowlist config. Для future real local DB обязательны:

- сначала readonly preview;
- затем explicit confirm only;
- backup как manual precondition;
- production/cache forbidden;
- no cache rebuild.

Production runtime должен оставаться blocked отдельным gate до отдельного production/cache decision.

## 7. Normalizer model

Normalization logic нужно вынести из max-head classes в registry/factory по ключу:

`simple_meters`

Первый generic normalizer должен быть только `simple_meters`. Не нужно сразу покрывать все типы характеристик.

Normalizer должен возвращать:

- `normalized_value`;
- unresolved/excluded reason;
- source/raw diagnostics, если они нужны для review или verification.

Normalizer не должен сам утверждать canonical unit, canonical identity, range policy или aliases. Эти решения приходят из approved contract.

## 8. Generic Phase 1: canonical value apply

Generic canonical value apply должен:

- принимать runtime config path;
- принимать contract path;
- работать в dry-run по умолчанию;
- выполнять real apply только с `--confirm-apply`;
- строить plan на основе contract и existing preview/review logic;
- выполнять UPDATE/INSERT только для `canonical_attribute_id`;
- сохранять alias rows;
- исключать unresolved values;
- использовать transaction;
- выполнять verification before commit;
- делать rollback on mismatch/error;
- поддерживать idempotent repeat run;
- запрещать production/cache;
- запрещать cache rebuild.

Для already-cleaned max-head local dump expected dry-run comparison:

- `update_existing_canonical_row_count: 0`;
- `insert_missing_canonical_row_count: 0`;
- `already_applied_count: 481`;
- `unresolved_excluded_count: 14`;
- `post_apply_verification_ok: 1`.

## 9. Requirement before generic canonical write-path

Текущий статус generic canonical apply:

- generic canonical apply command существует;
- command читает explicit contract;
- command строит diagnostic dry-run;
- `--confirm-apply` сейчас hard-stop;
- generic UPDATE/INSERT write-path ещё не реализован.

Нельзя сразу включать generic write-path на текущем local dump:

- текущий local dump уже находится after Phase 1 canonical apply и after Phase 2 alias cleanup;
- source alias rows для 481 already-applied canonical rows удалены;
- canonical-only verification не является source-based proof;
- нельзя доказывать generic canonical apply только на canonical rows;
- `expected_counts_match: 0` в diagnostic dry-run является честным ограничением текущего state, а не ошибкой.

Перед implementation/enable generic write-path нужен отдельный source-based dataset:

- pre-alias-cleanup dump;
- или controlled fixture;
- или другой воспроизводимый dataset, где source alias rows ещё существуют.

Dataset должен позволять получить source-based plan:

- `update_existing_canonical_row_count`;
- `insert_missing_canonical_row_count`;
- `already_applied_count`;
- `unresolved_excluded_count`;
- `duplicate_or_conflict_count`.

Dataset должен быть bounded по scope/category/contract, должен быть воспроизводимым и не должен трогать production/cache.

Минимальные acceptance criteria для future Step E/F:

- dry-run на pre-cleanup fixture показывает `source_based_plan_available: 1`;
- `expected_counts_match: 1` только если counts доказаны source-based;
- `--confirm-apply` запрещён до успешного source-based dry-run;
- real UPDATE/INSERT можно включать только после отдельного gate;
- source alias rows не меняются в Phase 1;
- alias cleanup остаётся Phase 2;
- rollback/transaction/verification обязательны для future confirm apply.

Explicit boundary:

- этот документ НЕ разрешает SQL/apply;
- этот документ НЕ разрешает `--confirm-apply`;
- этот документ НЕ разрешает production/cache;
- это только requirement перед переносом write-path из prototype в generic engine.

## 10. Generic Phase 2: alias cleanup

Generic alias cleanup preview должен:

- принимать runtime config path;
- принимать contract path;
- быть DB-readonly;
- считать total alias rows;
- считать safely removable rows;
- считать not removable rows;
- показывать reasons;
- показывать breakdown by `alias_attribute_id`.

Generic alias cleanup apply должен:

- работать в dry-run по умолчанию;
- выполнять real DELETE только с `--confirm-apply`;
- DELETE only from `oc_product_attribute`;
- DELETE only alias rows from computed plan;
- использовать exact row identity: `product_id + attribute_id + language_id + text`;
- не удалять canonical rows;
- не удалять unresolved/excluded rows;
- использовать transaction;
- выполнять verification before commit;
- делать rollback on mismatch/error;
- поддерживать idempotent repeat run;
- запрещать production/cache;
- запрещать cache rebuild.

Для already-cleaned max-head local dump expected alias cleanup preview:

- `total_alias_rows_in_scope: 14`;
- `safely_removable_alias_rows: 0`;
- `not_removable_alias_rows: 14`.

Для already-cleaned max-head local dump expected alias cleanup dry-run:

- `planned_delete_count: 0`;
- `remaining_alias_rows: 14`;
- `remaining_not_removable_rows: 14`;
- `post_cleanup_verification_ok: 1`.

## 11. Migration plan

### Step A: contract-only

Создать `max_head_11900213` contract.

Не менять prototype commands.

### Step B: generic alias cleanup preview first

Реализовать `db-readonly-alias-cleanup-preview.php` через contract.

Сравнить output с max-head prototype preview на already-cleaned local dump:

- `total_alias_rows_in_scope: 14`;
- `safely_removable_alias_rows: 0`;
- `not_removable_alias_rows: 14`.

### Step C: generic alias cleanup apply dry-run

Реализовать generic alias cleanup apply dry-run.

На already-cleaned local dump expected:

- `planned_delete_count: 0`;
- `remaining_alias_rows: 14`;
- `remaining_not_removable_rows: 14`;
- `sql_applied: 0`;
- `product_data_changed: 0`.

### Step D: generic canonical apply dry-run

Реализовать generic canonical value apply dry-run.

Сравнить с current max-head controlled apply dry-run:

- `update_existing_canonical_row_count: 0`;
- `insert_missing_canonical_row_count: 0`;
- `already_applied_count: 481`;
- `unresolved_excluded_count: 14`;
- `post_apply_verification_ok: 1`.

### Step E: future real local DB

Для future real local DB:

- создать explicit real local runtime allowlist;
- выполнить readonly previews;
- compare counts;
- проверить backup/manual precondition;
- запускать `--confirm-apply` только после review.

### Step F: web admin adapter

После проверки generic engine на local dump и real local DB:

- реализовать protected web UI для readonly preview/dry-run/confirm/apply;
- подключить Web admin adapter к тому же core engine;
- оставить production enable disabled by default;
- не обходить safety checks engine-а;
- не выполнять production/cache actions без отдельного production gate.

## 12. Human-gated decisions

Остаются human-gated:

- target meaning;
- canonical selection;
- alias include/exclude;
- unit contract;
- unresolved/range policy;
- review approval;
- final `--confirm-apply`;
- production/cache gate.

Generic engine должен исполнять approved contract. Он не должен сам выбирать canonical attribute, aliases, unit semantics, range policy или production/cache actions.

## 13. Что можно универсализировать

Можно универсализировать:

- discovery command;
- raw values inventory;
- proposals generation skeleton;
- review-chain;
- SQL preview;
- apply-plan preview;
- controlled apply;
- post-apply verification.

## 14. Что нельзя универсализировать без contract

Нельзя универсализировать без explicit contract:

- semantic parser decisions;
- unit meaning;
- range interpretation;
- ambiguous values;
- canonical identity;
- production/cache decisions.

## 15. Boundaries

Этот spec:

- не удаляет prototype max-head commands;
- не меняет production/cache;
- не делает cache rebuild;
- не делает SQL/apply;
- не запускает `--confirm-apply`;
- не создаёт contract/PHP в этом шаге;
- не создаёт web route;
- не меняет admin;
- не меняет `HANDOFF.md`;
- не меняет `RUNTIME_CHECKS.md`;
- не меняет `DECISIONS.md`.

## 16. Что этот документ НЕ делает

Этот документ:

- не реализует generic engine;
- не создаёт contract file;
- не меняет PHP/code;
- не создаёт Web admin adapter;
- не создаёт web endpoint/admin page;
- не меняет `bin/`;
- не меняет `src/`;
- не меняет `config/`;
- не меняет runtime configs;
- не запускает SQL;
- не применяет изменения на controlled local dump;
- не применяет изменения на real local DB;
- не трогает production/cache;
- не делает cache rebuild.
