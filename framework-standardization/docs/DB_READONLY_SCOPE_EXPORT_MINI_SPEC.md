# DB Readonly Scope + Export Mini-Spec

Mini-spec for safely moving the `resolve_scope` / `export_attributes` pair to the DB-readonly path.

This document is design-only. It does not approve implementation, pipeline wiring, SQL apply, or any OpenCart module work.

## Purpose

Define how `DbReadOnlyScopeResolver` and a future `DbReadOnlyAttributeExporter` must work together before `resolve_scope` can become DB-backed in the DB-readonly runner.

The key rule:

```text
resolve_scope and export_attributes are a linked DB-backed pair.
```

`resolve_scope` must not be switched to real DB product IDs while `export_attributes` still uses `DryRunAttributeExporter`, because the dry-run exporter is fixture-only and expects `product_id = 0`.

## Current State

Default dry-run path:

- entrypoint: `bin/dry-run.php`;
- composition: `PipelineFactory::createDefault()`;
- no DB connection;
- fixture source: `dry_run_fixture`;
- all 9 stages stay dry-run;
- expected product and attribute placeholders use IDs such as `product_id = 0` and `attribute_id = 0`.

DB-readonly path:

- entrypoint: `bin/db-readonly-run.php`;
- composition: `DbReadOnlyPipelineFactory`;
- local dump DB only;
- current DB-backed stage in runner: `resolve_canonical`;
- `DbReadOnlyCanonicalAttributeResolver` is wired only in DB-readonly composition;
- `resolve_scope` still uses `DryRunScopeResolver`;
- `export_attributes` still uses `DryRunAttributeExporter`;
- downstream stages after `export_attributes` still remain dry-run-compatible.

Existing standalone capability:

- `DbReadOnlyScopeResolver` exists;
- it has been manually checked against the local dump;
- it resolves `category_id = 11900213`;
- it returns real product rows and real `product_id` values;
- it is not wired into `DbReadOnlyPipelineFactory`.

## Problem

`DryRunAttributeExporter` is intentionally fixture-bound:

- supports only `canonical_code = pump_diameter`;
- supports only `category_id = 11900213`;
- expects fixture products;
- requires the first product to have `product_id = 0`;
- returns fixture attributes and raw values with placeholder IDs.

`DbReadOnlyScopeResolver` returns real DB products:

- `scope.source = local_dump_db_readonly`;
- `scope.product_ids` contains real OpenCart product IDs;
- `raw_data.products` contains real product rows;
- no fixture `product_id = 0` is produced.

Therefore wiring only `DbReadOnlyScopeResolver` into the DB-readonly pipeline would create a mixed incompatible context:

```text
real scope/products from DB
fixture-only exporter expecting product_id = 0
```

That hybrid would fail at `export_attributes` or, worse, make later stages interpret mismatched fixture and DB facts as if they belonged together.

## Design Goal

Move `resolve_scope` and `export_attributes` to DB-backed behavior as one controlled pair in the DB-readonly composition path.

The design must preserve:

- default dry-run as no-DB fixture mode;
- DB-readonly runner as a separate manual path;
- no SQL apply;
- no live DB access;
- PHP 5.6-compatible CLI/tooling layer;
- stage order and `AttributeContext` exchange through existing stage boundaries.

## Proposed DB-backed Pair

### resolve_scope

Future DB-readonly wiring may use `DbReadOnlyScopeResolver` only when `export_attributes` is also DB-backed.

The resolver should continue to:

- accept `ScopeResolverInterface::resolve(array $scope)`;
- support the first MVP scope only: `type = category`, `category_id = 11900213`;
- read only the local dump DB through `ReadOnlyDbConnectionInterface`;
- use table names through `OpenCartTableName` and `db_prefix`;
- return the same high-level result shape as dry-run scope resolving.

It must provide enough downstream data for DB-backed export:

- resolved scope metadata;
- `product_ids` for the resolved category;
- product rows with real `product_id`;
- language/runtime source facts required by the exporter;
- errors and warnings as string code arrays.

Expected success shape:

```php
array(
    'found' => 1,
    'scope' => array(
        'type' => 'category',
        'category_id' => 11900213,
        'category_name' => 'Скважинные насосы',
        'product_ids' => array(1068, 1069),
        'product_count' => 1972,
        'source' => 'local_dump_db_readonly',
    ),
    'products' => array(
        array(
            'product_id' => 1068,
            'model' => '...',
            'name' => '...',
            'status' => 1,
            'quantity' => 0,
            'category_ids' => array(11900213),
            'source' => 'local_dump_db_readonly',
        ),
    ),
    'errors' => array(),
    'warnings' => array(),
    'source' => 'local_dump_db_readonly',
)
```

The exact product list is DB data and must not be hardcoded in documentation or code.

### export_attributes

Future `DbReadOnlyAttributeExporter` should be the DB-backed companion for `DbReadOnlyScopeResolver`.

It should:

- implement `AttributeExporterInterface`;
- keep the public method shape: `export(array $canonical, array $scope, array $products)`;
- accept real `product_id` values from `DbReadOnlyScopeResolver`;
- accept the canonical target attribute resolved by `DbReadOnlyCanonicalAttributeResolver`;
- read only local dump catalog/reference tables;
- export current attribute facts for the products in scope;
- return the same high-level result shape as `DryRunAttributeExporter`;
- mark returned data with `source = local_dump_db_readonly`;
- never create SQL apply statements.

The exporter must not assume fixture IDs:

```text
product_id = 0 is invalid for DB-readonly export facts.
attribute_id = 0 is invalid for DB-readonly export facts.
target_attribute_id must come from resolved canonical data.
```

## Data Contract Between Stages

`ResolveScopeStage` writes:

