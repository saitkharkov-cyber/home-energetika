# Unit contract — maximum head scope 11900213

Date: 2026-07-08

## Context

Target attribute meaning:

`максимальный напор`

Category scope:

`11900213`

Human decision source:

`docs/HUMAN_DECISION_MAX_HEAD_SCOPE_11900213.md`

Raw values inventory check:

`docs/RUNTIME_CHECKS.md`

Related commits:

- `9d49d78 Document DB readonly raw values inventory check`
- `ad0090b Add DB readonly raw values inventory command`
- `8d7e239 Document max head canonical selection decision`

## Input attribute IDs

This contract applies only to the human-approved maximum head group:

- canonical attribute: `12 — Максимальный напор`
- included aliases:
  - `101 — Максимальный напор, м.вод.ст.`
  - `119 — Максимальный напор, м`
  - `81 — Max напор, м`

Do not include excluded attributes:

- `20 — Минимальный напор`
- `171 — Максимальный расход Qmax, м³/ч`
- `100 — Максимальный расход Qmax, м³/ч`
- `120 — Номинальный напор, м`

## Canonical unit decision

Canonical unit:

`m`

Meaning:

`meters of water head`

Canonical normalized value:

`numeric maximum head in meters`

The `normalized_value` must be a decimal number representing meters.

Examples:

- `60м.` -> `60`
- `75 м` -> `75`
- `54.5` -> `54.5`
- `31,5` -> `31.5`
- `150` -> `150`

## Accepted raw value patterns

### Plain integer or decimal number

Accepted:

- `42`
- `65`
- `200`
- `54.5`
- `31,5`

Normalization rule:

- trim whitespace;
- replace decimal comma with decimal dot;
- parse as decimal number;
- canonical unit is meters.

### Number with meter suffix

Accepted:

- `60м.`
- `120м.`
- `75 м`
- `46.5м.`

Normalization rule:

- trim whitespace;
- remove meter suffix variants;
- replace decimal comma with decimal dot;
- parse as decimal number;
- canonical unit is meters.

Accepted meter suffix variants:

- `м`
- `м.`
- `m`
- `m.`
- optional whitespace before suffix.

### Number with water column unit in attribute name

For attributes whose name already contains `м.вод.ст.`:

- `101 — Максимальный напор, м.вод.ст.`

Accepted raw values may be numeric without explicit unit:

- `279`
- `310`
- `54.5`
- `78`

Normalization rule:

- parse numeric value as meters of water head;
- canonical unit is meters.

Reason:

For water head, `м.вод.ст.` is semantically equivalent to meters of water column/head for this catalog attribute.

## Unresolved raw value patterns

The following patterns must not be normalized automatically at this stage:

### Ranges

Examples:

- `100-104`
- `104–118`
- `123–151`
- `43–46`
- `50–51,5`
- `87-91`
- `87–91`

Decision:

`unresolved`

Reason:

A range can mean min/max, operating interval, tolerance, or another vendor-specific interval. The contract does not yet approve whether to use min, max, average, or another rule.

### Textual upper-bound values

Examples:

- `до 51 м`

Decision:

`unresolved`

Reason:

`до 51 м` means “up to 51 m”. The contract does not yet approve converting this to `51`.

### Mixed text values

Examples:

- values with extra words or unclear text beyond a simple numeric value and unit.

Decision:

`unresolved`

Reason:

Mixed text needs review before normalization.

## Rejected / not in scope

The following meanings are not part of this contract:

- minimum head;
- nominal head;
- maximum flow;
- current;
- power;
- diameter;
- any other non-maximum-head attribute.

## Output contract for future proposals

A future normalization proposal may include rows only when:

- `attribute_id` is one of `12`, `101`, `119`, `81`;
- product is inside category scope `11900213`;
- raw value matches accepted patterns;
- parsed numeric value is valid;
- canonical unit is `m`;
- `normalized_value` is decimal meters.

Rows with unresolved patterns must be separated and not silently normalized.

## Safety boundaries

This document is a contract decision only.

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

Scope for that step:

- category_scope: `11900213`
- attribute_ids: `12`, `101`, `119`, `81`
- canonical unit: `m`
- normalized_value: decimal meters
- accepted patterns only;
- unresolved range/text/mixed values separated from accepted proposals.