# DB-readonly Pump Diameter Controlled Sources Stability Spec

## Purpose

This document defines comparison and stability criteria for two controlled readonly sample sources for the same characteristic:

```text
pump_diameter
```

The goal is to decide whether the standalone review-chain is stable enough inside one known characteristic before moving to a new characteristic.

This spec is only a decision-analysis boundary.

It must not become:

- full category batch;
- pipeline wiring;
- runner integration;
- SQL preview;
- SQL/apply;
- live DB usage;
- production output.

## Current Verified Baseline

Current first `pump_diameter` fixture provider:

```text
DbReadOnlyFirstRealDataUsageInputFixture
```

First source verified facts:

- prepared rows count: `8`;
- first manual command checked `2` rows;
- review batch command checked `4` rows;
- full batch command checked `8` rows;
- full batch command was successful.

Current second `pump_diameter` fixture provider:

```text
DbReadOnlySecondPumpDiameterUsageInputFixture
```

Second source verified facts:

- prepared rows count: `8`;
- source marker: `second_pump_diameter_controlled_sample`;
- standalone fixture check was successful;
- first-run integration through `DbReadOnlyRealDataReviewChainUsageChecker` was successful;
- full batch check through bounded chunks was successful.

Both sources use the same standalone readonly review-chain:

```text
readonly fixture rows
-> DbReadOnlyRealDataReviewChainUsageChecker
-> parser-like output
-> DbReadOnlyStandaloneReviewChainE2EChecker
-> generator
-> writer
-> local ignored JSON
-> synthetic/manual review blocks
-> loader
-> bridge
-> approval flow
-> reporter
```

Both full batch checks use bounded chunks because `DbReadOnlyRealDataReviewChainUsageChecker` has current safety limit:

```text
MAX_ROWS = 5
```

Verified common safety facts:

- SQL/apply markers remain `0`;
- runtime JSON does not remain after checks;
- SQL/apply artifacts are not created;
- default dry-run remains ok;
- DB-readonly runner remains ok.

## Stability Criteria

The standalone review-chain can be considered stable enough for a next-characteristic decision only if both controlled `pump_diameter` sources satisfy all criteria below.

Required source-level criteria:

- each source returns `used = 1`;
- each source returns `review_ready = 1`;
- each source returns `e2e_checked = 1`;
- each source full batch passes without errors;
- each source keeps SQL/apply markers at `0`;
- each source leaves no runtime fixture JSON after checks;
- each source creates no SQL/apply artifacts.

Required aggregate criteria:

- bounded chunking does not break aggregate result;
- aggregate errors count remains `0`;
- default dry-run remains ok;
- DB-readonly runner remains ok;
- diagnostics are clear enough for manual review.

Required non-apply markers:

- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- `sql_apply_allowed = 0`;
- `production_ready = 0`.

`approved` remains only a review-chain status.

`approved` does not mean:

- SQL apply permission;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

## Expected Comparison Output

If a future comparison/check command is explicitly approved later, its summary should be diagnostic-only and may include:

- `source_count = 2`;
- `total_controlled_rows = 16`;
- `sources_ready_count = 2`;
- `aggregate_errors_count = 0`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- `sql_apply_allowed = 0`;
- `production_ready = 0`;
- `ready_for_next_characteristic_decision = 1`.

The marker:

```text
ready_for_next_characteristic_decision = 1
```

is allowed only when all stability criteria are satisfied.

It is not:

- production readiness;
- SQL/apply permission;
- approval to write DB data;
- approval to connect pipeline/runners.

## Explicit Non-goals

This spec does not:

- normalize the full category;
- create production output;
- create SQL preview;
- create SQL;
- create SQL files;
- create SQL diff;
- create apply plan;
- perform SQL apply;
- add a new runner;
- connect anything to pipeline;
- add a new characteristic in this step;
- implement a comparison command.

## Boundaries

Any future comparison or stability check must obey these boundaries:

- no pipeline wiring;
- no runner integration;
- no live DB / production DB;
- no full category batch;
- no arbitrary input;
- no filenames/paths/URLs input;
- no SQL preview;
- no SQL generation/files/diff;
- no apply plan;
- no SQL apply;
- no DB/schema changes;
- no write/schema operations;
- no production output;
- no committed runtime artifacts;
- no default dry-run path changes.

## Recommendation

After this spec, record an architecture decision for the stability criteria.

If that decision confirms the criteria are satisfied, the next logical step should be a spec for the first new characteristic.

Do not implement a comparison command now without a separate explicit `+`.

Do not move to pipeline/runners, SQL/apply, live DB, production output, or full category batch as part of this step.

## Out Of Scope

This spec does not implement:

- PHP code;
- comparison command;
- fixture changes;
- runtime checks updates;
- architecture decision updates;
- pipeline wiring;
- runner integration;
- SQL preview;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- DB/live DB usage;
- DB/schema changes;
- production output.

## Summary

Two controlled `pump_diameter` sources provide a local readonly stability baseline.

The review-chain is stable enough for a future next-characteristic decision only if both sources remain review-ready, error-free, non-apply, artifact-clean and compatible with existing default dry-run and DB-readonly runner checks.