- `scope`;
- `raw_data.products`;
- errors and warnings;
- `stage_results.resolve_scope`.

For DB-readonly pair mode, `scope` must include:

- `type`;
- `category_id`;
- `category_name`;
- `product_ids`;
- `product_count`;
- `source = local_dump_db_readonly`.

For DB-readonly pair mode, each product row must include:

- `product_id`;
- `model`;
- `name`;
- `status`;
- `quantity`;
- `category_ids`;
- `source = local_dump_db_readonly`.

`ExportAttributesStage` reads:

- `canonical`;
- `scope`;
- `raw_data.products`.

The future DB-readonly exporter input requirements:

- `canonical.canonical_code = pump_diameter`;
- `canonical.target_attribute_id` is a real OpenCart attribute ID;
- `canonical.target_attribute_group_id` is a real OpenCart group ID;
- `scope.category_id = 11900213`;
- `scope.product_ids` contains real IDs;
- `products` is non-empty and matches `scope.product_ids`;
- runtime language is the DB-readonly supported language, currently `language_id = 1`.

The future DB-readonly exporter output should include:

- `exported`;
- `attributes`;
- `attribute_groups`;
- `product_attributes`;
- `target_attribute`;
- `found_attributes`;
- `raw_values`;
- `errors`;
- `warnings`;
- `source = local_dump_db_readonly`.

The data should remain compatible with existing downstream stage keys:

- `raw_data.attributes`;
- `raw_data.attribute_groups`;
- `raw_data.product_attributes`;
- `attribute_name_structure.target_attribute`;
- `attribute_name_structure.found_attributes`;
- `attribute_value_structure.raw_values`.

The first implementation may still leave later analyzers and builders dry-run only, but it must not feed them fixture/DB mixed data as a normal success path unless that compatibility is explicitly specified and verified.

## Hybrid Path Safety

Default dry-run must remain unchanged:

- `bin/dry-run.php` remains no-DB;
- `PipelineFactory::createDefault()` remains fixture-based;
- dry-run job config remains fixture/no-DB behavior;
- `DryRunScopeResolver` and `DryRunAttributeExporter` remain paired.

DB-readonly runner must remain separate:

- `bin/db-readonly-run.php` is the manual local dump path;
- runtime config remains ignored and local;
- `DbReadOnlyPipelineFactory` is the only composition path allowed to wire DB-backed components.

DB-backed scope/export stages must be connected only together:

```text
Allowed future DB-readonly pair:
DbReadOnlyScopeResolver + DbReadOnlyAttributeExporter

Not allowed:
DbReadOnlyScopeResolver + DryRunAttributeExporter
```

The composition should fail early or remain unwired if only one component of the pair is available.

## Read-only Safety Rules

Mandatory rules for the future implementation:

- use only local dump/local DB;
- never use live DB;
- use `ReadOnlyDbConnectionInterface`;
- use only read operations allowed by the read-only connection;
- do not add SQL apply;
- do not add executable SQL files;
- do not use write or schema-changing operations;
- do not create transactions or write APIs;
- do not read personal or operational tables excluded by `DUMP_LOCAL_DB_CHECKLIST.md`;
- do not create OpenCart module paths;
- do not change `bin/dry-run.php`;
- do not change default dry-run composition;
- do not change stage order.

Forbidden operation families remain:

```text
INSERT
UPDATE
DELETE
REPLACE
ALTER
DROP
TRUNCATE
CREATE
```

## Out of Scope

This step does not include:

- implementing `DbReadOnlyAttributeExporter`;
- wiring `DbReadOnlyScopeResolver`;
- changing `DbReadOnlyPipelineFactory`;
- changing `PipelineFactory::createDefault()`;
- changing `bin/dry-run.php`;
- changing `bin/db-readonly-run.php`;
- changing existing stage classes;
- adding live DB support;
- adding SQL apply;
- adding executable SQL;
- creating OpenCart module paths;
- expanding to more categories or canonicals;
- production synonym decisions;
- production value normalization;
- production SQL preview generation.

## Future Implementation Steps

Only after separate approval:

1. Implement `DbReadOnlyAttributeExporter` behind `AttributeExporterInterface`.
2. Keep it limited to local dump, `pump_diameter`, `category_id = 11900213`, and `language_id = 1`.
3. Verify it standalone against the local dump.
4. Add a paired composition change in `DbReadOnlyPipelineFactory`:
   - `DbReadOnlyScopeResolver`;
   - `DbReadOnlyAttributeExporter`.
5. Keep `PipelineFactory::createDefault()` and `bin/dry-run.php` unchanged.
6. Run DB-readonly runner checks.
7. Run default dry-run checks to confirm no regression.

The composition step should be treated as one change. Do not commit an intermediate state where only `resolve_scope` is DB-backed.

## Verification Plan

After future implementation, verify at minimum:

- default dry-run still returns `result_status: ok`;
- default dry-run still reports all 9 stages;
- default dry-run uses `source = dry_run_fixture`;
- DB-readonly runner still uses local dump config only;
- DB-readonly runner resolves canonical via DB-backed resolver;
- DB-readonly runner resolves scope with real product IDs;
- DB-readonly runner exports attributes for those real product IDs;
- no `product_id = 0` appears in DB-readonly exported product facts;
- no `attribute_id = 0` appears in DB-readonly exported attribute facts;
- target attribute matches resolved canonical `target_attribute_id`;
- errors/warnings are stable for unsupported category, unsupported scope type, and unknown canonical;
- no SQL apply is produced or executed;
- no forbidden OpenCart module paths are created;
- `git status` does not include ignored runtime config or dump files.

PHP checks, when code changes are introduced, must use:

```text
C:\php56\php.exe
```
