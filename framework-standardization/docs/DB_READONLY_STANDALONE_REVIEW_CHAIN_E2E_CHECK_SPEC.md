# DB-readonly Standalone Review Chain E2E Check Spec

## Purpose

Цель этого документа — зафиксировать безопасную границу будущей standalone E2E-проверки всей локальной review chain.

Проверка должна подтверждать только совместимость standalone-компонентов и соблюдение boundary между ними.

Это не:

- pipeline wiring;
- runner integration;
- SQL preview integration;
- SQL/apply;
- live DB workflow;
- production normalization.

E2E-проверка должна работать только с synthetic/in-memory input и local ignored review fixture artifact, если fixture file нужен для проверки writer/loader boundary.

## Chain Under Check

Будущая standalone E2E-проверка должна покрывать цепочку:

```text
parser output
-> DbReadOnlyLocalReviewFixtureGenerator
-> JSON-ready review fixture array
-> DbReadOnlyLocalReviewFixtureWriter
-> local ignored review JSON file
-> manual review / edited review blocks
-> DbReadOnlyLocalReviewFixtureLoader
-> PHP array fixture
-> DbReadOnlyLocalApprovalFixtureBridge
-> DbReadOnlyNormalizationApprovalFlow
-> DbReadOnlyReviewChainResultReporter
```

Каждый компонент должен оставаться standalone-only.

Проверка не должна превращать chain в pipeline stage, runner path, SQL preview input, production storage или apply layer.

## Allowed Input

Разрешённый input для будущей проверки:

- synthetic/in-memory parser-like output;
- local ignored review fixture JSON только в `framework-standardization/var/review-fixtures/`;
- reviewer-owned `review` blocks, заполненные synthetic/manual actions;
- temporary fixture filename с безопасным `.json` extension.

Запрещённый input:

- live DB input;
- production data source;
- arbitrary filesystem path;
- fixture JSON outside `framework-standardization/var/review-fixtures/`;
- SQL/apply artifact;
- OpenCart runtime input.

Parser-like input может содержать proposals со статусами:

- `proposed`;
- `needs_review`;
- `unknown`.

Если для boundary-проверки нужен approved/rejected result, он должен появляться только после explicit review action и только через `DbReadOnlyNormalizationApprovalFlow`.

## Expected Output

Будущая E2E-проверка должна возвращать только diagnostics/reporting output.

Ожидаемые группы output:

- E2E diagnostics;
- component-level diagnostics;
- writer diagnostics;
- loader diagnostics;
- bridge diagnostics;
- approval flow summary;
- reporter summary;
- cleanup diagnostics;
- explicit non-apply markers.

Обязательные non-apply markers:

```text
sql_generated = 0
apply_plan_created = 0
safe_to_apply = 0
sql_apply_allowed = 0
production_ready = 0
```

Reporter summary может показывать:

- proposals count;
- approved count;
- rejected count;
- needs_review count;
- unknown count;
- proposed count;
- unsupported statuses diagnostics;
- SQL/apply blocked markers.

`approved` в output остаётся только review-chain status.

`approved` не означает:

- SQL apply allowed;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

## Safety And Cleanup

Если проверка создаёт local review JSON fixture, она должна:

- создать файл только под `framework-standardization/var/review-fixtures/`;
- использовать только safe `.json` filename;
- не создавать SQL/apply-like filenames;
- удалить generated local JSON fixture после проверки;
- подтвердить, что fixture file удалён;
- подтвердить, что fixture JSON не попал в git;
- подтвердить, что `git status` не содержит runtime artifacts.

Generated fixture JSON files не должны коммититься.

Runtime artifact directory может существовать локально, но без tracked/staged fixture files.

## Boundaries

Standalone E2E-проверка не должна:

- менять pipeline wiring;
- добавлять runner integration;
- добавлять SQL preview integration;
- генерировать SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать DB;
- использовать live DB;
- делать DB/schema changes;
- выполнять write/schema operations;
- создавать OpenCart module runtime paths;
- менять default dry-run path;
- использовать fixture JSON как production storage;
- использовать fixture JSON как DB storage;
- использовать review-chain output как SQL preview input by default;
- считать `approved` разрешением на SQL/apply;
- делать `safe_to_apply = 1`;
- делать `production_ready = 1`.

Запрещённые operation families:

- `INSERT`;
- `UPDATE`;
- `DELETE`;
- `REPLACE`;
- `ALTER`;
- `DROP`;
- `TRUNCATE`;
- `CREATE`.

## Future Implementation Option

Если понадобится отдельный future checker class, он может быть описан отдельным implementation step:

```text
src/Approval/DbReadOnlyStandaloneReviewChainE2EChecker.php
```

В текущем шаге этот класс не создаётся.

Future checker должен оставаться standalone-only и не должен подключаться к pipeline/runners.

## Regression Expectations For Future Implementation

Будущая реализация E2E checker-а должна сохранять regression boundaries.

Default dry-run:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Ожидаемо:

- `result_status: ok`;
- `warnings_count: 0`;
- `errors_count: 0`;
- all 9 stages ok.

DB-readonly runner:

```text
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Ожидаемо:

- `result_status: ok`;
- `warnings_count: 0`;
- `errors_count: 0`;
- all 9 stages ok.

Эти regression checks подтверждают, что standalone E2E-check boundary не меняет default dry-run path и DB-readonly runner behavior.

## Verification Plan For Future Implementation

Future implementation/check должен подтвердить:

- PHP 5.6 syntax check для future checker class, если он будет создан;
- generator получает synthetic parser-like output;
- writer создаёт local ignored review JSON fixture;
- generated fixture находится только под `framework-standardization/var/review-fixtures/`;
- manual review blocks редактируются только synthetic/in-memory или через local ignored JSON;
- loader возвращает PHP array fixture;
- bridge отделяет proposals от review actions;
- approval flow создаёт approved/rejected только по explicit review actions;
- reporter показывает summary counts и non-apply markers;
- unsupported statuses остаются diagnostics;
- generated JSON fixture удалён после проверки;
- SQL/apply artifacts не созданы;
- live DB не использовалась;
- `git status` не содержит runtime artifacts;
- default dry-run остаётся ok;
- DB-readonly runner остаётся ok.

## Out Of Scope

Вне scope этого документа:

- PHP implementation;
- создание `DbReadOnlyStandaloneReviewChainE2EChecker`;
- runner integration;
- test framework;
- pipeline wiring;
- SQL preview integration;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- DB/schema changes;
- OpenCart module runtime;
- изменение default dry-run path;
- изменение `docs/HANDOFF.md`;
- изменение `docs/DECISIONS.md`;
- изменение `docs/RUNTIME_CHECKS.md`;
- изменение `.gitignore`;
- создание persistent fixture JSON files.

## Summary

Standalone review chain E2E check — это boundary-проверка совместимости standalone-компонентов:

```text
generator -> writer -> loader -> bridge -> approval flow -> reporter
```

Она может создавать временный local ignored JSON artifact только для проверки writer/loader boundary и должна удалить его после проверки.

Она не должна становиться pipeline stage, runner path, SQL preview input, production storage или apply layer.
