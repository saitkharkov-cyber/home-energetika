# DB-readonly Normalization Approval Flow Spec

Mini-spec для future explicit approval flow поверх standalone normalization proposals.

Документ описывает, как normalization proposals в будущем могут получать explicit approval/rejection контролируемо и трассируемо, не превращаясь автоматически в SQL/apply output.

Реализацию в рамках этого шага не делать.

## Purpose

Определить слой review/approval между:

```text
standalone normalization proposals
```

и:

```text
future SQL preview candidates
```

Главная идея:

```text
parser создаёт proposals, approval flow принимает review decisions.
```

Этот spec не разрешает SQL generation, SQL files, apply plan или SQL apply.

## Current Context

Текущее состояние:

- `DbReadOnlyNormalizationProposalParser` существует standalone;
- parser может создавать только `proposed`, `needs_review`, `unknown`;
- parser не выставляет `approved`;
- parser не выставляет `rejected`;
- parser не подключён к pipeline;
- SQL/apply запрещены до отдельной production SQL/apply architecture.

## Why Approval Flow Is Separate From Parser

Parser должен быть deterministic technical layer.

Он может:

- прочитать raw value;
- найти number-like fragments;
- распознать `мм` / `mm`;
- определить parser warnings;
- создать proposal.

Но parser не должен решать:

- правильно ли normalized value для production;
- допустимы ли parser warnings;
- можно ли использовать proposal как SQL preview candidate;
- кто несёт ответственность за decision.

Approval flow должен быть отдельным, потому что approval является controlled review decision, а не parsing result.

## Review Input Data

Для review нужны данные, достаточные для traceability и ручной проверки.

Минимальный input:

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
- `current approval_status`;
- `examples` / grouped raw values.

Дополнительно могут быть полезны:

- `canonical_code`;
- `language_id`;
- `source`;
- `created_at`;
- `parser_version`;
- `proposal_group_key`.

## Allowed Actions

Approval flow может поддерживать actions:

```text
approve
reject
mark_needs_review
mark_unknown
reset_to_proposed
```

### approve

Переводит proposal в:

```text
approved
```

Только этот action может создать `approved` status.

### reject

Переводит proposal в:

```text
rejected
```

Rejected proposal не является SQL preview input.

### mark_needs_review

Переводит proposal в:

```text
needs_review
```

Используется для ambiguous cases или недостаточной уверенности.

### mark_unknown

Переводит proposal в:

```text
unknown
```

Используется, когда value нельзя безопасно интерпретировать.

### reset_to_proposed

Переводит proposal обратно в:

```text
proposed
```

Используется для сброса manual decision перед повторным review.

## Approval Statuses

Approval flow может выставлять статусы:

```text
approved
rejected
needs_review
unknown
proposed
```

Status должен быть explicit field:

```text
approval_status
```

Status не должен выводиться автоматически из parser confidence.

## Audit / Storage Fields

Каждое review action должно сохранять audit trail.

Минимальные поля:

- `approved_by` или `reviewer`;
- `approved_at` / `reviewed_at`;
- `review_note`;
- `previous_status`;
- `new_status`;
- `source`.

Дополнительно можно хранить:

- `review_action`;
- `reviewer_role`;
- `proposal_id`;
- `proposal_hash`;
- `parser_version`;
- `approval_source_file`;
- `created_at`.

Для action кроме `approve` поле `approved_by` может быть пустым, но `reviewer` и `reviewed_at` должны быть заполнены.

## Approval Boundary

Обязательная граница:

```text
parser cannot approve
only approval flow can approve
```

Только proposal со статусом:

```text
approved
```

может стать input для future SQL preview.

Не являются SQL preview input:

- `rejected`;
- `needs_review`;
- `unknown`;
- `proposed`;
- parser diagnostics;
- raw_profile diagnostics;
- suspicious diagnostics.

Approved proposal не означает SQL apply.

Approved proposal означает только:

```text
eligible for future SQL preview candidate selection
```

Для SQL preview/apply нужен отдельный production SQL/apply spec и отдельное architecture decision.

## Storage Boundary

В этом spec не решается, где хранится approval.

Возможные варианты для future steps:

- local JSON approval fixture;
- local YAML approval fixture;
- reviewed CSV;
- future DB approval table.

Текущая граница:

- live DB запрещена;
- write/schema operations запрещены;
- SQL apply запрещён;
- DB approval table не создавать в этом шаге;
- executable SQL files не создавать.

Если future implementation использует local file fixture, это должен быть отдельный explicit step с проверкой, что fixture не является SQL/apply output.

## Report / Framework Result Boundary

Report и framework result могут в будущем показывать approval summaries:

- proposal count;
- approved count;
- rejected count;
- needs_review count;
- unknown count;
- proposed count;
- reviewer/source summary;
- examples.

Но report/framework result не должны:

- считать `production_ready = 1`;
- менять `safe_to_apply`;
- менять `statements`;
- создавать SQL;
- создавать apply plan;
- выполнять SQL apply.

Approval counts являются visibility/reporting facts, а не apply command.

## Future SQL Preview Boundary

Future SQL preview может рассматривать только approved proposals как candidate input.

Но даже в future SQL preview:

- `approved` не должен автоматически означать `safe_to_apply = 1`;
- SQL diff/generation требует отдельной architecture;
- apply safety требует отдельной architecture;
- SQL apply требует отдельного explicit command/path.

Текущий DB-readonly SQL preview должен оставаться blocked до отдельного production SQL/apply step:

```text
generated = 0
safe_to_apply = 0
apply_changes = 0
statements = array()
blocked_by contains db_readonly_sql_preview_not_implemented
```

## Verification Plan для будущей реализации

После future implementation approval flow проверить:

### Parser boundary

- parser не выставляет `approved`;
- parser не выставляет `rejected`;
- parser output остаётся только `proposed`, `needs_review`, `unknown`.

### Approval flow behavior

- approval flow может изменить `proposed -> approved`;
- approval flow может изменить `proposed -> rejected`;
- approval flow может изменить `needs_review -> approved` только explicit action;
- approval flow сохраняет `previous_status`;
- approval flow сохраняет `new_status`;
- approval flow сохраняет `reviewer`;
- approval flow сохраняет `reviewed_at`;
- approval flow сохраняет `review_note`.

### SQL/apply boundary

- rejected не попадает в future SQL preview input;
- `needs_review` не попадает в future SQL preview input;
- `unknown` не попадает в future SQL preview input;
- `proposed` не попадает в future SQL preview input;
- only approved count может быть future SQL preview candidate count;
- `safe_to_apply` остаётся `0`;
- `statements` остаётся `array()`;
- SQL не создаётся;
- SQL files не создаются;
- apply plan не создаётся;
- SQL apply не выполняется.

### Regression

Выполнить:

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

Выполнить:

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

- implementation;
- pipeline wiring;
- SQL preview changes;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- write/schema operations;
- HANDOFF update.

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

## References

Specs:

- `docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`

Project records:

- `docs/DECISIONS.md`
- `docs/RUNTIME_CHECKS.md`

## Recommended Boundary

Approval flow is a controlled review layer.

It may turn proposals into:

```text
approved
rejected
needs_review
unknown
proposed
```

It must not turn proposals directly into:

```text
SQL statements
SQL files
apply plan
safe-to-apply result
SQL apply
production-ready output
```
