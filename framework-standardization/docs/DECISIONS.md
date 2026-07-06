# Decisions

Документ фиксирует короткие архитектурные решения по Framework Standardization, чтобы `HANDOFF.md` оставался оперативным handoff, а не историческим журналом.

## 2026-07-06 - DB-readonly scope/export должны подключаться парой

### Решение

`resolve_scope` и `export_attributes` в DB-readonly path должны переводиться в DB-backed режим только как связанная пара.

Нельзя переводить только `resolve_scope` на DB-backed реализацию, если следующий stage `export_attributes` всё ещё использует dry-run exporter.

### Причина

`DbReadOnlyScopeResolver` возвращает реальные `product_id` из local dump DB.

`DryRunAttributeExporter` является fixture-only компонентом и ожидает fixture data, включая `product_id = 0`.

Поэтому состояние:

```text
DbReadOnlyScopeResolver + DryRunAttributeExporter
```

недопустимо.

### Последствие

Не подключать `DbReadOnlyScopeResolver` в `DbReadOnlyPipelineFactory`, пока не будет реализован и standalone-проверен совместимый `DbReadOnlyAttributeExporter`.

Default dry-run path должен оставаться no-DB fixture path.

DB-readonly runner должен оставаться отдельным manual path.

### Ссылка

Подробности: `docs/DB_READONLY_SCOPE_EXPORT_MINI_SPEC.md`.

## 2026-07-06 — DB-readonly-compatible adapters допустимы после scope/export wiring

### Решение

После подключения DB-backed пары `resolve_scope` / `export_attributes` в DB-readonly path допустимо использовать отдельные DB-readonly-compatible adapters для downstream stages.

На текущем этапе такими adapters являются:

`DbReadOnlyAttributeNameAnalyzer`

`DbReadOnlyAttributeValueAnalyzer`

`DbReadOnlySqlPreviewBuilder`

### Причина

После перехода `resolve_scope` и `export_attributes` на реальные DB IDs downstream dry-run components оказались fixture-only и не были совместимы с real DB facts.

Чтобы сохранить прохождение pipeline без перехода к production normalization и SQL apply, downstream stages были переведены на read-only-compatible adapters.

### Последствие

DB-readonly path теперь разделяется на три типа stages:

DB-backed stages:

`resolve_canonical`

`resolve_scope`

`export_attributes`

DB-readonly-compatible stages:

`analyze_names`

`analyze_values`

`build_sql_preview`

Dry-run stages:

`build_report`

`build_framework_result`

DB-readonly-compatible adapters не считаются production implementation.

Они не должны:

- выполнять production normalization;
- генерировать executable SQL;
- выполнять SQL apply;
- использовать live DB;
- выполнять write/schema operations.

`DbReadOnlySqlPreviewBuilder` должен оставаться preview-only / blocked builder: без SQL statements и без safe-to-apply режима.

### Ссылка

Runtime-проверки зафиксированы в `docs/RUNTIME_CHECKS.md`.

Paired wiring commit:

`cb54135 Wire DB readonly scope export path`

## 2026-07-06 — DB-readonly analyze_values развивается как profiling, не normalization

### Решение

`analyze_values` в DB-readonly path может развиваться как read-only value profiling stage.

Это означает, что `DbReadOnlyAttributeValueAnalyzer` может собирать diagnostics по real DB raw values, но не должен выполнять production normalization.

### Причина

После DB-backed `export_attributes` доступны real raw values по target attribute.

Эти данные полезны для инженерной диагностики:

`raw_values`

`unique_raw_values_count`

`top_raw_values`

`raw_value_frequencies`

`empty_values_count`

`length diagnostics`

`suspicious diagnostics`

Но на текущем этапе ещё нет отдельной production normalization architecture, parser approval flow и SQL/apply safety model.

Поэтому value analysis должен оставаться profiling-only.

### Последствие

Разрешено:

- считать raw value frequencies;
- считать top raw values;
- считать empty values;
- считать length diagnostics;
- считать heuristic suspicious diagnostics;
- сохранять examples;
- писать profiling facts в `attribute_value_structure.diagnostics.raw_profile`.

Запрещено:

- заполнять apply-ready `normalized_values`;
- выполнять unit conversion;
- извлекать canonical numeric value;
- делать semantic reject / approve;
- создавать executable SQL;
- переводить `build_sql_preview` в `safe_to_apply = 1`;
- выполнять SQL apply.

`suspicious_*` поля являются только diagnostics и не означают reject / approve.

### Ссылка

Spec:

`docs/DB_READONLY_VALUE_PROFILING_SPEC.md`

Runtime-проверки:

