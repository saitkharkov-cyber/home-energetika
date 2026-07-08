# Human decision — maximum head discovery scope 11900213

Date: 2026-07-08

## Context

Target attribute meaning:

`максимальный напор`

Category scope:

`11900213`

Discovery command:

`framework-standardization/bin/db-readonly-attribute-discovery.php`

Scoped discovery check documented in:

`docs/RUNTIME_CHECKS.md`

Related commits:

- `780a0eb Document DB readonly discovery category scope check`
- `eab3691 Add category scope to DB readonly attribute discovery`
- `05a1f2a Add DB readonly attribute discovery command`
- `c7a9cbc Add DB readonly attribute discovery command spec`

## Discovery result summary

Scoped discovery command returned:

- `category_scope: 11900213`
- `candidates_count: 8`

## Human canonical selection

Canonical attribute:

- `attribute_id: 12`
- `attribute_name: Максимальный напор`
- `group: Параметры насоса`
- `usage_count: 400`
- `reason_found: exact_name_match`
- `possible_role: canonical_candidate`

Decision:

`attribute_id: 12` is selected as canonical for the target meaning `максимальный напор` within category scope `11900213`.

## Included alias candidates for next raw values inventory

Include these attribute IDs in the next raw values inventory step:

- `101 — Максимальный напор, м.вод.ст.`
- `119 — Максимальный напор, м`
- `81 — Max напор, м`

Reason:

These candidates are likely same-meaning aliases for maximum head within the selected category scope, but their raw values still require inventory and unit/contract review before any normalization proposal.

## Excluded similar-but-different candidates

Exclude these candidates from the maximum head canonical group:

- `20 — Минимальный напор`
- `171 — Максимальный расход Qmax, м³/ч`
- `100 — Максимальный расход Qmax, м³/ч`
- `120 — Номинальный напор, м`

Reason:

These attributes represent different meanings:

- minimum head;
- maximum flow;
- nominal head.

They must not be merged into maximum head.

## Next allowed step

The next allowed workflow step is:

`raw values inventory`

Scope for that step:

- canonical attribute: `12`
- included alias candidates: `101`, `119`, `81`
- category scope: `11900213`

The raw values inventory step must remain DB-readonly.

## Boundaries

This document is a human decision note only.

It does not:

- create normalization proposals;
- approve a canonical unit;
- create a `normalized_value` contract;
- create SQL preview;
- create SQL files/diff;
- create apply plan;
- apply SQL;
- change config/jobs;
- change pipeline/runners;
- touch production/cache;
- rebuild cache;
- allow auto-merge.

`approved` in review-chain remains only a review status and does not mean SQL/apply permission.