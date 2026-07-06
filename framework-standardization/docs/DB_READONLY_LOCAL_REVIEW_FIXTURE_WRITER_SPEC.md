# DB-readonly Local Review Fixture Writer Spec

Mini-spec для будущего standalone writer-а, который сможет записывать JSON-ready review fixture array в local ignored path.

Документ описывает только безопасную future writer boundary. Реализацию в рамках этого шага не делать.

## Purpose

Standalone local review fixture writer нужен, чтобы безопасно записывать generated review fixture JSON files локально:

```text
framework-standardization/var/review-fixtures/*.json
```

Writer нужен только для local human review workflow:

```text
generator output array -> local review JSON file -> manual review -> load fixture -> bridge
```

Writer не является:

- production storage;
- DB storage;
- pipeline stage;
- SQL preview input by default;
- SQL/apply layer.

Writer не должен превращать fixture в production decision, apply-ready source или SQL preview input.

## Current Context

Уже есть:

- `DbReadOnlyLocalReviewFixtureGenerator`, который возвращает JSON-ready PHP array fixture;
- `DbReadOnlyLocalApprovalFixtureBridge`, который принимает fixture как PHP array;
- standalone E2E review flow, проверенный in memory;
- storage boundary для local review artifacts;
- `.gitignore` protection для future fixture JSON files.

`.gitignore` уже содержит:

```text
framework-standardization/var/review-fixtures/*.json
```

Actual writer ещё не реализован.

Actual fixture JSON files пока не создавать.

## Future Class Location

Рекомендуемый будущий класс:

```text
framework-standardization/src/Approval/DbReadOnlyLocalReviewFixtureWriter.php
```

Рекомендуемый namespace:

```text
FrameworkStandardization\Approval
```

Writer должен оставаться standalone component и не подключаться к pipeline.

## Input Contract

Future writer принимает:

- JSON-ready fixture array from `DbReadOnlyLocalReviewFixtureGenerator::generate($parserOutput)`;
- optional local filename / suggested filename.

Минимальный fixture input должен содержать:

- `source`;
- `fixture_type`;
- `generated_at`;
- `generator_mode`;
- `proposals[]`;
- `generator_diagnostics`;
- `errors`;
- `warnings`.

Writer не должен генерировать fixture content.

Writer не должен менять fixture semantics.

## Output Contract

Future writer должен возвращать writer result / diagnostics array.

Минимальный output:

- `written`;
- `writer_diagnostics`;
- `errors`;
- `warnings`;
- `source`.

Если файл успешно записан:

```text
written = 1
```

Если файл не записан:

```text
written = 0
```

## Minimal Future API

Минимальный public API:

```php
write($fixture, $filename = null)
```

`$fixture` должен быть PHP array.

`$filename` является optional local filename, а не path.

Если `$filename = null`, writer может сгенерировать безопасное имя.

## Path Boundary

Writer must write only under:

```text
framework-standardization/var/review-fixtures/
```

Allowed future behavior:

- writer may create target directory if missing;
- writer may write JSON file only inside target directory;
- writer may return absolute/resolved target path in diagnostics.

Required restrictions:

- writer must not write outside `framework-standardization/var/review-fixtures/`;
- writer must not accept absolute paths from `$filename`;
- writer must reject path traversal;
- writer must not write to `docs`;
- writer must not write to `src`;
- writer must not write to `config`;
- writer must not write to project root;
- writer must not overwrite existing files by default unless a separate explicit future option allows it.

Path traversal examples that must be rejected:

```text
../
..\
/
\
C:\
```

## Filename Boundary

Safe generated filename example:

```text
pump_diameter_YYYYMMDD_HHMMSS.review.json
```

Filename rules:

- only `.json` extension;
- recommended suffix `.review.json`;
- no executable extensions;
- no path separators;
- no absolute paths;
- no path traversal.

Reject or avoid filenames containing:

```text
.sql
apply
production
migration
patch
```

These words imply SQL/apply or production semantics and should not be used for local review fixture artifacts.