`docs/RUNTIME_CHECKS.md`

Implementation commit:

`0a470df Add DB readonly raw value profiling`

## 2026-07-06 — DB-readonly build_sql_preview остаётся blocked preview после raw_profile diagnostics

### Решение

DB-readonly `build_sql_preview` может отображать `raw_profile` summary только как diagnostics-only.

`DbReadOnlySqlPreviewBuilder` обязан оставаться blocked preview.

### Причина

`raw_profile` содержит read-only diagnostics по raw DB values.

Он отвечает на вопрос:

```text
что сейчас лежит в raw values?
```

Он не отвечает на вопрос:

```text
что надо записать в DB?
```

На текущем этапе нет отдельной production SQL/apply architecture, parser approval flow, normalization approval и apply safety model.

### Последствие

Разрешено:

- отображать `raw_profile` summary в `sql_preview.diagnostics`;
- использовать `raw_profile_present`;
- использовать count-поля вроде `raw_profile_total_values`, `unique_raw_values_count`, `empty_values_count`;
- использовать `suspicious_*` counts только как diagnostics.

Запрещено:

- использовать `raw_profile` как apply input;
- считать `suspicious_*` diagnostics reject / approve;
- использовать `normalized_values` как apply-ready data;
- делать `generated = 1`;
- делать `safe_to_apply = 1`;
- заполнять `statements`;
- убирать blocker `db_readonly_sql_preview_not_implemented`;
- генерировать executable SQL;
- создавать SQL files;
- создавать apply plan;
- выполнять SQL apply.

Обязательная безопасная форма:

```text
generated = 0
safe_to_apply = 0
statements = array()
blocked_by contains db_readonly_sql_preview_not_implemented
```

### Ссылка

Spec:

`docs/DB_READONLY_SQL_PREVIEW_BOUNDARY_SPEC.md`

Runtime-проверки:

`docs/RUNTIME_CHECKS.md`

Implementation commit:

`ecd9196 Add DB readonly SQL preview raw profile diagnostics`

Documentation commit:

`73f2708 Document DB readonly SQL preview diagnostics checks`

## 2026-07-06 — DB-readonly build_report показывает diagnostics только как reporting-only output

### Решение

DB-readonly `build_report` может отображать diagnostics из `raw_profile` и `sql_preview` только как reporting-only output.

Разрешено показывать:

- `raw_profile_summary` как read-only report output;
- `sql_preview_safety_summary` как read-only report output.

Report output не является:

- normalization;
- reject / approve decision;
- apply-ready data;
- SQL plan.

### Причина

`raw_profile` и `sql_preview.diagnostics` содержат инженерные read-only facts о текущих raw DB values и состоянии blocked preview.

Эти diagnostics помогают человеку увидеть состояние данных, но не отвечают на вопросы:

```text
какое значение нужно записать в DB?
можно ли применять SQL?
какие значения approved/rejected?
```

На текущем этапе нет отдельной production normalization / SQL apply architecture.

### Последствие

Разрешено:

- отображать `raw_profile_summary` в report;
- отображать `sql_preview_safety_summary` в report;
- показывать `suspicious_*` counts только как diagnostics;
- показывать, что `sql_preview` остаётся blocked preview.

Запрещено:

- считать report output normalization;
- считать report output reject / approve decision;
- считать `suspicious_*` diagnostics reject / approve;
- использовать `normalized_values` как apply-ready data;
- менять `sql_preview` из `build_report`;
- менять `safe_to_apply` из `build_report`;
- менять `statements` из `build_report`;
- создавать SQL;
- создавать SQL files;
- создавать apply plan;
- выполнять SQL apply.

SQL apply запрещён до отдельной production SQL/apply architecture.

### Ссылка

Spec:

`docs/DB_READONLY_REPORT_OUTPUT_SPEC.md`

Runtime-проверки:

`docs/RUNTIME_CHECKS.md`

Implementation commit:

`50daba1 Add DB readonly diagnostics to report output`

Documentation commit:

`a60c5d8 Document DB readonly report diagnostics checks`


## 2026-07-06 — DB-readonly build_framework_result остаётся dry-run/result-packaging output после diagnostics summary

### Решение

`DB-readonly build_framework_result` может выводить `diagnostics_summary` и `safety_summary` только как dry-run / result-packaging output.

Эти summary-блоки делают diagnostics видимыми на верхнем уровне результата, но не превращают их в production decision, apply decision или SQL plan.

### Разрешено

`build_framework_result` может показывать top-level diagnostics summary, основанный на уже подготовленных данных из `report` и `sql_preview`:

