# DB-readonly Local Approval Fixture Spec

Mini-spec для future/local fixture bridge между standalone normalization proposal parser и standalone approval flow.

Документ описывает, как можно передавать standalone normalization proposals на manual review и возвращать explicit review actions в standalone approval flow без pipeline wiring, SQL/apply и live DB.

Реализацию в рамках этого шага не делать.

## Purpose

Local approval fixture / manual review bridge нужен как безопасный промежуточный слой между:

```text
DbReadOnlyNormalizationProposalParser
```

и:

```text
DbReadOnlyNormalizationApprovalFlow
```

Главная идея:

```text
parser creates proposals -> local fixture exposes proposals for review -> reviewer writes explicit actions -> approval flow applies actions
```

Bridge не должен становиться pipeline stage, production storage, SQL/apply layer или DB schema.

## Current Context

Текущее состояние:

- `DbReadOnlyNormalizationProposalParser` существует standalone;
- parser не подключён к pipeline;
- parser создаёт только `proposed`, `needs_review`, `unknown`;
- parser не создаёт `approved` или `rejected`;
- `DbReadOnlyNormalizationApprovalFlow` существует standalone;
- approval flow не подключён к pipeline;
- approval flow может явно переводить proposals в `approved`, `rejected`, `needs_review`, `unknown`, `proposed`;
- SQL/apply запрещены до отдельной production SQL/apply architecture.

`approved` proposal означает только:

```text
eligible for future SQL preview candidate selection
```

`approved` proposal не означает:

- SQL apply;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

## Why Local Fixture Bridge Is Needed

Parser output является technical proposal output, а не review decision.

Approval flow требует explicit review actions:

```text
approve
reject
mark_needs_review
mark_unknown
reset_to_proposed
```

Local fixture bridge нужен, чтобы:

- сохранить proposals в форме, удобной для ручной проверки;
- отделить parser-generated fields от reviewer-owned fields;
- дать reviewer-у контролируемое место для explicit actions;
- передать review actions в `DbReadOnlyNormalizationApprovalFlow::apply($proposals, $reviewActions)`;
- сохранить boundary: review artifact не является SQL/apply output.

## Review Fixture Proposal Data

В review fixture должны попадать parser output fields, достаточные для traceability и manual review:

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

Допустимые дополнительные read-only поля для удобства review:

- `language_id`;
- `canonical_code`;
- `parser_version`;
- `proposal_group_key`;
- `example_product_ids`;
- `created_at`.

Дополнительные поля не должны превращать fixture в production storage.

## Recommended Fixture Format

Рекомендуемый первый safe format:

```text
JSON
```

Причины:

- JSON естественно хранит nested fields вроде `parsed_value` и `parser_warnings`;
- JSON сохраняет типы массивов лучше, чем CSV;
- JSON проще напрямую преобразовать в PHP arrays для standalone manual check;
- JSON снижает риск потери parser diagnostics из-за колонок/escaping;
- JSON не выглядит как SQL file и не является executable artifact.

CSV можно рассмотреть позже для review workflow, если понадобится табличное редактирование.

CSV не рекомендуется как первый формат, потому что `parsed_value` и `parser_warnings` потребуют serialization внутри ячеек и повышают риск ручной порчи parser-owned fields.

## Proposed JSON Shape

Минимальная безопасная структура fixture:

