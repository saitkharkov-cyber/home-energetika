# Generic canonical apply fixture spec

Дата: 2026-07-09

## 1. Purpose

Этот документ фиксирует requirement и план подготовки controlled source-based dataset для проверки generic Phase 1 canonical value apply.

Fixture/dataset нужен не для production и не для apply. Его назначение — честно проверить, что generic canonical apply строит source-based plan из alias/source rows по explicit contract до включения generic UPDATE/INSERT write-path.

Этот документ не разрешает SQL/apply и не заменяет human-gated decisions.

## 2. Problem

Текущий controlled local dump уже находится:

- after Phase 1 canonical apply;
- after Phase 2 alias cleanup.

Alias/source rows для 481 canonical rows уже удалены. Поэтому текущий state не позволяет честно доказать source-based generic canonical apply.

Generic diagnostic dry-run показывает:

- `source_based_plan_available: 0`;
- `canonical_only_verified_count: 481`;
- `expected_counts_match: 0`;
- `post_apply_verification_ok: 0`.

Это честное ограничение текущего state, а не ошибка команды. `canonical_only_verified_count` подтверждает наличие canonical-состояния, но не является source-based proof.

## 3. Required source-based state

Dataset должен содержать source alias rows для contract:

- `target_key: max_head`;
- `category_scope: 11900213`;
- `canonical_attribute_id: 12`;
- `alias_attribute_ids: 101, 119, 81`;
- `normalizer_key: simple_meters`.

Dataset должен позволять проверить source-based counts:

- `update_existing_canonical_row_count`;
- `insert_missing_canonical_row_count`;
- `already_applied_count`;
- `unresolved_excluded_count`;
- `duplicate_or_conflict_count`.

Counts должны выводиться из alias/source rows, а не из canonical-only verification.

## 4. Acceptable fixture options

### A. Pre-alias-cleanup dump

Состояние после Phase 1 canonical apply, но до Phase 2 alias cleanup.

Такой dump ожидаемо содержит canonical rows и всё ещё содержит removable alias rows. Он подходит для проверки source-based `already_applied_count`, потому что alias/source rows ещё доступны и canonical rows уже должны совпадать с normalized values.

### B. Pre-canonical-apply dump

Состояние до Phase 1 canonical apply.

Такой dump подходит для проверки update/insert plan. Он требует осторожности: update/insert counts должны совпадать с historical expected `400/81` или с другим explicit fixture contract, утверждённым отдельным gate.

### C. Synthetic controlled fixture

Маленький dataset только для нескольких `product_id` / `language_id` cases.

Такой fixture:

- не является production dump;
- должен быть воспроизводимым;
- должен покрывать update, insert, already_applied, unresolved, duplicate/conflict;
- должен быть bounded по scope/category/contract.

## 5. Minimal synthetic fixture cases

Минимальный synthetic fixture должен покрывать следующие cases без SQL реализации в этом документе.

### 1. Already applied case

- alias/source row нормализуется в значение `X`;
- canonical row уже есть и равен `X`;
- ожидаемо `already_applied_count +1`.

### 2. Update case

- alias/source row нормализуется в `X`;
- canonical row есть, но `text` отличается;
- ожидаемо `update_existing_canonical_row_count +1`.

### 3. Insert case

- alias/source row нормализуется в `X`;
- canonical row отсутствует;
- ожидаемо `insert_missing_canonical_row_count +1`.

### 4. Unresolved/excluded case

- alias/source row не нормализуется `simple_meters`;
- canonical write не планируется;
- ожидаемо `unresolved_excluded_count +1`.

### 5. Duplicate/conflict case

- duplicate exact row или несколько разных normalized values для одного `product_id` / `language_id`;
- canonical write не планируется;
- ожидаемо `duplicate_or_conflict_count +1`.

### 6. Out-of-scope guard

- product/category вне scope;
- row не должен попадать в plan.

## 6. Fixture safety boundaries

Fixture не должен:

- использовать production DB;
- выполнять cache rebuild;
- менять `oc_attribute` / `oc_attribute_description`;
- требовать изменения existing contract без отдельного gate;
- включать `--confirm-apply`;
- использоваться сначала иначе чем для dry-run.

Fixture должен оставаться controlled, bounded и воспроизводимым.

## 7. Acceptance criteria before generic write-path

Перед включением generic UPDATE/INSERT write-path нужно:

- иметь fixture/dump с `source_based_plan_available: 1`;
- получить `expected_counts_match: 1` на source-based dry-run;
- убедиться, что `canonical_only_verified_count` не используется как proof;
- убедиться, что source alias rows не меняются в Phase 1;
- убедиться, что alias cleanup остаётся Phase 2;
- только после этого отдельным gate разрешать implementation generic write-path;
- ещё отдельным gate разрешать `--confirm-apply`.

Rollback, transaction и verification обязательны для future confirm apply.

## 8. Non-goals

Этот документ НЕ делает:

- не создаёт fixture;
- не выполняет SQL;
- не включает UPDATE/INSERT/DELETE;
- не разрешает `--confirm-apply`;
- не разрешает production/cache;
- не заменяет human decision.

## 9. Next bounded step

Следующий bounded step:

- либо найти/подготовить pre-alias-cleanup dump;
- либо сделать small synthetic fixture spec/command dry-run-only;
- без SQL/apply;
- без `--confirm-apply`;
- без production/cache.

## 10. Boundaries

Этот шаг:

- doc-only update;
- не меняет PHP/code;
- не меняет existing contract;
- не меняет runtime configs;
- не меняет `RUNTIME_CHECKS.md`;
- не меняет `HANDOFF.md`;
- не меняет `DECISIONS.md`;
- не выполняет SQL/apply;
- не запускает `--confirm-apply`;
- не трогает production/cache;
- не делает cache rebuild.
