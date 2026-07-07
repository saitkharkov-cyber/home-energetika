# DB-readonly canonical unit / normalized_value contract spec

## Purpose

This document defines the workflow block after completed raw values inventory:

target attribute meaning
-> DB-readonly attribute name discovery
-> human canonical selection
-> explicit include / exclude alias decision
-> raw values inventory
-> canonical unit decision
-> normalized_value contract
-> preparation for normalization proposals

The canonical unit / normalized_value contract is a human-approved boundary. It defines the canonical unit, the meaning of `normalized_value`, and the allowed interpretation of source values before any normalization proposals are generated.

The contract must prevent unit mistakes, especially for values that can later feed selectors, cache, or production-facing output. Unit semantics must not be guessed automatically.

This spec does not implement a parser, normalizer, fixture, job, runner, SQL preview, or apply path.

## Input

The contract step may start only after raw values inventory is completed.

Required input:

* target attribute meaning;
* canonical `attribute_id`;
* canonical `attribute_name`;
* included alias `attribute_ids`;
* excluded `attribute_ids`;
* completed raw values inventory;
* warnings / dirtiness signals from inventory;
* optional raw samples and usage counts;
* optional production/cache relevance flag.

If raw values inventory is missing or incomplete, canonical unit and `normalized_value` contract approval is blocked.

## Contract fields

The approved contract must explicitly record:

* canonical attribute key;
* canonical meaning;
* canonical `attribute_id`;
* included alias `attribute_ids`;
* canonical unit;
* `normalized_value` type;
* `normalized_value` meaning;
* allowed source units;
* allowed conversions;
* prohibited conversions;
* parsing expectations;
* examples;
* anti-examples;
* warnings requiring manual review;
* selector/cache safety notes;
* approval status / approved-by-human marker.

## Normalized value rules

`normalized_value` must be a machine-comparable value. It must not contain the raw suffix or unit string.

The canonical unit is stored separately at the contract level. The same value type must use one decimal representation rule across proposals.

Comma and point decimal handling must be approved explicitly. For example, converting `20,5` to `20.5` is allowed only if the contract approves comma decimal normalization.

The following cases require explicit handling or manual review:

* ranges;
* multiple values;
* HTML entities;
* fractions;
* text + number combinations;
* mixed units;
* suspicious suffixes;
* values that appear to belong to another semantic.

Unknown or unsafe values must not be silently normalized.

## Example: pump_max_head

This is an illustrative example only, not an implementation step.

Canonical contract:

* canonical key: `pump_max_head`;
* canonical meaning: maximum pump head;
* canonical unit: `m`;
* `normalized_value`: decimal number in meters.

Examples:

* `46.5м.` -> `normalized_value = 46.5`, `unit = m`;
* `68м.` -> `normalized_value = 68`, `unit = m`;
* `20,5 м` -> `normalized_value = 20.5`, `unit = m`, only if comma decimal rule is approved.

Anti-examples:

* do not store `68м.` as `normalized_value`;
* do not store `68 м` as a string with suffix;
* do not convert meters to millimeters;
* do not treat `68м` as `68 мм`;
* do not mix maximum head with flow / `max_flow_l_min`.

Counterexample:

`max_flow_l_min` and other flow/performance attributes require a separate unit contract. Values in `m/h`, `l/min`, raw legacy values, and selector/cache values must not be mixed without an explicit conversion contract.

## Relationship to proposals

Normalization proposals must not be generated before the canonical unit / `normalized_value` contract is approved.

Each future proposal must reference the approved contract and show:

* `raw_value`;
* parsed value;
* `normalized_value`;
* canonical unit;
* confidence;
* warnings;
* source `attribute_id`.

Proposal generation is a separate future spec/step.

## Relationship to config/jobs

`config/jobs` is not the start of attribute discovery or unit selection.

`config/jobs` may appear only after:

* accepted canonical decision;
* raw values inventory completed;
* canonical unit approved;
* `normalized_value` contract approved.

One characteristic must have one job/contract. One value type should map to one parser/normalizer family. A new characteristic does not necessarily require a unique PHP handler if the value semantics are already covered by an existing parser family.

## Relationship to review-chain

The standalone review-chain receives normalization proposals only after an approved contract exists.

Review approval does not mean SQL apply permission. Apply plan is possible only as a separate explicit step after review.

Approved normalized proposals must not automatically change DB, cache, production data, or selector output.

## Production safety

Selector/cache-related attributes require an explicit canonical unit contract before implementation.

No production/cache changes are allowed in this step. No cache rebuild is allowed.

The `max_flow_l_min` production incident remains a warning example:

* a temporary cache hotfix was applied for Belamos/Pedrollo;
* production rebuild restored old flow values in the `m/h` scale;
* therefore unit semantics must not be guessed.

Production cache rebuild requires separate explicit approval.

## Boundaries

This step is documentation/spec only.

Allowed:

* DB-readonly workflow design;
* defining required contract fields;
* documenting examples, anti-examples, and safety boundaries.

Not allowed:

* unit auto-approval;
* automatic `normalized_value` contract creation;
* normalization proposals in this step;
* parser implementation;
* normalizer implementation;
* PHP implementation;
* `config/jobs` changes;
* pipeline wiring;
* runner integration;
* SQL preview;
* SQL generation;
* SQL files;
* SQL diff;
* apply plan;
* SQL apply;
* live DB / production DB;
* DB/schema changes;
* write/schema operations;
* production output;
* production/cache changes;
* cache rebuild;
* runtime artifacts;
* committed runtime artifacts;
* default dry-run path changes.