## Git Boundary

`.gitignore` already protects future generated review fixture JSON files:

```text
framework-standardization/var/review-fixtures/*.json
```

Future implementation must verify:

- generated file is ignored or at least not staged/tracked;
- generated fixture JSON files are not committed by default;
- local dump facts do not enter git;
- `git status` does not show generated fixture JSON files as staged/tracked files.

If `.gitignore` behavior is insufficient in future implementation, that must be handled as a separate explicit step.

## Safety Boundary

Writer must not:

- generate fixture content;
- modify fixture `approval_status`;
- approve proposals;
- reject proposals;
- call approval flow;
- call fixture bridge;
- call SQL preview;
- connect to pipeline;
- change runners;
- generate SQL;
- create SQL files;
- create SQL diff;
- create apply plan;
- execute SQL apply;
- use DB;
- use live DB;
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

Writer writes only a local JSON review artifact.

## Writer Diagnostics

Future writer diagnostics should include:

- `writer_mode = standalone_local_review_fixture_writer`;
- `target_dir`;
- `target_file`;
- `wrote_file`;
- `bytes_written`;
- `fixture_type`;
- `proposals_count`;
- `writes_files`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- `git_ignored_expected = 1`.

`writes_files` may be `1` only if a file was actually written.

If validation fails before writing:

```text
writes_files = 0
wrote_file = 0
bytes_written = 0
```

## JSON Encoding Boundary

Future writer should encode fixture as JSON for human review.

Requirements:

- preserve nested arrays;
- preserve parser-owned fields;
- preserve reviewer-owned `review` block;
- avoid executable formats;
- do not emit SQL;
- do not emit PHP code;
- do not emit shell scripts.

If JSON encoding fails, writer should return an error and must not create a partial apply-like artifact.

## Verification Plan for Future Implementation

### Syntax check

Future writer class:

```text
C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyLocalReviewFixtureWriter.php
```

Expected:

```text
No syntax errors detected
```

### Standalone writer check

Prepare small fixture array in memory.

Call:

```php
$writer->write($fixture, $filename);
```

Verify:

- file exists locally under `framework-standardization/var/review-fixtures/`;
- file extension is `.json`;
- filename is safe;
- writer result includes `writer_mode = standalone_local_review_fixture_writer`;
- `wrote_file = 1`;
- `writes_files = 1`;
- `bytes_written > 0`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- JSON decodes back to PHP array;
- decoded fixture can be passed to bridge as PHP array.

### Git safety

Verify:

- generated fixture is ignored or not staged/tracked;
- `git status` does not show generated fixture as tracked/staged file;
- generated fixture JSON files are not committed by default;
- local runtime config does not enter git;
- dump files do not enter git.

The generated fixture can be deleted after check.

If left on disk, it must remain an ignored local artifact with an explicit note in the report.

### Content safety

Verify:

- fixture file contains no SQL statements;
- fixture file contains no SQL diff;
- fixture file contains no apply plan;
- fixture file is not named like SQL/apply output;
- writer does not call approval flow;
- writer does not call bridge;
- writer does not call SQL preview.

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

## Out of Scope

Вне scope текущего шага:

- PHP implementation;
- actual file writing;
- actual fixture JSON creation;
- changing `.gitignore`;
- creating `var` directory;
- creating `review-fixtures` directory;
- runner/test framework;
- pipeline wiring;
- connecting writer to pipeline;
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

- `docs/DB_READONLY_LOCAL_REVIEW_ARTIFACT_STORAGE_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`;
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`;
- `docs/DB_READONLY_STANDALONE_REVIEW_FLOW_CHECK_SPEC.md`.

Project records:

- `docs/DECISIONS.md`;
- `docs/RUNTIME_CHECKS.md`.

Project ignore boundary:

- `.gitignore`.

## Recommended Boundary

Writer is only a standalone local file writer for human review fixture artifacts.

It must not become:

- production storage;
- pipeline stage;
- SQL preview input;
- apply layer.