- `raw_profile_present`
- `raw_profile_total_values`
- `unique_raw_values_count`
- `suspicious_no_digits_count`
- `suspicious_long_value_count`
- `suspicious_multiple_numbers_count`
- `report_has_raw_profile_summary`
- `report_has_sql_preview_safety_summary`
- `sql_preview_safe_to_apply`
- `sql_preview_statement_count`
- `blocked_preview_expected`

`build_framework_result` может показывать top-level safety summary:

- `generated = 0`
- `safe_to_apply`
- `statements_count`
- `sql_apply_allowed = 0`
- `production_ready = 0`

### Границы

`diagnostics_summary` и `safety_summary` являются только read-only output.

Они не являются:

- normalization;
- reject / approve decision;
- apply-ready data;
- SQL diff;
- SQL plan;
- production readiness marker.

`production_ready` должен оставаться `0`.

`sql_apply_allowed` должен оставаться `0`.

`statements_count` должен оставаться `0` в DB-readonly blocked-preview path.

### Запрещено

`build_framework_result` не должен:

- менять `report`;
- менять `sql_preview`;
- менять `safe_to_apply`;
- менять `statements`;
- менять `generated`;
- делать normalization;
- принимать reject / approve decisions;
- создавать executable SQL;
- создавать SQL files;
- создавать apply plan;
- выполнять SQL apply;
- менять pipeline wiring;
- менять runners;
- менять default dry-run path.

SQL apply запрещён до отдельной production SQL/apply architecture.

### Контекст

Связанные документы:

- `docs/DB_READONLY_FRAMEWORK_RESULT_SPEC.md`
- `docs/DB_READONLY_REPORT_OUTPUT_SPEC.md`
- `docs/RUNTIME_CHECKS.md`

Связанные коммиты:

- `ff06d47 Add DB readonly diagnostics to framework result`
- `e0af61f Document DB readonly framework result diagnostics checks`

### Последствие

DB-readonly pipeline теперь может показывать diagnostics на верхнем уровне framework result, но весь путь остаётся read-only / dry-run / non-apply.

Следующий production-facing шаг всё ещё требует отдельного spec и отдельного architecture decision.


## 2026-07-06 — SQL/apply преждевременен без normalization approval flow

### Решение

Production SQL/apply architecture нельзя проектировать как следующий слой, пока не описан и не реализован controlled normalization approval flow.

DB-readonly parser может создавать `normalization proposals`, но не должен создавать `approved` или apply-ready values автоматически.

### Причина

Текущий DB-readonly pipeline уже показывает diagnostics на нескольких уровнях:

- `raw_profile` в `analyze_values`;
- `raw_profile` summary в `sql_preview.diagnostics`;
- `raw_profile_summary` и `sql_preview_safety_summary` в `build_report`;
- `diagnostics_summary` и `safety_summary` в `build_framework_result`.

Но эти данные отвечают только на вопрос:

- что сейчас лежит в raw DB values;
- какие форматы и suspicious cases видны;
- почему SQL preview остаётся blocked.

Они не отвечают на вопросы:

- какое normalized value должно быть записано;
- какие parser warnings допустимы;
- какие proposals approved;
- какие proposals rejected;
- какие proposals требуют review;
- какие values могут стать input для future SQL preview.

Поэтому SQL/apply без approval flow был бы преждевременным и небезопасным.

### Разрешено

Следующий production-facing слой может развиваться как controlled normalization proposal layer.

Разрешено проектировать и в будущем реализовывать:

- parser для raw values;
- `parsed_value`;
- `normalized_value_proposals`;
- `parser_confidence`;
- `parser_warnings`;
- `approval_status`;
- статусы `proposed`, `approved`, `rejected`, `needs_review`, `unknown`;
- traceability от `original_raw_value` к proposal;
- report/framework summaries по proposal counts и approval statuses.

### Границы parser-а

Parser может:

- парсить числа;
- распознавать `мм` и `mm`;
- распознавать decimal comma / dot;
- распознавать диапазоны только как `needs_review`;
- сохранять original raw value;
- создавать parser diagnostics;
- создавать proposals со статусами `proposed`, `needs_review` или `unknown`.

Parser не должен:

- сам принимать production decision;
- сам выставлять `approved`;
- создавать SQL;
- создавать SQL files;
- создавать apply plan;
- менять DB;
- менять `safe_to_apply`;
- менять `statements`;
- выполнять SQL apply.

### Approval boundary

Только явно approved normalized proposals могут стать input для будущего SQL preview.

Не являются apply input:

- raw diagnostics;
- `raw_profile`;
- parser diagnostics;
- `proposed`;
- `needs_review`;
- `unknown`;
- `rejected`;
- suspicious diagnostics;
- unapproved normalized proposals.

