# DB-readonly Real-data Review Chain Usage Scenario Spec

## Purpose

Цель этого документа — выбрать первый безопасный реальный сценарий использования уже собранной standalone review-chain.

Этот шаг нужен, чтобы перейти от synthetic/in-memory E2E check к controlled real-data usage scenario, но без перехода к production workflow.

Scenario должен проверить, что standalone review-chain может подготовить данные для ручного review на небольшом readonly input.

Это не:

- pipeline wiring;
- runner integration;
- SQL preview integration;
- SQL/apply;
- live DB workflow;
- production output;
- full category normalization.

## Scenario Goal

Первый безопасный сценарий:

1. Взять одну заранее подготовленную real-data input snapshot/fixture из local dump/config/test data.
2. Использовать её только как readonly input.
3. Обернуть real-data rows в parser-like output shape, совместимый со standalone review-chain.
4. Прогнать standalone review-chain до reporter/E2E diagnostics.
5. Получить review-ready diagnostics для ручной оценки.
6. Не создавать production/export/apply output.

Сценарий должен быть малым и контролируемым: только ограниченный набор строк, достаточный для проверки boundary.

## Allowed Input

Допустимы только:

- local readonly snapshot;
- local readonly fixture;
- dump-derived input, подготовленный заранее;
- synthetic wrapper над real-data rows;
- ограниченный маленький набор строк;
- parser-like output array, построенный из readonly rows;
- local ignored review fixture JSON только если он нужен для manual review boundary.

Input должен быть read-only.

Input не должен требовать live DB connection.

Input не должен выполнять write/schema operations.

## Forbidden Input

Запрещено использовать:

- live DB;
- production DB;
- production data source;
- OpenCart runtime path;
- arbitrary uploaded data;
- full category batch;
- automatic pipeline input;
- SQL preview input;
- SQL/apply artifact;
- runtime source, который требует DB writes или schema changes.

Запрещено запускать scenario как часть pipeline/runners.

## Chain Under Usage

Сценарий использует уже существующую standalone chain:

```text
parser-like output
-> DbReadOnlyLocalReviewFixtureGenerator
-> JSON-ready review fixture array
-> DbReadOnlyLocalReviewFixtureWriter
-> local ignored JSON
-> manual/synthetic review blocks
-> DbReadOnlyLocalReviewFixtureLoader
-> PHP array fixture
-> DbReadOnlyLocalApprovalFixtureBridge
-> DbReadOnlyNormalizationApprovalFlow
-> DbReadOnlyReviewChainResultReporter
-> optional DbReadOnlyStandaloneReviewChainE2EChecker diagnostics
```

Chain остаётся standalone-only.

Она не становится pipeline stage, runner integration, SQL preview input, production storage или apply layer.

## Expected Output

Ожидаемый output:

- review-ready diagnostics;
- local ignored review fixture JSON only if needed for manual review;
- reporter summary;
- E2E diagnostics;
- component diagnostics;
- explicit non-apply markers.

Обязательные non-apply markers:

```text
sql_generated = 0
apply_plan_created = 0
safe_to_apply = 0
sql_apply_allowed = 0
production_ready = 0
```

Output не является:

- production output;
- SQL preview input;
- apply-ready output;
- normalization approval for production;
- permission for SQL apply.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

## Completion Criteria

Scenario считается успешным, если:

- input обработан без ошибок;
- review fixture может быть создана;
- review fixture может быть загружена обратно;
- manual/synthetic review action проходит через bridge;
- approval flow возвращает expected status transitions;
- reporter summary корректен;
- E2E diagnostics успешны;
- runtime artifacts не коммитятся;
- temporary/local fixture artifacts удаляются или остаются ignored local-only по явной договорённости;
- SQL/apply artifacts не создаются;
- default dry-run не ломается;
- DB-readonly runner не ломается.

Минимальные expected checks для будущей реализации:

- `errors_count = 0`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`;
- `sql_apply_allowed = 0`;
- `production_ready = 0`;
- generated fixture JSON не staged/tracked;
- no SQL/apply artifacts.

## Explicit Non-goals

В рамках первого real-data usage scenario не делать:

- normalization всей категории;
- массовую обработку;
- pipeline wiring;
- runner integration;
- SQL preview;
- apply plan;
- DB writes;
- DB/schema changes;
- OpenCart module changes;
- production data changes;
- production export;
- SQL/apply artifact;
- automatic approval;
- использование `approved` как SQL/apply permission.

## Safety Boundaries

Запрещено:

- pipeline wiring;
- runner integration;
- SQL preview integration;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- production DB;
- DB/schema changes;
- write/schema operations;
- OpenCart module runtime paths;
- default dry-run path changes;
- committed runtime artifacts.

Запрещённые operation families:

- `INSERT`;
- `UPDATE`;
- `DELETE`;
- `REPLACE`;
- `ALTER`;
- `DROP`;
- `TRUNCATE`;
- `CREATE`.

## Decision Analysis

### Why this scenario is safe

Этот scenario безопаснее следующего production-facing шага, потому что:

- работает с заранее подготовленным readonly input;
- использует маленький контролируемый набор строк;
- не подключается к pipeline;
- не использует live DB;
- не создаёт SQL/apply artifacts;
- не создаёт production output;
- проверяет только review-chain usability на real-data-like facts.

### Why not full category batch

Full category batch преждевременен, потому что:

- увеличивает data volume;
- повышает риск accidental production assumptions;
- может скрыть boundary issues за объёмом данных;
- может быть ошибочно воспринят как production normalization step.

Первый real-data scenario должен быть small controlled slice.

### Why not SQL preview

SQL preview преждевременен, потому что:

- review-chain status не является SQL/apply permission;
- `approved` остаётся только review-chain status;
- SQL/apply architecture требует отдельного future decision;
- current output является diagnostics/reporting-only.

## Recommendation

Рекомендация:

1. Сначала зафиксировать architecture decision для этого real-data usage scenario.
2. Затем только после явного `+` реализовать минимальный standalone usage checker/fixture command, если decision подтвердит необходимость.
3. Не начинать implementation в рамках текущего spec step.

Будущий implementation, если будет разрешён, должен оставаться standalone-only и diagnostic/reporting-only.

## Future Implementation Option

Если decision подтвердит необходимость, возможный future implementation может быть:

- standalone usage checker;
- temporary/manual command;
- local fixture command;
- small script для controlled snapshot input.

Любой future implementation должен:

- принимать только local readonly snapshot/fixture;
- работать с маленьким controlled input;
- не подключаться к pipeline/runners;
- не использовать live DB;
- не генерировать SQL/apply;
- подтверждать cleanup/git safety.

## Out Of Scope

Вне scope этого документа:

- PHP implementation;
- usage checker implementation;
- fixture command implementation;
- pipeline wiring;
- runner integration;
- SQL preview integration;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- live DB;
- production DB;
- DB/schema changes;
- OpenCart module runtime;
- production normalization;
- full category batch;
- изменение default dry-run path;
- изменение `docs/HANDOFF.md`;
- изменение `docs/DECISIONS.md`;
- изменение `docs/RUNTIME_CHECKS.md`.

## Summary

Первый real-data standalone review-chain usage scenario должен быть малым, readonly и controlled:

```text
local readonly snapshot/fixture
-> parser-like output
-> standalone review-chain
-> reporter/E2E diagnostics
```

Он нужен только для проверки, что review-chain может подготовить review-ready diagnostics по real-data-like input.

Он не должен становиться pipeline step, production normalization, SQL preview, apply plan или production output.
