# DB-readonly Real-data Usage Review Batch Expansion Spec

## Purpose

Цель этого документа — описать controlled expansion первого successful manual command check.

Текущий first actual run использует slice:

```text
input_rows_count <= 2
```

Будущий осторожный шаг может расширить этот check до малого standalone review batch.

Это расширение должно:

- перейти от first-run slice `<= 2 rows` к малому controlled review batch;
- не запускать full category batch;
- не подключать pipeline/runners;
- не делать SQL/apply;
- не использовать live DB;
- оставаться standalone/manual и readonly.

## Current Baseline

Текущий закрытый baseline:

- fixture provider имеет context `pump_diameter`;
- prepared fixture содержит `4` controlled rows;
- manual command использует `getFirstRunSlice(2)`;
- command успешно вернул `used = 1`;
- command успешно вернул `review_ready = 1`;
- command успешно вернул `e2e_checked = 1`;
- command успешно вернул `input_rows_count = 2`;
- runtime JSON после command не остался;
- SQL/apply artifacts не создавались.

Связанные компоненты:

```text
DbReadOnlyFirstRealDataUsageInputFixture
-> db-readonly-first-real-data-usage-check.php
-> DbReadOnlyRealDataReviewChainUsageChecker
-> standalone review-chain E2E checker
```

## Proposed Expansion Boundary

Будущий expansion должен быть минимальным и controlled.

Разрешённый будущий шаг:

- использовать prepared fixture rows в пределах max prepared rows;
- начать с `4 rows` текущего prepared fixture;
- не превышать documented prepared fixture max `<= 12 rows`;
- оставаться manual/standalone;
- не принимать CLI arbitrary input;
- не принимать filenames/paths/URLs;
- не использовать production data.

Первый expanded check должен быть малым:

```text
input_rows_count > 2
input_rows_count <= 12
```

Рекомендуемый first expanded batch:

```text
input_rows_count = 4
```

Expansion не должен менять смысл current command baseline: это всё ещё readonly diagnostics/review-chain check, а не production workflow.

## Expected Output

Ожидаемый future output:

- review-ready diagnostics;
- reporter summary;
- E2E diagnostics;
- concise command/check output;
- explicit non-apply markers.

Обязательные non-apply markers:

```text
sql_generated = 0
apply_plan_created = 0
safe_to_apply = 0
sql_apply_allowed = 0
production_ready = 0
```

Output не является:

- SQL preview input;
- apply-ready output;
- production normalization;
- production export;
- permission for SQL apply.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

## Completion Criteria

Будущий expanded check считается успешным, если:

- `input_rows_count > 2`;
- `input_rows_count <= 12`;
- `used = 1`;
- `review_ready = 1`;
- `e2e_checked = 1`;
- `errors_count = 0`;
- all SQL/apply markers are `0`;
- runtime fixture JSON не остаётся после check;
- SQL/apply artifacts не создаются;
- default dry-run regression remains ok;
- DB-readonly runner regression remains ok.

Expected regression status:

- default dry-run: `result_status: ok`, `warnings_count: 0`, `errors_count: 0`, all 9 stages ok;
- DB-readonly runner: `result_status: ok`, `warnings_count: 0`, `errors_count: 0`, all 9 stages ok.

## Explicit Prohibitions

Для controlled review batch expansion запрещено:

- pipeline wiring;
- runner integration;
- live DB / production DB;
- full category batch;
- arbitrary input;
- filenames/paths/URLs input;
- SQL preview;
- SQL generation/files/diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`;
- `UPDATE`;
- `DELETE`;
- `REPLACE`;
- `ALTER`;
- `DROP`;
- `TRUNCATE`;
- `CREATE`.

## Implementation Boundary

Implementation, если будет явно разрешена, должна быть minimal standalone manual command/check path.

Допустимые варианты future implementation:

- отдельный manual command для expanded fixture slice;
- explicit option внутри standalone manual command only if it does not introduce arbitrary input;
- small standalone check class only if needed for code clarity.

Любой implementation должен:

- не добавлять постоянный runner;
- не подключаться к pipeline;
- не принимать filenames/paths/URLs;
- не использовать live DB;
- не генерировать SQL/apply;
- не создавать production output;
- удалять или не оставлять runtime fixture JSON после successful check.

## Recommendation

Рекомендация:

1. После этого spec зафиксировать architecture decision.
2. Implementation делать только после explicit `+`.
3. Не добавлять постоянный runner.
4. Не подключать к pipeline.
5. Не переходить к full category batch.

Первый implementation step, если будет подтверждён, должен проверить только малый controlled review batch на prepared fixture rows, начиная с current `4 rows`.

## Out Of Scope

Вне scope этого документа:

- PHP implementation;
- command implementation;
- runner integration;
- pipeline wiring;
- live DB;
- production DB;
- full category batch;
- arbitrary input handling;
- SQL preview;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- изменение default dry-run path;
- изменение `docs/HANDOFF.md`;
- изменение `docs/DECISIONS.md`;
- изменение `docs/RUNTIME_CHECKS.md`.

## Summary

Controlled expansion может перейти от:

```text
input_rows_count = 2
```

к малому standalone review batch:

```text
input_rows_count = 4
input_rows_count <= 12
```

Только при сохранении readonly/manual/standalone границ и всех non-apply markers:

```text
sql_generated = 0
apply_plan_created = 0
safe_to_apply = 0
sql_apply_allowed = 0
production_ready = 0
```
