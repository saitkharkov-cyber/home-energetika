# DB-readonly Local Review Fixture Generation Spec

Mini-spec для future standalone generation/export шага, который превращает standalone parser output в local JSON review fixture для человека.

Документ описывает безопасный процесс подготовки review artifact, который позже может быть загружен как PHP array и передан в `DbReadOnlyLocalApprovalFixtureBridge::applyFixture($fixture)`.

Реализацию в рамках этого шага не делать.

## Purpose

Local review fixture generation нужен, чтобы подготовить human-reviewable JSON из normalization proposals, созданных standalone parser-ом.

Этот step нужен только для:

- чтения standalone parser output;
- сохранения proposal facts в review-friendly JSON shape;
- добавления пустого reviewer-owned `review` block для каждого proposal;
- подготовки local artifact для manual review;
- последующей передачи reviewed fixture в standalone bridge.

Этот step не является:

- pipeline stage;
- SQL preview;
- apply layer;
- approval flow;
- production storage;
- DB storage.

Главная идея:

```text
standalone parser output -> local review JSON fixture -> human review -> standalone fixture bridge -> standalone approval flow
```

## Current Context

Уже есть:

- standalone `DbReadOnlyNormalizationProposalParser`;
- parser создаёт `normalization_value_proposals`;
- parser может выставлять только `proposed`, `needs_review`, `unknown`;
- parser не выставляет `approved` или `rejected`;
- standalone `DbReadOnlyLocalApprovalFixtureBridge`;
- bridge умеет принимать JSON-shaped fixture как PHP array;
- bridge передаёт review actions в standalone `DbReadOnlyNormalizationApprovalFlow`;
- SQL/apply запрещены до отдельной production SQL/apply architecture.

Сейчас не хватает безопасно описанного generation/export процесса:

```text
parser output -> local review fixture
```

Реальные local dump fixture files не должны попадать в git по умолчанию.

## Input Contract

Input для будущего generator-а:

```text
standalone parser output
```

Минимальные input keys:

- `normalization_value_proposals`;
- `parser_diagnostics`;
- `source`.

`normalization_value_proposals` должен быть массивом proposal rows.

Generator не должен менять parser output semantics.

## Output Contract

Output future generator-а:

```text
local JSON review fixture
```

Минимальный top-level shape:

```text
source
fixture_type = db_readonly_normalization_review
generated_at
generator_mode = standalone_local_review_fixture_generation
proposals[]
```

`generated_at` является metadata для review artifact.

`generator_mode` должен явно показывать, что artifact создан standalone generation step, а не pipeline или SQL/apply layer.

## Proposal Row Shape

Каждый `proposals[]` row должен содержать parser-owned proposal data:

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

Каждый row также должен содержать reviewer-owned block:

```text
review.action
review.reviewer
review.review_note
```

Минимальный JSON example:

```text
{
  "source": "local_dump_db_readonly",
  "fixture_type": "db_readonly_normalization_review",
  "generated_at": "2026-07-06T00:00:00+00:00",
  "generator_mode": "standalone_local_review_fixture_generation",
  "proposals": [
    {
      "proposal_id": "...",
      "product_id": 1068,
      "attribute_id": 44,
      "target_attribute_id": 44,
      "original_raw_value": "75 мм",
      "parsed_value": {},
      "proposed_normalized_value": "75",
      "proposed_unit": "mm",
      "parser_confidence": "high",
      "parser_warnings": [],
      "approval_status": "proposed",
      "source": "local_dump_db_readonly",
      "review": {
        "action": "",
        "reviewer": "",
        "review_note": ""
      }
    }
  ]
}
```

## Review Block Rules

Generator должен создать пустой `review` block для каждого proposal.

Начальные значения:

```text
review.action = ""
review.reviewer = ""
review.review_note = ""
```

Generator не должен:

- pre-approve proposals;
- выставлять `approved`;
- выставлять `rejected`;
- заполнять reviewer identity;
- писать review notes;
- менять `approval_status`.

Review fields должны заполняться только человеком или future explicit review tool.

