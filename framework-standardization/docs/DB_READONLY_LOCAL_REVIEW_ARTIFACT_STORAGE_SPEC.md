# DB-readonly Local Review Artifact Storage Spec

Mini-spec для безопасной future storage boundary вокруг generated local review fixture files.

Документ описывает только границу будущей записи/чтения local review JSON files. Реализацию в рамках этого шага не делать.

## Purpose

Local review artifact storage boundary нужен перед тем, как разрешать запись generated review fixtures на диск.

Этот boundary нужен только для future safe operations:

- writing local review JSON files;
- loading reviewed local JSON files;
- passing loaded fixture arrays into standalone approval bridge.

Он не является:

- production storage;
- DB storage;
- pipeline stage;
- SQL preview input by default;
- SQL/apply layer.

Главная цель - сохранить generated review fixtures как local human-review artifacts, а не как production data или apply-ready source.

## Current Context

Уже есть:

- `DbReadOnlyLocalReviewFixtureGenerator`, который возвращает JSON-ready PHP array fixture;
- `DbReadOnlyLocalApprovalFixtureBridge`, который принимает fixture как PHP array;
- standalone E2E review flow, проверенный in memory;
- запрет на SQL generation / SQL apply / live DB.

Следующий production-facing шаг не должен сразу писать files без storage boundary, потому что real review fixture JSON files могут содержать local dump facts:

- `product_id`;
- `attribute_id`;
- `target_attribute_id`;
- `original_raw_value`;
- parser diagnostics;
- review actions / notes.

Такие файлы не должны попадать в git по умолчанию.

## Recommended Local Path

Рекомендуемый local-only path для future implementation:

```text
framework-standardization/var/review-fixtures/*.json
```

Этот путь должен рассматриваться как место для runtime/local review artifacts.

В этом spec:

- `var` directory не создавать;
- fixture JSON files не создавать;
- `.gitignore` не менять.

## Why `var/review-fixtures`

`framework-standardization/var/review-fixtures` подходит для первого safe step, потому что:

- это local runtime artifacts, а не source code;
- путь отделён от `docs`, `src` и `config`;
- его удобно защитить через `.gitignore`;
- он не выглядит как SQL/apply artifact;
- его можно очищать без влияния на source code;
- он явно показывает review-fixture назначение файлов.

Review fixture files не должны жить в `docs`, потому что реальные fixture files могут содержать local dump facts.

Review fixture files не должны жить в `src`, потому что они не являются PHP source.

Review fixture files не должны жить в `config`, потому что они не являются runtime config.

## Git Boundary

Generated review fixture JSON files must not be committed by default.

Обязательные правила:

- local dump facts must not enter git;
- future implementation должен перед file-writing step проверить `.gitignore`;
- если `.gitignore` нужно менять, это должен быть отдельный маленький explicit step;
- actual fixture files не должны попадать в git status как staged/tracked files;
- generated review fixtures не должны становиться test fixtures без отдельной sanitization policy.

Future file-writing implementation должен завершаться проверкой:

```text
git status
```

и подтверждать, что generated fixture JSON files не tracked и не staged.

## Allowed Future Operations

Future implementation может, после отдельного explicit step:

- создать local `framework-standardization/var/review-fixtures` directory;
- записывать generated fixture JSON files локально;
- загружать reviewed fixture JSON files локально;
- декодировать reviewed JSON в PHP array;
- передавать loaded fixture array в `DbReadOnlyLocalApprovalFixtureBridge::applyFixture($fixture)`;
- удалять local fixture files после manual review/check.

Эти operations должны оставаться local-only и standalone.

## Forbidden Operations

Запрещено:

- commit generated fixture JSON files by default;
- treat fixture files as production storage;
- treat fixture files as DB storage;
- treat fixture files as SQL preview input by default;
- treat fixture files as apply plan;
- connect generator/bridge to pipeline;
- generate SQL;
- create SQL files;
- create SQL diff;
- create apply plan;
- execute SQL apply;
- use live DB;
- create DB/schema changes;
- perform write/schema operations.

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

## File Naming Guidance

Future local fixture filenames should be descriptive and non-executable.

Recommended pattern:

```text
pump_diameter_YYYYMMDD_HHMMSS.review.json
```

Allowed naming principles:

- include canonical/job marker, for example `pump_diameter`;
- include timestamp;
- include `.review.json`;
- keep extension `.json`.

Forbidden naming patterns:

- executable extensions;
- `.sql`;
- filenames that imply production/apply semantics.

Avoid words like:

```text
apply
production
sql
safe_to_apply
```

unless a future production SQL/apply architecture explicitly allows it.

## Data Safety

Review fixture JSON can contain read-only local dump facts:

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
- `review.action`;
- `review.reviewer`;
- `review.review_note`;
- `source`.

Because these fields may include local dump facts, default policy is:

```text
local-only / not committed
```

Future sample fixtures for docs/tests require a separate sanitized sample spec.

Sanitized samples must not be inferred from real local dump fixtures without an explicit sanitization step.

## Bridge Relation

Local fixture storage may support this future flow:

```text
generator output array
-> write local review JSON file
-> human edits review block
-> load reviewed JSON file as PHP array
-> DbReadOnlyLocalApprovalFixtureBridge::applyFixture($fixture)
-> approval flow applies explicit actions
```

Status transitions still belong only to:

```text
DbReadOnlyNormalizationApprovalFlow
```

Storage does not approve or reject proposals.

Storage does not make `approved` proposals apply-ready.

## Future Verification Plan

Future implementation of file writing/loading must verify:

### Git protection

- `.gitignore` protects `framework-standardization/var/review-fixtures/*.json`;
- generated fixture file appears locally but not as tracked/staged file;
- `git status` is clean except intentional source/doc changes;
- local runtime config and dump files do not enter git.

### Fixture safety

- generated fixture file has `.json` extension;
- fixture file contains no SQL statements;
- fixture file contains no SQL diff;
- fixture file contains no apply plan;
- fixture file is not named like SQL/apply output;
- loaded fixture can be passed as PHP array to bridge.

### Runtime regression

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

### SQL/apply boundary

Confirm:

- SQL generation not added;
- SQL files not created;
- SQL diff not created;
- apply plan not created;
- SQL apply not performed;
- live DB not used;
- DB/schema changes not performed.

## Out of Scope

Вне scope текущего шага:

- PHP implementation;
- actual file writing;
- actual fixture file creation;
- `.gitignore` change;
- `var` directory creation;
- runner/test framework;
- pipeline wiring;
- connecting generator/bridge to pipeline;
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

- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`;
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`;
- `docs/DB_READONLY_STANDALONE_REVIEW_FLOW_CHECK_SPEC.md`.

Project records:

- `docs/DECISIONS.md`;
- `docs/RUNTIME_CHECKS.md`.

## Recommended Boundary

Local review artifact storage is only a local file boundary for human review fixtures.

It must not become:

- production storage;
- pipeline input;
- SQL preview input;
- apply layer.
