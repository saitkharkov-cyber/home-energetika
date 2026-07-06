# DB-readonly Standalone Review Flow Check Spec

Mini-spec для будущей standalone end-to-end проверки review flow между parser output, local review fixture generator, local approval fixture bridge и approval flow.

Документ описывает только безопасный contract check. Реализацию в рамках этого шага не делать.

## Purpose

Standalone E2E review flow check нужен, чтобы проверить совместимость standalone components без подключения к pipeline:

```text
parser output
-> local review fixture generator
-> manually edited fixture array
-> local approval fixture bridge
-> approval flow
```

Проверка должна подтвердить:

- generator создаёт human-reviewable fixture array с пустыми review blocks;
- review decisions появляются только после manual edit fixture array;
- bridge только мапит непустые `review.action` в review actions;
- status transitions выполняет только approval flow;
- `approved` / `rejected` не создаются parser-ом, generator-ом или bridge-ом;
- весь flow остаётся standalone и не создаёт SQL/apply output.

## Current Context

Уже существуют standalone components:

- `DbReadOnlyNormalizationProposalParser`;
- `DbReadOnlyLocalReviewFixtureGenerator`;
- `DbReadOnlyLocalApprovalFixtureBridge`;
- `DbReadOnlyNormalizationApprovalFlow`.

Parser создаёт только:

- `proposed`;
- `needs_review`;
- `unknown`.

Parser не должен создавать:

- `approved`;
- `rejected`.

Generator превращает parser output в JSON-ready fixture array и добавляет пустой reviewer-owned `review` block.

Bridge читает fixture array, собирает proposals и непустые review actions, затем вызывает approval flow.

Approval flow является единственным standalone layer, который может явно создать:

- `approved`;
- `rejected`;
- `needs_review`;
- `unknown`;
- `proposed`.

## Standalone Flow

Будущий check должен выполнять flow только in memory:

1. Подготовить `parserOutput` array.
2. Вызвать `DbReadOnlyLocalReviewFixtureGenerator::generate($parserOutput)`.
3. Проверить, что fixture содержит пустые `review` blocks.
4. Симулировать manual review изменением fixture array в памяти.
5. Вызвать `DbReadOnlyLocalApprovalFixtureBridge::applyFixture($editedFixture)`.
6. Проверить output approval flow:
   - `updated_proposals`;
   - `approval_audit`;
   - `approval_summary`.

Проверка не должна писать fixture JSON files на диск.

## Input Parser Output for Check

Минимальный `parserOutput` для contract check должен содержать 4 proposals со статусами:

- `proposed`;
- `proposed`;
- `unknown`;
- `needs_review`.

Входной parser output не должен содержать:

- `approved`;
- `rejected`.

Каждый proposal должен содержать parser-owned fields, достаточные для bridge / approval flow:

- `proposal_id`;
- `product_id`;
- `attribute_id`;
- `target_attribute_id`;
- `original_raw_value`;
- `parsed_value`;
- `proposed_normalized_value`;
- `proposed_unit`;
- `parser_confidence`;
- `parser_warnings`;
- `approval_status`;
- `source`.

## Expected Generator Facts

После `generate($parserOutput)` ожидаются facts:

- `fixture_type = db_readonly_normalization_review`;
- `generator_mode = standalone_local_review_fixture_generation`;
- `proposals_count = 4`;
- `review_blocks_created_count = 4`;
- все `review.action` пустые до manual edit;
- `approved_count = 0`;
- `rejected_count = 0`;
- `writes_files = 0`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`.

Generator не должен:

- pre-approve proposals;
- создавать `rejected`;
- менять parser-owned proposal semantics;
- вызывать bridge;
- вызывать approval flow.

## Manual Review Simulation

Manual review в contract check должен симулироваться только изменением fixture array в памяти.

Пример безопасной симуляции:

- proposal A: `review.action = approve`;
- proposal B: `review.action = reject`;
- proposal C: `review.action = mark_needs_review`;
- proposal D: `review.action = ""`.

Для изменённых rows можно заполнить:

- `review.reviewer`;
- `review.review_note`.

Пустой `review.action` означает:

```text
no review action
```

и не должен менять исходный proposal status.

## Expected Bridge Facts

После `applyFixture($editedFixture)` bridge diagnostics должны показывать:

- `proposals_count = 4`;
- `review_actions_count = 3`;
- `skipped_empty_actions_count = 1`;
- `bridge_mode = standalone_local_fixture_bridge`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`.

