# DB-readonly Review Chain Result Export Boundary Spec

## Purpose

Цель этого документа — определить, нужен ли отдельный standalone export/report artifact после `DbReadOnlyStandaloneReviewChainE2EChecker`.

Потенциальный artifact может быть полезен, если потребуется сохранить human-readable diagnostic snapshot результата standalone review chain:

- E2E diagnostics;
- reporter summary;
- component diagnostics;
- explicit non-apply markers;
- cleanup facts.

Такой artifact, если он когда-либо появится, должен быть только diagnostic/reporting artifact.

Он не является:

- SQL/apply;
- production output;
- pipeline integration;
- runner integration;
- SQL preview input;
- production storage;
- DB storage.

## Current Input Source

Текущий source для возможного future export/report artifact:

- output standalone `DbReadOnlyStandaloneReviewChainE2EChecker`;
- `reporter_summary`;
- `e2e_diagnostics`;
- `component_diagnostics`;
- `errors`;
- `warnings`;
- `source`.

Текущий E2E checker уже возвращает diagnostic result array и не требует отдельного file artifact для работы.

## Decision Analysis

### Option A: не добавлять новый artifact layer сейчас

Плюсы:

- E2E checker result уже возвращает diagnostics;
- `DbReadOnlyReviewChainResultReporter` уже возвращает summary;
- runtime checks documentation уже фиксирует observed facts;
- меньше moving parts;
- меньше local artifact surface area;
- меньше риска, что файл ошибочно воспримут как production/export output;
- меньше риска, что runtime artifact попадёт в git;
- меньше риска, что report artifact начнут использовать как SQL preview input.

Минусы:

- нет отдельного persisted human-readable audit snapshot;
- для просмотра результата нужно использовать output checker-а или runtime checks documentation.

### Option B: добавить standalone local export/report artifact later

Плюсы:

- можно сохранить human-readable audit snapshot для ручного review;
- можно зафиксировать single-file diagnostic snapshot после E2E check;
- можно отделить review fixture artifact от result/report artifact.

Минусы:

- появляется новый local artifact type;
- нужно отдельное git-ignore/path/filename boundary;
- нужно предотвратить восприятие artifact как production export;
- нужно предотвратить использование artifact как SQL preview/apply input;
- нужно отдельно проверять cleanup и git safety.

Этот вариант допустим только если появится явная потребность сохранять human-readable diagnostic snapshot.

## Recommendation

Текущая рекомендация:

сейчас не реализовывать export writer.

На текущем этапе достаточно:

- standalone E2E checker result;
- reporter summary;
- runtime checks documentation.

Если позже появится потребность в persisted human-readable diagnostic snapshot, сначала нужен отдельный explicit decision step.

Implementation делать только после отдельного explicit decision, если необходимость artifact layer будет подтверждена.

## Future Artifact Option

Если future artifact будет разрешён отдельным decision step, возможный class:

```text
src/Approval/DbReadOnlyReviewChainResultExportWriter.php
```

В текущем шаге этот class не создаётся.

Future writer, если будет реализован, должен оставаться standalone-only diagnostic/reporting tool.

Он не должен становиться:

- pipeline stage;
- runner integration;
- SQL preview input;
- production output;
- SQL/apply layer.

## Allowed Future Artifact Properties

Если future export/report artifact когда-либо будет реализован, он должен:

- писать только local ignored diagnostic report artifact;
- писать только внутрь `framework-standardization/var/`;
- использовать safe filename;
- не перезаписывать existing files по умолчанию;
- не содержать SQL content;
- не содержать apply plan;
- не быть production output;
- явно содержать non-apply markers.

Обязательные non-apply markers:

```text
sql_generated = 0
apply_plan_created = 0
safe_to_apply = 0
sql_apply_allowed = 0
production_ready = 0
```

Future artifact должен быть distinguishable от:

- review fixture JSON;
- SQL files;
- SQL diff;
- apply plan;
- production export.

## Explicit Prohibitions

Для result export/report artifact boundary запрещено:

- pipeline wiring;
- runner integration;
- SQL preview integration;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- DB/live DB;
- DB/schema changes;
- write/schema operations;
- OpenCart module runtime paths;
- default dry-run path changes;
- production export;
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

## Future Verification Boundary

Если future export writer будет реализован отдельным step, verification должен подтвердить:

- PHP 5.6 syntax check future writer class;
- writer принимает только E2E checker result/reporting diagnostics;
- writer пишет только local ignored diagnostic artifact;
- artifact path находится только внутри `framework-standardization/var/`;
- artifact filename safe;
- existing file не перезаписывается по умолчанию;
- artifact не содержит executable SQL;
- artifact не содержит SQL diff;
- artifact не содержит apply plan;
- artifact явно содержит non-apply markers;
- generated artifact не staged/tracked;
- default dry-run остаётся ok;
- DB-readonly runner остаётся ok.

## Out Of Scope

Вне scope этого документа:

- PHP implementation;
- создание `DbReadOnlyReviewChainResultExportWriter`;
- создание actual export/report artifact files;
- `.gitignore` changes;
- runner integration;
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
- изменение `docs/RUNTIME_CHECKS.md`.

## Summary

Отдельный standalone review-chain result export/report artifact сейчас не нужен.

Текущий безопасный слой:

```text
DbReadOnlyStandaloneReviewChainE2EChecker result
-> reporter_summary
-> e2e_diagnostics
-> component_diagnostics
-> runtime checks documentation
```

Future artifact layer допустим только после отдельного explicit decision и только как local ignored diagnostic/reporting artifact без SQL/apply и production semantics.
