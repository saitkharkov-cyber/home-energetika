# DB-readonly Local Review Fixture Loader Spec

Mini-spec для будущего standalone loader-а, который сможет читать local review fixture JSON file из ignored path и возвращать PHP array для последующей standalone обработки.

Реализацию в рамках этого шага не делать.

## Purpose

Standalone local review fixture loader нужен только для local human review workflow:

```text
writer output JSON file
-> manual review/edit
-> loader
-> PHP array
-> bridge
-> approval flow
```

Loader должен закрывать безопасную boundary между:

```text
manual edited JSON file -> PHP array -> DbReadOnlyLocalApprovalFixtureBridge
```

Loader не должен превращать local fixture file в production storage, pipeline input или SQL/apply input.

## Current Context

Уже существуют:

- standalone parser;
- standalone local review fixture generator;
- standalone local review fixture writer;
- standalone local approval fixture bridge;
- standalone approval flow.

Writer записывает JSON-ready review fixture array в:

```text
framework-standardization/var/review-fixtures/*.json
```

Bridge принимает fixture как PHP array.

Будущий loader должен только читать reviewed JSON fixture из local ignored path и возвращать PHP array.

## Future Class

Предлагаемый future class:

```text
src/Approval/DbReadOnlyLocalReviewFixtureLoader.php
```

Минимальный future API:

```php
__construct($baseDir = null)
load($filename)
```

Default `baseDir`:

```text
framework-standardization/var/review-fixtures
```

Future implementation должен вычислять default `baseDir` относительно проекта/текущего файла, а не зависеть от текущей рабочей директории, насколько это возможно.

## Standalone Boundary

Loader должен быть standalone only.

Loader не является:

- pipeline stage;
- production storage;
- DB storage;
- SQL preview input by default;
- SQL/apply layer;
- OpenCart runtime.

Loader не должен менять pipeline wiring, runners или default dry-run path.

## Input Boundary

Loader принимает только local filename, а не arbitrary path.

Разрешено читать только из:

```text
framework-standardization/var/review-fixtures/
```

Запрещено:

- принимать absolute paths;
- принимать path traversal;
- принимать path separators;
- читать из `docs`;
- читать из `src`;
- читать из `config`;
- читать из project root;
- читать SQL/apply-like filenames.

Path traversal / absolute examples, которые должны быть rejected:

```text
../
..\
/
\
C:\
```

Разрешены только `.json` files.

Unsafe filename tokens:

```text
.sql
apply
production
migration
patch
```

Filename с такими tokens должен быть rejected, потому что он выглядит как SQL/apply или production artifact.

## Output Boundary

Loader должен возвращать result array.

Минимальный output shape:

- `loaded`;
- `fixture`;
- `loader_diagnostics`;
- `errors`;
- `warnings`;
- `source`.

Если файл успешно прочитан и JSON decoded в PHP array:

```text
loaded = 1
```

Если чтение или decode не выполнены:

```text
loaded = 0
fixture = null
```

## Loader Diagnostics

`loader_diagnostics` должен включать:

- `loader_mode = standalone_local_review_fixture_loader`;
- `target_dir`;
- `target_file`;
- `loaded_file`;
- `bytes_read`;
- `fixture_type`;
- `proposals_count`;
- `reads_files`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- `git_ignored_expected = 1`.

`reads_files` может быть `1` только если файл реально прочитан.

Если validation fails before reading:

```text
reads_files = 0
loaded_file = 0
bytes_read = 0
```

## JSON / Content Safety

Loader должен:

- читать только JSON;
- декодировать JSON в PHP array;
- не выполнять содержимое файла;
- не интерпретировать SQL;
- не запускать PHP;
- не запускать shell;
- не создавать SQL;
- не создавать SQL files;
- не создавать SQL diff;
- не создавать apply plan;
- не выполнять SQL apply;
- не менять `approval_status`;
- не создавать `approved`;
- не создавать `rejected`.

Если JSON invalid или структура не похожа на review fixture, loader должен вернуть error и не выполнять side effects.

Минимальная ожидаемая fixture-like структура:

- top-level array;
- `fixture_type`;
- `proposals` as array.

Future implementation может быть осторожной: invalid structure должна быть error/warning, но не должна запускать bridge или approval flow автоматически.

## Relationship With Bridge

Loader может быть future input source для:

```php
DbReadOnlyLocalApprovalFixtureBridge::applyFixture($fixture)
```

Но loader не должен сам вызывать bridge.

Loader не должен:

- вызывать approval flow;
- принимать approval decisions;
- менять `review.action`;
- менять proposal statuses;
- считать `approved` apply-ready data;
- менять `safe_to_apply`;
- создавать SQL/apply output.

Status transitions остаются ответственностью standalone approval flow.

## Git Boundary

Local review fixture JSON files должны оставаться local ignored artifacts.

Expected ignore boundary:

```text
framework-standardization/var/review-fixtures/*.json
```

Future implementation/check должен подтверждать:

- loaded fixture file не staged;
- loaded fixture file не tracked;
- generated/loaded review fixtures не коммитятся по умолчанию;
- local dump facts не попадают в git.

## Safety Boundary

Loader не должен:

- подключаться к pipeline;
- подключаться к runners;
- подключаться к SQL preview;
- использовать DB;
- использовать live DB;
- менять DB/schema;
- выполнять write/schema operations;
- генерировать executable SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- создавать OpenCart module runtime.

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

## Verification Plan for Future Implementation

После future implementation loader-а выполнить:

### Syntax check

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalReviewFixtureLoader.php
```

Expected:

```text
No syntax errors detected
```

### Valid local JSON load

Проверить:

- valid local review JSON loads successfully;
- `loaded = 1`;
- `fixture` is PHP array;
- `loader_mode = standalone_local_review_fixture_loader`;
- `reads_files = 1`;
- `bytes_read > 0`;
- `fixture_type = db_readonly_normalization_review`;
- `proposals_count` заполнен.

### Invalid input checks

Проверить:

- invalid JSON returns error;
- unsafe filenames rejected;
- path traversal rejected;
- absolute paths rejected;
- filenames with path separators rejected;
- non-json filenames rejected;
- SQL/apply-like filenames rejected.

### Bridge compatibility

Проверить:

- loaded fixture can be passed to bridge as PHP array;
- loader itself does not call bridge;
- loader itself does not call approval flow;
- loader does not change `review.action`;
- loader does not change `approval_status`.

### SQL/apply boundary

Проверить:

- SQL not generated;
- SQL files not created;
- SQL diff not created;
- apply plan not created;
- SQL apply not performed;
- `safe_to_apply = 0`;
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

- `git status` does not show generated/loaded fixture artifacts as tracked/staged;
- local review fixture JSON files remain ignored/local;
- local runtime config не попал в git;
- dump files не попали в git.

## Out of Scope

Вне scope текущего spec:

- PHP implementation;
- actual loader class;
- runtime checks;
- test framework;
- pipeline wiring;
- runner integration;
- SQL preview integration;
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

Related specs:

- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_WRITER_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_ARTIFACT_STORAGE_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`;
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`;
- `docs/DB_READONLY_STANDALONE_REVIEW_FLOW_CHECK_SPEC.md`.

Project records:

- `docs/DECISIONS.md`;
- `docs/RUNTIME_CHECKS.md`.

## Recommended Boundary

Loader is only a standalone local JSON reader for human review fixture artifacts.

It must not become:

- production storage;
- pipeline stage;
- SQL preview input;
- apply layer.
