# DB-readonly normalization proposals generation spec

Дата: 2026-07-07

Статус: spec / decision analysis only.

Этот документ формализует следующий блок controlled attribute consolidation workflow:

```text
approved canonical unit / normalized_value contract
-> normalization proposals generation
-> standalone review-chain
```

Normalization proposals generation начинается только после human-approved canonical unit / `normalized_value` contract.

## 1. Purpose

Цель spec:

* описать DB-readonly generation of normalization proposals;
* зафиксировать, что proposals должны быть reviewable, deterministic и diagnostic-only;
* подготовить proposals для уже реализованной standalone review-chain;
* явно отделить proposals от SQL/apply permission;
* подтвердить, что proposals не меняют DB/cache/config/jobs;
* не делать parser/normalizer implementation в этом step.

Normalization proposals отвечают на вопрос:

```text
какое normalized_value candidate предлагается для конкретного raw_value по approved contract?
```

Они не отвечают на вопросы:

```text
утверждён ли proposal человеком?
можно ли применять SQL?
можно ли менять DB/cache?
можно ли менять config/jobs?
```

## 2. Input

Proposal generation принимает только approved contract context.

Минимальные input fields:

* target attribute meaning;
* canonical `attribute_id`;
* canonical `attribute_name`;
* included alias `attribute_ids`;
* excluded `attribute_ids`;
* completed raw values inventory;
* approved canonical unit / `normalized_value` contract;
* allowed source units;
* allowed conversions;
* prohibited conversions;
* warnings/dirtiness signals;
* optional parser/normalizer family reference, если уже существует;
* optional selector/cache relevance flag.

Если contract не содержит approved human marker, proposals generation запрещён.

Values из excluded `attribute_ids` не должны попадать в proposal input.

Warnings из inventory должны сохраняться как diagnostic input, а не как automatic reject/approve decision.

## 3. Proposal fields

Каждый proposal должен содержать:

* source `attribute_id`;
* source `attribute_name`;
* `raw_value`;
* raw `usage_count` or affected rows count;
* parsed value;
* `normalized_value`;
* canonical unit;
* canonical attribute key;
* canonical `attribute_id`;
* conversion rule used;
* confidence;
* warnings;
* status candidate, например `proposed`;
* reference/id/hash на approved contract;
* sample `product_ids`;
* diagnostics.

Allowed status candidates before human review:

* `proposed`;
* `needs_review`;
* `unknown`.

Proposal generation must not create final review decisions by itself.

## 4. Proposal behavior

Proposal generation must be deterministic for the same input and approved contract.

Rules:

* unknown/unsafe raw values must not silently normalize;
* values outside contract must produce warnings / manual review status;
* prohibited conversions must block proposal or mark it unsafe;
* values from excluded `attribute_ids` must not appear in proposals;
* values from included aliases must preserve source `attribute_id`;
* `normalized_value` must not contain raw suffix/unit string;
* canonical unit must be stored separately;
* confidence must not replace human approval.

Proposal warnings are diagnostics only.

Proposal confidence is not:

* approval;
* SQL/apply permission;
* production readiness;
* cache update permission.

## 5. Relationship to parser/normalizer family

Architecture model:

* одна характеристика = один job/contract;
* один тип значений = один parser/normalizer family;
* новая характеристика не обязательно требует новый PHP handler.

If value semantics are already covered by an existing parser/normalizer family, proposal generation may reference that family.

If value semantics are not covered, a separate spec/decision is required before implementation.

This step does not implement parser/normalizer code.

Parser/normalizer family reference, if present, is only a planning/contract field until a separate implementation step is approved.

## 6. Relationship to standalone review-chain

Proposals must be compatible with the already implemented second half of the workflow:

```text
normalization proposals
-> review fixture generator
-> writer
-> manual review
-> loader
-> bridge
-> approval flow
-> result reporter
```

Standalone review-chain accepts proposals only after approved contract.

`approved` in review-chain means only review status.

`approved` does not mean SQL apply permission.

Apply plan is possible only as a separate explicit step after review.

Review-chain output must not automatically change DB/cache/config/jobs.

## 7. Relationship to config/jobs

`config/jobs` is not the start.

`config/jobs` may appear only after:

* accepted canonical decision;
* completed raw values inventory;
* approved unit/contract;
* proposal generation model/spec.

Config/job must not bypass:

* human canonical selection;
* include/exclude alias decision;
* raw values inventory;
* approved canonical unit / `normalized_value` contract;
* review-chain.

## 8. Examples

Illustrative example only, without implementation:

Approved contract:

* canonical key: `pump_max_head`;
* canonical meaning: maximum pump head;
* canonical unit: `m`;
* `normalized_value`: decimal meters.

Proposal examples:

* raw_value `46.5м.` -> parsed value `46.5`, `normalized_value = 46.5`, unit `m`, confidence high;
* raw_value `68м.` -> parsed value `68`, `normalized_value = 68`, unit `m`, confidence high;
* raw_value `20,5 м` -> parsed value `20.5`, `normalized_value = 20.5`, unit `m`, only if comma decimal rule is approved.

Anti-examples:

* raw_value `68м.` must not produce `normalized_value` as string `68м.`;
* `normalized_value` must not include suffix `м`;
* `pump_max_head` must not be mixed with `max_flow_l_min`;
* meters must not be converted to millimeters unless a separate approved contract explicitly allows that, which `pump_max_head` does not.

Counterexample:

Flow/performance attributes must not generate proposals without a separate approved conversion contract for `m/h` / `l/min`.

Production incident with `max_flow_l_min` remains a warning example for wrong unit semantics.

## 9. Production safety

Production/cache changes are prohibited in this step.

No cache rebuild.

Selector/cache-related attributes require:

* explicit canonical unit contract;
* separate implementation approval;
* separate production/cache approval.

Approved proposals must not automatically change production/cache.

Production incident with `max_flow_l_min`:

* temporary cache hotfix for Belamos/Pedrollo;
* rebuild restored old flow values in `m/h`;
* therefore unit semantics must not be guessed.

No production cache rebuild without separate explicit approval.

## 10. Boundaries

This step is documentation/spec only.

It describes DB-readonly workflow only.

Allowed:

* defining proposal input requirements;
* defining proposal fields;
* defining proposal behavior;
* documenting relationship to approved contract and standalone review-chain;
* documenting safety boundaries.

Not allowed:

* proposals implementation;
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

Запрещённые operation families:

* `INSERT`;
* `UPDATE`;
* `DELETE`;
* `REPLACE`;
* `ALTER`;
* `DROP`;
* `TRUNCATE`;
* `CREATE`.

`approved` remains only a review-chain status.

`approved` does not mean:

* SQL apply allowed;
* `safe_to_apply = 1`;
* `production_ready = 1`;
* apply-ready output.

## 11. Recommended next step

Next separate step:

```text
architecture decision for normalization proposals generation as post-contract pre-review boundary
```

Implementation, parser/normalizer work, `config/jobs`, pipeline/runners, SQL/apply and production/cache actions must remain blocked until separate explicit approval.
