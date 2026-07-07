# DB-readonly First Real-data Usage Manual Command Spec

## Purpose

Цель этого документа — описать безопасный future manual entrypoint для первого controlled usage check.

Manual command должен связывать:

```text
DbReadOnlyFirstRealDataUsageInputFixture
-> DbReadOnlyRealDataReviewChainUsageChecker
```

Этот entrypoint нужен только для локальной ручной проверки first real-data-like readonly fixture.

Это не:

- pipeline wiring;
- runner integration;
- live DB workflow;
- production DB workflow;
- SQL preview;
- SQL/apply;
- production output.

## Proposed Future Command

Возможный future файл только как option:

```text
bin/db-readonly-first-real-data-usage-check.php
```

В этом шаге command/script не реализуется.

Future command должен оставаться standalone manual command и не должен становиться частью default dry-run path, pipeline или runners.

## Expected Flow

Ожидаемый future flow:

1. Instantiate `DbReadOnlyFirstRealDataUsageInputFixture`.
2. Call `getFirstRunSlice(2)`.
3. Pass result into `DbReadOnlyRealDataReviewChainUsageChecker->run($readonlyInput)`.
4. Print concise diagnostics for human review.

Минимальные diagnostics для вывода:

- `used`;
- `review_ready`;
- `input_rows_count`;
- `e2e_checked`;
- `sql_generated`;
- `apply_plan_created`;
- `safe_to_apply`;
- `sql_apply_allowed`;
- `production_ready`;
- `errors_count`;
- `warnings_count`.

Output должен быть diagnostic/reporting-only.

Output не является:

- SQL preview input;
- apply-ready output;
- production normalization;
- production export;
- permission for SQL apply.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

## Boundaries

Future command должен быть:

- standalone manual command only;
- local readonly usage check only;
- limited to first run slice `<= 2 rows`;
- based on `pump_diameter` controlled fixture context;
- diagnostic/reporting-only.

Запрещено:

- pipeline wiring;
- runner integration;
- live DB;
- production DB;
- full category batch;
- arbitrary input;
- filenames/paths/URLs input;
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

## Completion Criteria For Future Implementation

Future command implementation считается успешным только если:

- command exits ok only when `used = 1`;
- command exits ok only when `review_ready = 1`;
- `input_rows_count <= 2`;
- all SQL/apply markers are `0`;
- runtime fixture JSON does not remain after the check;
- SQL/apply artifacts are not created;
- default dry-run regression remains ok;
- DB-readonly runner regression remains ok.

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

## Safety Notes

Future command must not accept arbitrary external input.

Future command must not read filenames, paths, URLs, uploaded files, live DB data or production DB data.

Future command must not create persistent review artifacts unless they are already covered by the local ignored fixture boundary and removed or confirmed ignored after check.

Any runtime fixture JSON created indirectly by the underlying standalone E2E checker must not remain after successful check.

## Recommendation

Рекомендация:

1. После этого spec зафиксировать architecture decision для manual command boundary.
2. Implementation делать только после explicit `+`.
3. Не добавлять постоянный runner сейчас.
4. Не подключать command к pipeline.
5. Не менять default dry-run path.

Если implementation будет разрешён, он должен быть минимальным standalone manual command, который только вызывает existing fixture provider and usage checker and prints concise diagnostics.

## Out Of Scope

Вне scope этого документа:

- PHP implementation;
- actual command/script creation;
- persistent runner;
- pipeline wiring;
- runner integration;
- arbitrary input handling;
- live DB;
- production DB;
- full category batch;
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

Future manual command may provide a safe local entrypoint:

```text
DbReadOnlyFirstRealDataUsageInputFixture::getFirstRunSlice(2)
-> DbReadOnlyRealDataReviewChainUsageChecker::run($readonlyInput)
-> concise diagnostics
```

It must remain standalone, readonly and diagnostic-only.

It must not become pipeline wiring, runner integration, SQL preview, SQL/apply or production output.