```text
{
  "source": "local_dump_db_readonly",
  "fixture_type": "db_readonly_normalization_review",
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

`review` block является reviewer-owned area.

Parser-owned fields должны оставаться read-only для reviewer-а.

## Reviewer-editable Fields

Reviewer может менять только:

- `review.action`;
- `review.reviewer`;
- `review.review_note`.

Допустимые значения `review.action`:

```text
approve
reject
mark_needs_review
mark_unknown
reset_to_proposed
```

Пустой `review.action` означает:

```text
no review action
```

Пустые actions не должны менять status proposal-а.

## Fields Not Editable By Hand

Следующие поля нельзя менять руками:

- `proposal_id`;
- `product_id`;
- `attribute_id`;
- `target_attribute_id`;
- `original_raw_value`;
- `parsed_value`;
- `parser_warnings`.

Также не рекомендуется менять руками:

- `proposed_normalized_value`;
- `proposed_unit`;
- `parser_confidence`;
- `approval_status`;
- `source`.

Если reviewer считает parser output неверным, он должен использовать:

```text
reject
mark_needs_review
mark_unknown
```

или оставить `review_note`.

Ручное исправление parser-owned fields должно быть отдельным future spec, потому что оно меняет traceability и может потребовать parser override model.

## Bridge to Approval Flow

Bridge должен преобразовать fixture rows в два массива:

```text
$proposals
$reviewActions
```

`$proposals` должен содержать original proposal data из fixture.

`$reviewActions` должен содержать только rows с непустым `review.action`.

Минимальный action item:

```text
proposal_id
action
reviewer
review_note
source
```

Затем bridge вызывает standalone approval flow:

```php
$approvalFlow->apply($proposals, $reviewActions)
```

Output approval flow:

- `updated_proposals`;
- `approval_audit`;
- `approval_summary`;
- `errors`;
- `warnings`;
- `source`.

Bridge не должен напрямую выставлять statuses. Status transitions должен выполнять approval flow.

## Boundary

Local approval fixture:

- не является DB storage;
- не является production storage;
- не является SQL file;
- не является SQL diff;
- не является apply plan;
- не является pipeline stage;
- не является SQL preview input сам по себе.

`approved` в fixture или после approval flow не означает:

- SQL apply;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- executable SQL;
- apply-ready output.

Только future SQL preview architecture может решить, как читать approved proposals как candidate input.

Даже future SQL preview candidate input не должен автоматически означать SQL apply.

## Safety Rules

Bridge не должен:

- менять pipeline wiring;
- подключать parser к `analyze_values`;
- подключать approval flow к SQL preview;
- менять `DbReadOnlySqlPreviewBuilder`;
- менять report/framework result;
- менять runners;
- менять default dry-run path;
- использовать live DB;
- создавать DB tables;
- выполнять write/schema operations;
- генерировать SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply.

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

## Verification Plan для будущей реализации

После future implementation local approval fixture bridge проверить:

### Fixture content

- fixture содержит proposals со статусами `proposed`, `needs_review`, `unknown`;
- fixture содержит required proposal fields;
- fixture содержит reviewer-owned fields `action`, `reviewer`, `review_note`;
- parser-owned fields не изменяются bridge-ом;
- fixture не является SQL file;
- fixture не содержит executable SQL.

### Review actions

- `approve` через fixture создаёт `approved` только через approval flow;
- `reject` через fixture создаёт `rejected` только через approval flow;
- `mark_needs_review` создаёт `needs_review`;
- `mark_unknown` создаёт `unknown`;
- `reset_to_proposed` создаёт `proposed`;
- пустой `action` не меняет proposal status;
- invalid action возвращает error/warning, но не создаёт SQL/apply output.

### Approval audit

- audit содержит `proposal_id`;
- audit содержит `review_action`;
- audit содержит `reviewer`;
- audit содержит `reviewed_at`;
- audit содержит `review_note`;
- audit содержит `previous_status`;
- audit содержит `new_status`;
- audit содержит `source`.

### SQL/apply boundary

- `safe_to_apply` остаётся `0`;
- `production_ready` остаётся `0`;
- `sql_apply_allowed` остаётся `0`;
- SQL не генерируется;
- SQL files не создаются;
- SQL diff не создаётся;
- apply plan не создаётся;
- SQL apply не выполняется;
- live DB не используется.

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

### Git safety

Проверить:

- local runtime config не попал в git;
- dump files не попали в git;
- generated local review fixtures не попали в git, если они содержат local dump facts и не предназначены для commit;
- PHP-код менялся только в рамках будущего explicit implementation step.

## Out of Scope

Вне scope:

- PHP implementation;
- fixture file creation в текущем шаге;
- pipeline wiring;
- подключение parser к `analyze_values`;
- подключение approval flow к SQL preview;
- изменение `DbReadOnlySqlPreviewBuilder`;
- изменение report/framework result;
- изменение runners;
- изменение default dry-run path;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- DB/schema changes;
- OpenCart module runtime;
- HANDOFF update.

## References

Specs:

- `docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`

Project records:

- `docs/DECISIONS.md`
- `docs/RUNTIME_CHECKS.md`

## Recommended Boundary

Local approval fixture / manual review bridge должен быть только:

```text
local review artifact/process between standalone parser and standalone approval flow
```

Он не должен становиться:

```text
production storage
SQL/apply layer
pipeline stage
DB schema
SQL preview implementation
```
