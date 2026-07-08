# Range policy — maximum head scope 11900213

Date: 2026-07-09

## Context

Target attribute meaning:

`максимальный напор`

Category scope:

`11900213`

Related contract:

`docs/MAX_HEAD_UNIT_CONTRACT_SCOPE_11900213.md`

Related inventory evidence:

`docs/RUNTIME_CHECKS.md`

Human decision source:

`docs/HUMAN_DECISION_MAX_HEAD_SCOPE_11900213.md`

## Decision

Range-like, upper-bound, and mixed-text raw values remain:

`unresolved`

They must not be normalized automatically in the first normalization proposals generation step.

## Affected examples

Examples observed in raw values inventory:

- `100-104`
- `104–118`
- `123–151`
- `43–46`
- `50–51,5`
- `87-91`
- `87–91`
- `до 51 м`

## Reason

For maximum head, a range may represent:

- operating interval;
- min/max range;
- tolerance;
- model-specific performance range;
- vendor-specific table value;
- text copied from another catalog/source.

The current workflow does not approve whether to use:

- lower bound;
- upper bound;
- average;
- another derived value.

Therefore these values must stay unresolved until a separate range-specific human decision is made.

## Allowed in next proposals generation

The next normalization proposals generation step may include only simple accepted values from the unit contract, for example:

- `60м.` -> `60`
- `75 м` -> `75`
- `54.5` -> `54.5`
- `31,5` -> `31.5`
- `150` -> `150`

## Not allowed in next proposals generation

The next normalization proposals generation step must not normalize:

- ranges;
- upper-bound textual values;
- mixed-text values;
- ambiguous multi-number values.

These rows must be placed in an unresolved block/report only.

## Scope

This policy applies only to:

- category_scope: `11900213`
- target: `максимальный напор`
- attribute_ids: `12`, `101`, `119`, `81`

## Safety boundaries

This document is a human decision note only.

It does not:

- generate normalization proposals;
- generate SQL preview;
- create SQL files/diff;
- create apply plan;
- apply SQL;
- change config/jobs;
- change pipeline/runners;
- touch production/cache;
- rebuild cache;
- allow auto-merge.

`approved` in review-chain remains only a review status and does not mean SQL/apply permission.

## Next allowed step

The next allowed workflow step is:

`normalization proposals generation`

Constraint:

- accepted simple values only;
- range-like / upper-bound / mixed-text values must be reported as unresolved.