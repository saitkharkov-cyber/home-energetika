# DB-readonly Prepared Fixture Expansion Spec

## Purpose

Цель этого документа — описать controlled expansion prepared fixture для context `pump_diameter`.

Будущий шаг должен расширить prepared fixture:

```text
current prepared rows count = 4
future prepared rows count > 4 and <= 12
```

Expansion нужен только для локального controlled readonly review-chain usage.

Он должен сохранить standalone readonly boundary и не должен переходить к:

- full category batch;
- pipeline/runners;
- live DB;
- SQL/apply;
- production output.

## Current Baseline

Текущий fixture provider:

```text
DbReadOnlyFirstRealDataUsageInputFixture
```

Текущий context:

```text
pump_diameter
```

Текущий prepared fixture:

```text
prepared rows count = 4
```

Expanded batch command уже успешно прошёл:

- `input_rows_count = 4`;
- `used = 1`;
- `review_ready = 1`;
- `e2e_checked = 1`;
- `errors_count = 0`;
- SQL/apply markers are all `0`;
- runtime JSON после command не остался;
- SQL/apply artifacts не создавались.

Current safe chain:

```text
DbReadOnlyFirstRealDataUsageInputFixture
-> db-readonly-real-data-usage-review-batch-check.php
-> DbReadOnlyRealDataReviewChainUsageChecker
-> standalone review-chain E2E checker
```

## Proposed Expansion

Будущий controlled expansion может увеличить prepared fixture до:

```text
prepared rows count > 4
prepared rows count <= 12
```

Rows должны оставаться:

- local;
- readonly;
- dump-derived/test-like;
- controlled;
- compatible with `DbReadOnlyRealDataReviewChainUsageChecker`.

Row format сохраняется:

```text
product_id
attribute_id
attribute_name
raw_value
normalized_value
confidence
```

Expansion не должен добавлять:

- external file reading;
- CLI input;
- filenames/paths/URLs;
- DB access;
- live DB;
- production DB;
- SQL/apply semantics.

Prepared fixture expansion должен быть изменением local fixture data only, а не новым runtime data source.

## Completion Criteria For Future Implementation

Будущая implementation считается успешной, если:

- prepared rows count `> 4`;
- prepared rows count `<= 12`;
- expanded batch command can process prepared fixture rows in controlled mode;
- `used = 1`;
- `review_ready = 1`;
- `e2e_checked = 1`;
- `errors_count = 0`;
- all SQL/apply markers are `0`;
- runtime fixture JSON не остаётся после check;
- SQL/apply artifacts не создаются;
- default dry-run remains ok;
- DB-readonly runner remains ok.

Required non-apply markers:

```text
sql_generated = 0
apply_plan_created = 0
safe_to_apply = 0
sql_apply_allowed = 0
production_ready = 0
```

Expected regression status:

- default dry-run: `result_status: ok`, `warnings_count: 0`, `errors_count: 0`, all 9 stages ok;
- DB-readonly runner: `result_status: ok`, `warnings_count: 0`, `errors_count: 0`, all 9 stages ok.

## Explicit Prohibitions

Для prepared fixture expansion запрещено:

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

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

## Implementation Boundary

Implementation, если будет подтверждена, должна менять только:

- fixture provider;
- standalone manual command boundary only if needed.

Implementation не должна менять:

- pipeline;
- runners;
- jobs;
- config;
- SQL preview;
- report;
- framework result;
- default dry-run path.

Implementation не должна добавлять:

- persistent runner;
- live DB access;
- external file source;
- CLI arbitrary input;
- production output;
- SQL/apply artifacts.

## Recommendation

Рекомендация:

1. После этого spec зафиксировать architecture decision.
2. Implementation делать только после explicit `+`.
3. Если implementation будет подтверждена, менять только fixture provider и, если нужно, standalone manual command boundary.
4. Не добавлять постоянный runner.
5. Не подключать к pipeline.
6. Не переходить к full category batch.

Первый implementation step должен быть маленьким и проверяемым:

- увеличить prepared fixture rows above `4`;
- keep rows count `<= 12`;
- run controlled expanded check;
- confirm cleanup and regressions.

## Out Of Scope

Вне scope этого документа:

- PHP implementation;
- fixture provider changes;
- command changes;
- runner integration;
- pipeline wiring;
- live DB;
- production DB;
- full category batch;
- arbitrary input handling;
- external file reading;
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

Prepared fixture expansion may increase:

```text
prepared rows count = 4
```

to:

```text
prepared rows count > 4
prepared rows count <= 12
```

Only within the existing controlled local readonly fixture boundary for `pump_diameter`.

It must not become full category batch, pipeline/runner integration, live DB usage, SQL preview, SQL/apply or production output.
