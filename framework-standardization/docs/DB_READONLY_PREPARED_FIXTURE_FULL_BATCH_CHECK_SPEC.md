# DB-readonly Prepared Fixture Full Batch Check Spec

## Purpose

This document defines a controlled future manual check for the full current prepared fixture set in the `pump_diameter` context.

The goal is to move from the existing expanded batch command that checks 4 rows to a standalone/manual/readonly check over all currently prepared rows, which is 8 rows after the prepared fixture expansion.

This boundary is only for validating that the prepared fixture can pass through the already existing standalone review-chain usage path.

It must not become:

- full category batch processing;
- pipeline wiring;
- runner integration;
- SQL preview;
- SQL/apply;
- live DB usage;
- production output.

## Current Baseline

Current fixture provider:

- `DbReadOnlyFirstRealDataUsageInputFixture`

Current baseline facts:

- context: `pump_diameter`;
- prepared fixture rows count: 8;
- `max_rows` remains 12;
- first manual command still uses `input_rows_count = 2`;
- expanded batch command still uses `input_rows_count = 4`;
- fixture expansion checks are documented in `docs/RUNTIME_CHECKS.md`;
- latest stable commit: `cd48e88 Document DB readonly prepared fixture expansion checks`.

The current state confirms:

- the 2-row first-run command remains successful;
- the 4-row expanded batch command remains successful;
- SQL/apply markers remain 0;
- runtime fixture JSON does not remain after checks.

## Proposed Future Manual Check

Future optional command:

- `bin/db-readonly-prepared-fixture-full-batch-check.php`

This command is not implemented by this spec.

Expected future flow:

1. Instantiate `DbReadOnlyFirstRealDataUsageInputFixture`.
2. Call `getPreparedFixture()`.
3. Take all prepared rows, currently 8.
4. Validate that `input_rows_count > 4`.
5. Validate that `input_rows_count <= 12`.
6. Pass rows into `DbReadOnlyRealDataReviewChainUsageChecker::run($readonlyInput)`.
7. Print concise plain-text diagnostics.

The diagnostics should include:

- `used`;
- `review_ready`;
- `input_rows_count`;
- `e2e_checked`;
- SQL/apply markers;
- `errors_count`;
- `warnings_count`.

## Expected Output

The future full prepared fixture check should produce only local command output and in-memory diagnostics.

Expected non-apply markers:

- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- `sql_apply_allowed = 0`;
- `production_ready = 0`.

It must not create:

- persisted report artifacts;
- SQL files;
- SQL diffs;
- apply plans;
- production output;
- committed runtime artifacts.

## Completion Criteria For Future Implementation

The future command is successful only if:

- `input_rows_count = 8` for the current fixture;
- `input_rows_count > 4`;
- `input_rows_count <= 12`;
- `used = 1`;
- `review_ready = 1`;
- `e2e_checked = 1`;
- `errors_count = 0`;
- all SQL/apply markers are 0;
- runtime fixture JSON does not remain after the command;
- SQL/apply artifacts are not created;
- default dry-run regression remains ok;
- DB-readonly runner regression remains ok.

## Explicit Prohibitions

The future full prepared fixture check must not do any of the following:

- pipeline wiring;
- runner integration;
- live DB usage;
- production DB usage;
- full category batch processing;
- arbitrary input;
- filenames, paths, or URLs as input;
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

## Recommendation

After this spec, record an architecture decision before any implementation.

Implementation is allowed only after an explicit `+`.

If implementation is confirmed, it should be a standalone manual command only:

- no persistent runner;
- no pipeline wiring;
- no arbitrary CLI input;
- no external file input;
- no DB access;
- no SQL/apply output.

## Out Of Scope

This spec does not implement:

- PHP command code;
- changes to fixture provider data;
- runner integration;
- pipeline integration;
- SQL preview integration;
- SQL/apply architecture;
- runtime checks documentation;
- architecture decision documentation.

## Summary

The full prepared fixture batch check is a future standalone manual validation step for all currently prepared `pump_diameter` fixture rows.

It may validate 8 rows through the existing readonly review-chain usage path, but it must remain diagnostic-only, local-only, and non-apply.
