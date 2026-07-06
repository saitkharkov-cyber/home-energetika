# DB-readonly Review Chain Result Reporter Spec

Mini-spec для будущего standalone reporting layer после `DbReadOnlyNormalizationApprovalFlow`.

Реализацию в рамках этого шага не делать.

## Purpose

Standalone review-chain result reporter нужен, чтобы безопасно показать человеку итог manual review / approval flow без перехода к SQL/apply.

Контекст цепочки:

```text
parser output
-> DbReadOnlyLocalReviewFixtureGenerator
-> DbReadOnlyLocalReviewFixtureWriter
-> local ignored JSON file
-> manual review
-> DbReadOnlyLocalReviewFixtureLoader
-> PHP array fixture
-> DbReadOnlyLocalApprovalFixtureBridge
-> DbReadOnlyNormalizationApprovalFlow
-> future result reporter
```

Reporter должен показывать итог review-chain как diagnostics/reporting output, а не как production decision или apply-ready result.

## Future Class

Возможный future class:

```text
src/Approval/DbReadOnlyReviewChainResultReporter.php
```

Возможный future API:

```php
summarize($approvalResult)
```

`$approvalResult` должен быть output standalone approval flow, а не pipeline context.

## Standalone Boundary

Reporter должен быть standalone only.

Reporter не является:

- pipeline stage;
- runner integration;
- SQL preview input by default;
- production normalization;
- production storage;
- DB storage;
- apply-ready output;
- SQL/apply layer.

Reporter не должен менять pipeline wiring, runners или default dry-run path.

## Input Contract

Reporter может принимать result из standalone approval flow.

Ожидаемые input blocks могут включать:

- `updated_proposals`;
- `approval_audit`;
- `approval_summary`;
- `errors`;
- `warnings`;
- `source`.

Reporter должен читать эти данные как already-produced review-chain output.

Reporter не должен вызывать approval flow сам.

## Output Contract

Reporter может возвращать standalone report/result array.

Возможный output:

- `reported`;
- `review_chain_summary`;
- `status_counts`;
- `diagnostics`;
- `errors`;
- `warnings`;
- `source`.

`review_chain_summary` может включать:

- `total_proposals`;
- `approved_count`;
- `rejected_count`;
- `needs_review_count`;
- `unknown_count`;
- `proposed_count`;
- `changed_count`;
- `approval_audit_count`.

`diagnostics` должны явно показывать:

- `reporter_mode = standalone_review_chain_result_reporter`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- `sql_apply_allowed = 0`;
- `production_ready = 0`.

## Allowed Behavior

Reporter может:

- принимать result из standalone approval flow;
- считать summary counts по statuses;
- показывать количество proposals;
- показывать количество `approved`;
- показывать количество `rejected`;
- показывать количество `needs_review`;
- показывать количество `unknown`;
- показывать количество `proposed`;
- показывать unsupported/unsafe statuses как diagnostics;
- возвращать human-readable/reporting diagnostics;
- явно показывать, что SQL/apply still blocked;
- использоваться только standalone/manual.

Unsupported/unsafe statuses должны быть diagnostics-only.

## Forbidden Behavior

Reporter не должен:

- менять statuses;
- создавать `approved`;
- создавать `rejected`;
- принимать review decisions;
- вызывать bridge;
- вызывать approval flow;
- вызывать SQL preview;
- генерировать SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать DB;
- использовать live DB;
- менять DB/schema;
- выполнять write/schema operations;
- подключаться к pipeline;
- подключаться к runners;
- менять default dry-run path.

Запрещённые operation families:

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

## Approval Status Boundary

`approved` и `rejected` в reporter output остаются только review-chain statuses.

`approved` не означает:

- SQL apply permission;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- executable SQL;
- apply-ready output.

`rejected` не должен превращаться в SQL instruction.

`needs_review`, `unknown`, `proposed` не должны становиться SQL preview input.

Only a separate future SQL/apply architecture decision can define how approved proposals may be considered for SQL preview candidates.

## SQL / Apply Boundary

Reporter должен явно сохранять non-apply markers:

```text
sql_generated = 0
apply_plan_created = 0
safe_to_apply = 0
sql_apply_allowed = 0
production_ready = 0
```

Reporter не должен создавать:

- SQL preview;
- SQL statements;
- SQL files;
- SQL diff;
- apply plan.

Reporter не должен выполнять SQL apply.

## Relationship With Existing Standalone Components

Reporter находится после approval flow в standalone chain.

Он может читать output approval flow, но не должен:

- запускать parser;
- запускать generator;
- запускать writer;
- запускать loader;
- запускать bridge;
- запускать approval flow;
- запускать SQL preview.

Каждый layer остаётся отдельной boundary:

- writer = local JSON artifact writer;
- human/manual review = владелец review edits;
- loader = local JSON artifact reader;
- bridge = standalone conversion boundary;
- approval flow = standalone status transition boundary;
- reporter = standalone reporting boundary.

## Verification Plan for Future Implementation

После future implementation reporter-а проверить:

### Syntax check

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyReviewChainResultReporter.php
```

Expected:

```text
No syntax errors detected
```

### Standalone reporter check

Подготовить in-memory approval result with statuses:

- `approved`;
- `rejected`;
- `needs_review`;
- `unknown`;
- `proposed`.

Проверить:

- `total_proposals` заполнен;
- `approved_count` заполнен;
- `rejected_count` заполнен;
- `needs_review_count` заполнен;
- `unknown_count` заполнен;
- `proposed_count` заполнен;
- unsupported/unsafe statuses reported as diagnostics;
- reporter does not change statuses;
- reporter does not create `approved` / `rejected`.

### SQL/apply boundary

Проверить:

- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- `sql_apply_allowed = 0`;
- `production_ready = 0`;
- SQL files not created;
- SQL diff not created;
- SQL apply not performed;
- live DB not used.

### Regression

Default dry-run:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Expected:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

DB-readonly runner:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Expected:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

### Git safety

Проверить:

- no generated fixture JSON files tracked/staged;
- local runtime config не попал в git;
- dump files не попали в git;
- only intended reporter implementation file changed.

## Out of Scope

Вне scope текущего spec:

- PHP implementation;
- actual class;
- runtime checks;
- test framework;
- pipeline wiring;
- runner integration;
- SQL preview integration;
- SQL/apply architecture;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- DB/schema changes;
- write/schema operations;
- HANDOFF update;
- RUNTIME_CHECKS update;
- DECISIONS update.

## References

Project records:

- `docs/HANDOFF.md`;
- `docs/DECISIONS.md`;
- `docs/RUNTIME_CHECKS.md`.

Related standalone review-chain specs:

- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_WRITER_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_LOADER_SPEC.md`;
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`;
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`.

## Recommended Boundary

Review-chain result reporter is only a standalone reporting layer after approval flow.

It must not become:

- production decision layer;
- pipeline stage;
- SQL preview input;
- apply layer.
