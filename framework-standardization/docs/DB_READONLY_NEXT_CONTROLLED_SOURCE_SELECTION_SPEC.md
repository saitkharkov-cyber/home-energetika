# DB-readonly Next Controlled Source Selection Spec

## Purpose

This document defines the decision analysis for choosing the next controlled source after the successful prepared fixture full batch check.

The goal is not to expand processing automatically. The goal is to choose one next small controlled source that keeps the current standalone/manual/readonly review-chain boundary.

This step must not become:

- pipeline wiring;
- runner integration;
- live DB usage;
- full category batch;
- SQL preview;
- SQL/apply;
- production output.

## Current Verified Baseline

Current verified context:

- `pump_diameter`.

Current prepared fixture baseline:

- prepared fixture: `8` controlled readonly rows;
- first manual command: `2` rows;
- review batch command: `4` rows;
- full batch command: `8` rows.

Full batch command was successful:

- `used = 1`;
- `review_ready = 1`;
- `e2e_checked = 1`;
- SQL/apply markers are `0`;
- runtime JSON does not remain after checks;
- default dry-run remains ok;
- DB-readonly runner remains ok.

The current chain confirms that the existing prepared `pump_diameter` fixture can pass through the standalone readonly review-chain path at the current full prepared fixture size.

## Decision Question

The next controlled source should be chosen explicitly before implementation.

There are two candidate directions:

### Option A: Add a second controlled sample source for `pump_diameter`

This option keeps the same characteristic context and adds another small controlled readonly source/sample for the same `pump_diameter` target.

Purpose:

- test stability within one known characteristic;
- compare a second sample shape without mixing characteristics;
- keep runtime checks simple and close to the current baseline.

### Option B: Start an analogous controlled fixture for the next characteristic

This option keeps the same standalone review-chain pattern but applies it to another characteristic.

Purpose:

- test whether the review-chain usage pattern is reusable beyond `pump_diameter`;
- expose characteristic-specific assumptions earlier;
- prepare for wider future coverage without turning current checks into a full batch.

## Evaluation Criteria

### Risk of scope creep

Option A has lower scope risk because it stays inside `pump_diameter`.

Option B has higher scope risk because a new characteristic may require new assumptions, naming, raw value shapes, or parser-like expectations.

### Value for review-chain validation

Option A validates stability inside the already proven context.

Option B validates portability of the standalone review-chain pattern across characteristic contexts.

### Closeness to the applied task

Option A is closest to the current applied task because the existing verified path is already `pump_diameter`.

Option B is useful only if the next engineering goal is to validate broader characteristic coverage.

### Risk of mixing characteristics

Option A avoids mixing characteristic semantics.

Option B introduces a new characteristic boundary and therefore needs a separate explicit decision to avoid mixing contexts in one fixture or one check.

### Risk of becoming full category batch

Both options must stay small and controlled.

Option A has lower full-category-batch risk if it remains a second fixed sample source, not an automatic expansion.

Option B has higher risk if it is treated as the start of broad characteristic coverage instead of one small controlled fixture.

### Runtime checks simplicity

Option A is simpler because current commands, diagnostics and expectations already use `pump_diameter`.

Option B requires new fixture naming, expected counts, and separate checks.

### SQL/apply/live DB absence

Both options must remain:

- no SQL/apply;
- no live DB;
- no production DB;
- no production output.

Neither option may change the current non-apply markers.

## Recommendation

Do not automatically expand the batch further.

Do not start full category batch processing.

Choose exactly one next small controlled source.

Recommended default next step:

- choose Option A if the immediate goal is to verify robustness within the same known `pump_diameter` characteristic;
- choose Option B only after a separate decision if the immediate goal is to validate the standalone pattern on another characteristic.

The conservative recommendation is:

```text
Prefer a second controlled pump_diameter sample source first, unless there is an explicit decision to start a new characteristic fixture.
```

Reason:

- it minimizes scope creep;
- it avoids mixing characteristic semantics;
- it keeps runtime checks close to the existing successful baseline;
- it preserves the current standalone/manual/readonly boundary.

## Boundaries

Any next controlled source must obey these boundaries:

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

`approved` remains only a review-chain status.

`approved` does not mean:

- SQL apply permission;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

## Next-step Guidance

After this spec:

1. Record an architecture decision.
2. Implementation is allowed only after an explicit `+`.
3. If implementation is approved, it must be a minimal controlled fixture/source step.
4. The implementation must not add pipeline wiring, runner integration, SQL/apply, live DB usage, or production output.

The next implementation, if approved, should change only the minimal files required for the chosen controlled source.

## Out Of Scope

This spec does not implement:

- PHP code;
- fixture/source changes;
- command changes;
- runtime checks updates;
- architecture decision updates;
- pipeline wiring;
- runner integration;
- live DB usage;
- SQL preview;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- production output.

## Summary

The next source should be selected deliberately, not by automatic batch expansion.

The safest next source is a second controlled `pump_diameter` sample source, unless a separate explicit decision chooses a new characteristic fixture.

Any path must remain standalone, manual, readonly, diagnostic-only and non-apply.