Даже approved normalized proposal не разрешает SQL apply сам по себе.

Для SQL preview/apply нужен отдельный production SQL/apply spec и отдельное architecture decision.

### Запрещено

До отдельной production SQL/apply architecture запрещено:

- генерировать executable SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать live DB;
- выполнять write/schema operations;
- переводить `safe_to_apply` в `1`;
- считать diagnostics production-ready data;
- считать proposals apply-ready data без explicit approval.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Контекст

Связанные документы:

- `docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`
- `docs/DB_READONLY_VALUE_PROFILING_SPEC.md`
- `docs/DB_READONLY_SQL_PREVIEW_BOUNDARY_SPEC.md`
- `docs/DB_READONLY_REPORT_OUTPUT_SPEC.md`
- `docs/DB_READONLY_FRAMEWORK_RESULT_SPEC.md`
- `docs/RUNTIME_CHECKS.md`

Связанный коммит:

- `948ae73 Add DB readonly normalization approval spec`

### Последствие

Следующий безопасный engineering step должен двигаться в сторону normalization proposal parser / approval flow, а не SQL/apply.

SQL/apply остаётся blocked до появления approved normalized proposals и отдельной production SQL/apply architecture.

## 2026-07-06 — Standalone normalization parser не является approval или SQL/apply layer

### Решение

`DbReadOnlyNormalizationProposalParser` является standalone parser skeleton для создания normalization proposals.

Он не является pipeline stage, approval layer, SQL preview input или apply-ready layer.

На текущем этапе parser может создавать только proposals со статусами:

- `proposed`
- `needs_review`
- `unknown`

Parser не должен создавать proposals со статусами:

- `approved`
- `rejected`

### Причина

Parser нужен как следующий безопасный слой после raw value profiling.

Он может технически разобрать raw value и предложить normalized value candidate, но не может сам принимать production decision.

Без отдельного approval flow parser output остаётся proposal/diagnostics, а не approved normalized data.

### Разрешено

Standalone parser может:

- принимать read-only raw values массивом;
- читать `product_id`;
- читать `attribute_id`;
- читать `language_id`;
- читать `target_attribute_id`;
- читать `raw_text` или `value`;
- сохранять `original_raw_value`;
- создавать `parsed_value`;
- создавать `proposed_normalized_value`;
- определять `proposed_unit`;
- выставлять `parser_confidence`;
- сохранять `parser_warnings`;
- создавать `normalization_value_proposals`;
- создавать `parser_diagnostics`.

Для первого skeleton разрешены только статусы:

- `proposed`;
- `needs_review`;
- `unknown`.

### Parsing boundary

Safe parsing rules для standalone parser skeleton:

- пустое значение -> `unknown`;
- одно число без диапазона -> `proposed`;
- число + `мм` / `mm` -> `proposed`;
- decimal comma / dot -> `proposed`;
- несколько чисел -> `needs_review`;
- диапазон -> `needs_review`;
- текст без чисел -> `unknown`.

Диапазоны и значения с несколькими числами не должны автоматически становиться `proposed` как production-ready values.

Они требуют review.

### Запрещено

Standalone parser не должен:

- выставлять `approved`;
- выставлять `rejected`;
- подключаться к `analyze_values`;
- подключаться к `sql_preview`;
- подключаться к `build_report`;
- подключаться к `build_framework_result`;
- менять pipeline wiring;
- менять runners;
- менять default dry-run path;
- менять `safe_to_apply`;
- менять `statements`;
- создавать executable SQL;
- создавать SQL files;
- создавать apply plan;
- выполнять SQL apply;
- использовать live DB;
- выполнять write/schema operations.

### Approval boundary

`normalization_value_proposals` не являются apply input.

`proposed`, `needs_review` и `unknown` не являются apply-ready statuses.

Только future explicit approval flow может создать `approved normalized proposals`.

Даже `approved normalized proposals` в будущем не должны автоматически выполнять SQL apply: для этого нужен отдельный production SQL/apply spec и отдельное architecture decision.

### Контекст

Связанные документы:

- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`
- `docs/RUNTIME_CHECKS.md`

Связанные коммиты:

- `071616f Add DB readonly normalization parser skeleton spec`
- `bd06b9c Add DB readonly normalization proposal parser`
- `94d0f27 Document DB readonly normalization parser checks`

### Последствие

Parser можно развивать как standalone normalization proposal layer.

Следующий безопасный engineering step может быть связан с расширением parser diagnostics или подготовкой explicit approval flow.

Нельзя подключать parser output к SQL/apply path без отдельного approval flow и отдельной SQL/apply architecture.
