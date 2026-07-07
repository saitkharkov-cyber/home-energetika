# DB-readonly First Real-data Usage Input Source Spec

## Purpose

Цель этого документа — выбрать первый concrete input source для controlled real-data-like usage check через `DbReadOnlyRealDataReviewChainUsageChecker`.

Этот spec нужен, чтобы определить безопасный маленький readonly input до первого реального прогона usage checker-а.

Это не:

- full category batch;
- live DB integration;
- production DB workflow;
- pipeline wiring;
- runner integration;
- SQL preview;
- SQL/apply;
- production output.

## Preferred First Input

Рекомендуемый первый источник:

- маленький local readonly fixture/snapshot;
- dump-derived или test fixture;
- не live DB;
- не production source;
- не arbitrary uploaded data;
- не pipeline input.

Максимальный размер prepared fixture/snapshot:

- до 12 rows.

Формат должен быть совместим с `DbReadOnlyRealDataReviewChainUsageChecker`:

```text
rows[]
  product_id
  attribute_id
  attribute_name
  raw_value
  normalized_value
  confidence
```

Первый actual run должен использовать маленький slice из fixture:

- `input_rows_count <= 2`.

Такой split нужен, чтобы fixture мог содержать немного больше подготовленных examples, но первый check оставался минимальным и контролируемым.

## Candidate Scenario

Рекомендуемый первый scenario:

- characteristic context: `pump_diameter`;
- source type: local readonly dump-derived/test fixture;
- prepared fixture capacity: до 12 controlled rows;
- first run slice: 1-2 controlled rows.

Цель первого run:

- проверить, что `DbReadOnlyRealDataReviewChainUsageChecker` принимает readonly rows;
- проверить, что checker создаёт parser-like output;
- проверить, что E2E checker вызывается;
- довести chain до `review_ready` diagnostics;
- подтвердить non-apply markers.

## Input Shape

Минимальный safe row shape:

```text
product_id
attribute_id
attribute_name
raw_value
normalized_value
confidence
```

Допустимые свойства input:

- значения только из local readonly snapshot/fixture;
- маленький controlled набор;
- без filenames/paths/URLs внутри input;
- без DB connection data;
- без SQL content;
- без apply semantics.

Если часть non-critical fields отсутствует в future manual check, usage checker может использовать безопасные fallback values.

Input не должен обращаться к внешним источникам.

## Explicit Boundaries

Запрещено:

- pipeline wiring;
- runner integration;
- live DB;
- production DB;
- full category batch;
- arbitrary uploaded data;
- OpenCart runtime paths;
- SQL preview;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`;
- `UPDATE`;
- `DELETE`;
- `REPLACE`;
- `ALTER`;
- `DROP`;
- `TRUNCATE`;
- `CREATE`.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

## Completion Criteria For Future Run

Будущий первый run считается успешным, если:

- `input_rows_count <= 2`;
- checker returns `used = 1`;
- `parser_like_output_created = 1`;
- `e2e_checker_called = 1`;
- `e2e_checked = 1`;
- `review_ready = 1`;
- all SQL/apply markers are `0`;
- runtime fixture JSON не остаётся после check;
- SQL/apply artifacts не создаются;
- default dry-run остаётся ok;
- DB-readonly runner остаётся ok.

Обязательные non-apply markers:

```text
sql_generated = 0
apply_plan_created = 0
safe_to_apply = 0
sql_apply_allowed = 0
production_ready = 0
```

## Recommendation

Рекомендация:

1. После этого spec зафиксировать architecture decision для first real-data usage input source.
2. Затем только после explicit `+` сделать минимальный standalone manual check script/temporary command, если будет нужно.
3. Не добавлять постоянный runner сейчас.
4. Не подключать usage checker к pipeline.

Если future manual check будет сделан, он должен:

- использовать только local readonly fixture/snapshot;
- брать first run slice 1-2 rows;
- не читать live DB;
- не создавать SQL/apply artifacts;
- удалить temporary runtime fixture JSON после check;
- подтвердить git safety.

## Out Of Scope

Вне scope этого документа:

- PHP implementation;
- создание fixture files;
- создание command/script;
- persistent runner;
- pipeline wiring;
- runner integration;
- live DB;
- production DB;
- full category batch;
- SQL preview;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- OpenCart runtime;
- production output;
- изменение default dry-run path;
- изменение `docs/HANDOFF.md`;
- изменение `docs/DECISIONS.md`;
- изменение `docs/RUNTIME_CHECKS.md`.

## Summary

Первый concrete input source должен быть маленьким local readonly dump-derived/test fixture для `pump_diameter`.

Prepared fixture может содержать до 12 controlled rows, но первый actual run должен использовать slice:

```text
input_rows_count <= 2
```

Цель — проверить, что usage checker создаёт parser-like output и доводит standalone review-chain до `review_ready` diagnostics без SQL/apply, без live DB, без pipeline/runners и без production output.