## File / Output Boundary

Generated fixture files являются local review artifacts.

Они не являются:

- production storage;
- DB storage;
- SQL file;
- SQL diff;
- apply plan;
- pipeline input by default;
- SQL preview input by default.

Generated fixture files не должны попадать в git по умолчанию, особенно если они содержат local dump facts.

Рекомендуемый local-only путь для future implementation:

```text
framework-standardization/var/review-fixtures/*.json
```

Альтернативно можно использовать другое local-only место, если оно лучше соответствует проектной структуре.

Если future implementation выбирает `var/review-fixtures`, то создание директории, `.gitignore` rules и actual fixture files должно быть отдельным implementation step.

В рамках этого spec:

- не создавать `var`;
- не создавать `.gitignore`;
- не создавать fixture JSON files.

## Safety Boundary

Generator не должен:

- создавать `approved`;
- создавать `rejected`;
- менять proposals;
- менять parser diagnostics;
- выполнять approval flow;
- вызывать `DbReadOnlyLocalApprovalFixtureBridge`;
- вызывать SQL preview;
- менять `safe_to_apply`;
- менять `statements`;
- генерировать SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать live DB;
- выполнять write/schema operations;
- менять pipeline wiring;
- менять runners;
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

## Bridge Relation

Generated fixture может позже быть:

1. отредактирован человеком в `review` blocks;
2. загружен как PHP array;
3. передан в:

```php
$bridge->applyFixture($fixture)
```

`DbReadOnlyLocalApprovalFixtureBridge` должен:

- читать `proposals[]`;
- читать `review.action`;
- превращать непустые `review.action` rows в `$reviewActions`;
- передавать `$proposals` и `$reviewActions` в approval flow.

Status transitions принадлежат только:

```text
DbReadOnlyNormalizationApprovalFlow
```

Generator не должен выполнять status transitions.

## Verification Plan для будущей реализации

После future implementation local review fixture generation проверить:

### Fixture generation contract

- generated fixture содержит все parser proposals;
- top-level `source` заполнен;
- top-level `fixture_type = db_readonly_normalization_review`;
- top-level `generated_at` заполнен;
- top-level `generator_mode = standalone_local_review_fixture_generation`;
- `review` block есть для каждого proposal;
- `review.action` пустой для каждого proposal;
- `review.reviewer` пустой для каждого proposal;
- `review.review_note` пустой для каждого proposal.

### Approval boundary

- `approved_count = 0` на generation stage;
- `rejected_count = 0` на generation stage;
- generator не создаёт `approved`;
- generator не создаёт `rejected`;
- generator не вызывает approval flow;
- generator не вызывает bridge.

### SQL/apply boundary

- fixture не содержит SQL;
- fixture не содержит SQL diff;
- fixture не содержит apply plan;
- `safe_to_apply` не становится `1`;
- `statements` не появляются;
- SQL files не создаются;
- SQL apply не выполняется;
- live DB не используется.

### File safety

- fixture path local ignored или не staged;
- generated local review fixtures не попали в git;
- local runtime config не попал в git;
- dump files не попали в git.

### Regression

Default dry-run:

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

DB-readonly runner:

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

## Out of Scope

Вне scope:

- PHP implementation;
- actual fixture file creation;
- `.gitignore` changes;
- `var` directory creation;
- pipeline wiring;
- parser changes;
- approval flow changes;
- bridge changes;
- SQL preview changes;
- report/framework result changes;
- runners changes;
- default dry-run path changes;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- DB/schema changes;
- write/schema operations;
- HANDOFF update;
- DECISIONS update;
- RUNTIME_CHECKS update.

## References

Specs:

- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`

Project records:

- `docs/DECISIONS.md`
- `docs/RUNTIME_CHECKS.md`

## Recommended Boundary

Fixture generation creates:

```text
human-reviewable local JSON artifact from parser proposals only
```

It does not:

```text
approve
reject
normalize further
preview SQL
generate SQL
create SQL files
create SQL diff
create apply plan
apply anything
```
