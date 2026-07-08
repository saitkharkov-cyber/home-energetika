# Apply readiness decision — максимальный напор, scope 11900213

Дата: 2026-07-09

## Контекст

Целевой смысл характеристики:

`максимальный напор`

Область категории:

`11900213`

Canonical attribute:

`12 — Максимальный напор`

Included aliases:

- `101 — Максимальный напор, м.вод.ст.`
- `119 — Максимальный напор, м`
- `81 — Max напор, м`

Canonical unit:

`m`

Связанные решения и проверки:

- `docs/HUMAN_DECISION_MAX_HEAD_SCOPE_11900213.md`
- `docs/MAX_HEAD_UNIT_CONTRACT_SCOPE_11900213.md`
- `docs/MAX_HEAD_RANGE_POLICY_SCOPE_11900213.md`
- `docs/HUMAN_REVIEW_MAX_HEAD_PROPOSALS_SCOPE_11900213.md`
- `docs/SQL_PREVIEW_PLAN_MAX_HEAD_SCOPE_11900213.md`
- `docs/RUNTIME_CHECKS.md`

Связанные команды:

- `framework-standardization/bin/db-readonly-normalization-proposals.php`
- `framework-standardization/bin/db-readonly-normalization-review-chain.php`
- `framework-standardization/bin/db-readonly-normalization-review-sample.php`
- `framework-standardization/bin/db-readonly-sql-preview.php`
- `framework-standardization/bin/db-readonly-apply-plan-preview.php`

## Что уже подтверждено

Подтверждено:

- generated proposals построены;
- review-chain построен;
- sample review выполнен;
- human review decision зафиксирован;
- SQL preview сгенерирован;
- SQL preview вручную просмотрен;
- apply-plan preview сгенерирован;
- apply-plan preview вручную просмотрен.

Ключевые counts:

- `400` будущих UPDATE existing canonical rows;
- `81` будущий INSERT missing canonical rows;
- `81` source alias rows сохраняются;
- `14` unresolved values исключены;
- `0` schema blockers;
- `0` conflicts.

## Apply readiness decision

Решение:

`apply-ready for local dump / staging-like controlled DB only`

Это означает:

- можно готовить следующий bounded step для apply-команды;
- apply-команда должна быть отдельной;
- apply-команда не должна работать автоматически;
- apply-команда должна требовать явный флаг подтверждения;
- production apply запрещён;
- cache rebuild запрещён;
- rollback/apply verification должны быть отдельными и явными.

## Разрешённый следующий gate

Следующий gate:

`bounded local/staging apply command`

Этот gate может готовить команду, которая применяет SQL только при явном подтверждении.

Разрешённая область:

- category_scope: `11900213`;
- canonical_attribute_id: `12`;
- included attribute_ids: `12`, `101`, `119`, `81`;
- canonical_unit: `m`;
- только review-approved simple proposals;
- только `400` UPDATE existing canonical rows;
- только `81` INSERT missing canonical rows;
- unresolved values исключены;
- source alias rows сохраняются.

## Обязательные условия перед apply

Перед любым apply должны быть выполнены условия:

- apply разрешён только на local dump / staging-like DB;
- production DB запрещена;
- runtime должен явно отличаться от production;
- должен быть backup или dump snapshot;
- команда должна требовать явный флаг, например `--confirm-apply`;
- без явного флага команда должна завершаться без изменений;
- команда должна напечатать summary до применения;
- команда должна напечатать safety markers после применения;
- команда должна иметь dry-run / preview режим по умолчанию.

## Что будущая apply-команда может делать

Будущая apply-команда может выполнять только:

- UPDATE `oc_product_attribute.text` для existing canonical rows;
- INSERT missing canonical rows в `oc_product_attribute`;
- только `attribute_id = 12`;
- только конкретные `product_id`;
- только конкретный `language_id`;
- только нормализованные numeric values;
- только строки из review-approved set.

## Что будущая apply-команда не должна делать

Запрещено:

- DELETE;
- ALTER;
- DROP;
- TRUNCATE;
- CREATE TABLE;
- wide UPDATE без `product_id`;
- UPDATE/INSERT вне category_scope `11900213`;
- UPDATE/INSERT для unresolved values;
- изменение source alias rows;
- auto-merge attributes;
- auto-canonical selection;
- production/cache changes;
- cache rebuild;
- apply без явного подтверждения.

## Source alias rows

Source alias rows должны сохраняться.

Alias rows:

- `101`
- `119`
- `81`

Они не должны удаляться, merge-иться или изменяться автоматически.

Будущий apply должен только добавить/обновить canonical row `attribute_id = 12`.

## Unresolved values

Unresolved values остаются unresolved.

Количество:

`14`

Примеры:

- `100-104`
- `104–118`
- `50–51,5`
- `до 51 м`

Они не должны попадать в apply.

## Verification после apply

После apply должен быть отдельный verification step.

Минимальная проверка должна подтвердить:

- updated canonical rows count соответствует ожидаемому count;
- inserted canonical rows count соответствует ожидаемому count;
- affected rows имеют `attribute_id = 12`;
- affected products входят в category_scope `11900213`;
- unresolved values не были применены;
- source alias rows сохранены;
- нет новых conflicts/duplicates;
- product data изменились только в ожидаемых строках.

## Rollback

Rollback не разрешается автоматически.

Rollback требует отдельного gate.

Перед реальным apply нужен backup или dump snapshot.

Rollback SQL не создаётся этим документом.

## Production/cache

Production запрещён.

Cache rebuild запрещён.

Любые production/cache действия должны быть отдельным gate после успешного local/staging apply и verification.

## Safety clarification

Этот документ не выполняет apply.

Он не:

- создаёт apply-команду;
- выполняет SQL;
- создаёт SQL files/diff;
- меняет product data;
- трогает production/cache;
- разрешает cache rebuild.

Этот документ только фиксирует готовность перейти к разработке bounded apply-команды для controlled DB.

## Result

Зафиксировано:

- apply readiness подтверждён только для local dump / staging-like controlled DB;
- production apply запрещён;
- SQL apply ещё не выполнялся;
- следующий gate — bounded apply command with explicit confirmation.