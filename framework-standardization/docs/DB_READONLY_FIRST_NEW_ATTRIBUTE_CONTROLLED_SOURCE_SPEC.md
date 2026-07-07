# DB-readonly First New Attribute Controlled Source Spec

## Purpose

This document defines decision analysis for the first new characteristic after the verified `pump_diameter` stability gate.

The goal is to move from a verified single-characteristic baseline to one carefully selected new characteristic while keeping the same controlled, small, readonly, standalone review-chain boundary.

This step must remain:

- controlled small readonly source only;
- standalone/manual/diagnostic-only;
- non-production;
- non-apply.

This step must not become:

- full category batch;
- pipeline wiring;
- runner integration;
- SQL preview;
- SQL/apply;
- live DB usage;
- production output.

## Current Baseline

Current verified characteristic:

```text
pump_diameter
```

Current stability gate:

- first `pump_diameter` controlled source: `DbReadOnlyFirstRealDataUsageInputFixture`, `8` rows;
- second `pump_diameter` controlled source: `DbReadOnlySecondPumpDiameterUsageInputFixture`, `8` rows;
- both sources use standalone readonly review-chain;
- full batch checks use bounded chunking because `DbReadOnlyRealDataReviewChainUsageChecker` has `MAX_ROWS = 5`;
- SQL/apply markers remain `0`;
- runtime JSON does not remain after checks;
- default dry-run remains ok;
- DB-readonly runner remains ok.

The first new characteristic should be selected only after this stability gate is accepted as sufficient for moving beyond `pump_diameter`.

## Selection Criteria

The first new characteristic should satisfy all criteria below.

Domain criteria:

- it should be from the same borehole/deep-well pump domain;
- it should be close enough to `pump_diameter` pipeline semantics to reuse the current standalone review-chain safely;
- it should not mix unrelated product families or unrelated attribute meanings.

Data shape criteria:

- it should have a simple row format;
- it should be suitable for a small first slice;
- it should be suitable for an `8` row controlled full source;
- it may allow a prepared source up to `12` rows if needed later.

Implementation boundary criteria:

- it should not require new DB schema assumptions;
- it should not require OpenCart runtime paths;
- it should not require SQL preview;
- it should not require SQL/apply;
- it should not require live DB access;
- it should not require production DB access.

Expected row format should stay compatible with the current usage checker input style:

- `product_id`;
- `attribute_id`;
- `attribute_name`;
- `raw_value`;
- `normalized_value`;
- `confidence`.

## Candidate Options

The currently available documents do not name one unambiguous best next characteristic.

Therefore the next characteristic should be selected by a separate architecture decision.

Candidate options:

### Option A: Another pump physical dimension attribute

Examples:

- connection diameter-like attribute;
- outlet/inlet diameter-like attribute;
- casing or body dimension-like attribute.

Why it may fit:

- closest to `pump_diameter` semantics;
- likely simple numeric/unit-like raw values;
- low risk of introducing new parser-like assumptions.

Risks:

- could be too similar to `pump_diameter` and add limited coverage;
- naming must be precise to avoid mixing different physical dimensions.

### Option B: Pump flow/capacity-like attribute

Examples:

- flow rate;
- capacity;
- productivity.

Why it may fit:

- still in the same pump domain;
- useful next semantic shape after diameter;
- likely common in pump characteristics.

Risks:

- may introduce unit variants or range values;
- may require clearer review diagnostics if units differ.

### Option C: Pump power-like attribute

Examples:

- motor power;
- rated power.

Why it may fit:

- common pump characteristic;
- numeric/unit-like values may remain simple.

Risks:

- may involve kW/W/hp variants;
- could require unit conversion assumptions that must remain review-only.

### Option D: Pump head/pressure-like attribute

Examples:

- head;
- pressure;
- lifting height.

Why it may fit:

- common borehole pump characteristic;
- relevant to the same domain.

Risks:

- may contain ranges or operating curves;
- could create ambiguity between pressure and head semantics.

## Recommended Next Step

After this spec, record an architecture decision with one concrete selected characteristic.

The decision should state:

- selected characteristic name/context;
- why it is the safest next characteristic;
- expected first controlled source size;
- expected full controlled source size;
- whether any characteristic-specific warnings are expected;
- explicit confirmation that SQL/apply remains blocked.

Do not implement a fixture/source before that decision.

Implementation is allowed only after a separate explicit `+`.

If implementation is approved later, it should create only a minimal controlled local readonly source/fixture for the selected characteristic.

## Boundaries

Any future first-new-characteristic source must obey these boundaries:

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

## Explicit Non-goals

This spec does not:

- choose the final characteristic;
- implement PHP code;
- implement a fixture/source;
- add a new command;
- add a new runner;
- connect pipeline/runners;
- read live DB;
- read production DB;
- normalize a full category;
- create SQL preview;
- generate SQL;
- create SQL files;
- create SQL diff;
- create apply plan;
- perform SQL apply;
- create production output.

## Summary

The first new characteristic should be selected deliberately after the `pump_diameter` stability gate.

The safest candidates are characteristics from the same borehole pump domain with simple, controlled, readonly row data and no new DB/schema/runtime/SQL assumptions.

The next required step is an architecture decision with one selected characteristic. Fixture/source implementation must wait for a separate explicit `+`.