Bridge не должен напрямую выставлять `approval_status`.

Bridge должен только:

- собрать `$proposals`;
- собрать `$reviewActions` из rows с непустым `review.action`;
- передать данные в approval flow.

## Expected Approval Flow Facts

Approval flow output должен подтверждать explicit status transitions:

- `total_proposals = 4`;
- `approved_count = 1`;
- `rejected_count = 1`;
- `needs_review_count >= 1`;
- `changed_count = 3`;
- `approval_audit_count = 3`;
- empty `review.action` не меняет status;
- `approved` / `rejected` созданы только approval flow.

`approval_audit` должен содержать для changed rows:

- `proposal_id`;
- `review_action`;
- `reviewer`;
- `reviewed_at`;
- `review_note`;
- `previous_status`;
- `new_status`;
- `source`.

`approved` означает только:

```text
future SQL preview candidate eligibility
```

`approved` не означает:

- SQL apply;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

## Safety Boundary

Standalone E2E review flow check не должен:

- менять pipeline wiring;
- подключать parser к `analyze_values`;
- подключать generator к pipeline;
- подключать bridge к pipeline;
- подключать approval flow к SQL preview;
- создавать fixture JSON files;
- создавать `var` directory;
- менять `.gitignore`;
- генерировать SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать live DB;
- менять DB/schema;
- выполнять write/schema operations.

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

## Verification Plan for Future Check

Future implementation/check должен выполнять:

### PHP syntax checks

Проверить involved standalone classes через PHP 5.6:

```text
C:\php56\php.exe -l framework-standardization\src\Normalizer\DbReadOnlyNormalizationProposalParser.php
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalReviewFixtureGenerator.php
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalApprovalFixtureBridge.php
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyNormalizationApprovalFlow.php
```

### Standalone E2E temporary check

Проверку выполнять временным PHP snippet/file only.

Если создаётся temporary file:

- не commit-ить его;
- удалить после проверки.

Проверка должна подтвердить:

- parser output не содержит `approved` / `rejected`;
- generator создал 4 пустых review blocks;
- manual edit in memory создал 3 non-empty review actions;
- bridge передал 3 actions в approval flow;
- approval flow создал `approved_count = 1`;
- approval flow создал `rejected_count = 1`;
- `approval_audit_count = 3`;
- SQL не создан;
- apply plan не создан;
- `safe_to_apply = 0`.

### Default dry-run regression

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Ожидаемо:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

### DB-readonly runner regression

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Ожидаемо:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

### Git safety

Проверить:

- generated fixture JSON files не появились в `git status`;
- temporary PHP check file удалён;
- local runtime config не попал в git;
- dump files не попали в git;
- `.gitignore` не менялся, если это не было отдельным explicit step.

## Out of Scope

Вне scope текущего шага:

- PHP implementation;
- adding runner;
- adding test framework;
- writing fixture JSON files;
- creating `var` directory;
- changing `.gitignore`;
- pipeline wiring;
- SQL preview integration;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- DB/schema changes;
- HANDOFF update;
- DECISIONS update;
- RUNTIME_CHECKS update.

## References

Specs:

- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`;
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`;
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`.

Project records:

- `docs/DECISIONS.md`;
- `docs/RUNTIME_CHECKS.md`.

## Recommended Boundary

Standalone E2E review flow check является только contract check для standalone components.

Он не должен становиться:

- pipeline stage;
- SQL preview input;
- production storage;
- apply layer.
