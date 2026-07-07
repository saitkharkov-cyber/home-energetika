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

## 2026-07-06 — Approval flow отделён от parser и не разрешает SQL/apply

### Решение

Future explicit approval flow должен быть отдельным слоем поверх normalization proposals.

`DbReadOnlyNormalizationProposalParser` не должен выставлять `approved` или `rejected`.

Только approval flow может явно перевести proposal в статусы:

- `approved`
- `rejected`
- `needs_review`
- `unknown`
- `proposed`

`approved` proposal означает только candidate eligibility для future SQL preview.

`approved` proposal не означает SQL apply и не переводит pipeline в production-ready режим.

### Причина

Parser является техническим deterministic layer.

Он может:

- разобрать raw value;
- создать parsed value;
- создать normalization proposal;
- выставить parser confidence;
- сохранить parser warnings.

Но parser не должен решать:

- правильно ли значение для production;
- допустимы ли warnings;
- можно ли использовать proposal для SQL preview;
- кто несёт ответственность за approval decision.

Approval — это controlled review decision, а не parsing result.

### Разрешено

Future approval flow может поддерживать actions:

- `approve`
- `reject`
- `mark_needs_review`
- `mark_unknown`
- `reset_to_proposed`

Approval flow может хранить audit fields:

- `reviewer` или `approved_by`
- `reviewed_at` / `approved_at`
- `review_note`
- `previous_status`
- `new_status`
- `source`
- `review_action`
- `proposal_id`
- `proposal_hash`

Approval flow может использовать review input:

- `proposal_id`
- `product_id`
- `attribute_id`
- `target_attribute_id`
- `original_raw_value`
- `parsed_value`
- `proposed_normalized_value`
- `proposed_unit`
- `parser_confidence`
- `parser_warnings`
- `current approval_status`
- `examples` / grouped raw values

### Approval boundary

Обязательная граница:

- parser cannot approve;
- only approval flow can approve;
- only `approved` proposals can become candidates for future SQL preview;
- `approved` does not mean SQL apply;
- `approved` does not mean `safe_to_apply = 1`;
- `approved` does not mean `production_ready = 1`.

Не являются SQL preview input:

- `proposed`
- `rejected`
- `needs_review`
- `unknown`
- raw diagnostics
- `raw_profile`
- parser diagnostics
- suspicious diagnostics
- unapproved proposals

### Storage boundary

В этом решении не выбирается storage для approval data.

Допустимые варианты для future specs:

- local JSON approval fixture;
- local YAML approval fixture;
- reviewed CSV;
- future DB approval table.

Но на текущем этапе запрещено:

- создавать DB approval table;
- использовать live DB;
- выполнять write/schema operations;
- создавать SQL files;
- создавать apply plan;
- выполнять SQL apply.

Если future implementation использует local file fixture, это должен быть отдельный explicit step.

### Report / framework result boundary

Report и framework result могут в будущем показывать approval summaries:

- proposal count;
- approved count;
- rejected count;
- needs_review count;
- unknown count;
- proposed count;
- reviewer/source summary;
- examples.

Но approval summaries не должны:

- считать `production_ready = 1`;
- менять `safe_to_apply`;
- менять `statements`;
- создавать SQL;
- создавать apply plan;
- выполнять SQL apply.

### Запрещено

До отдельной production SQL/apply architecture запрещено:

- подключать approval output напрямую к SQL apply;
- считать `approved` proposal apply command;
- генерировать executable SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать live DB;
- выполнять write/schema operations;
- переводить `safe_to_apply` в `1`;
- переводить `production_ready` в `1`.

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

- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`
- `docs/RUNTIME_CHECKS.md`

Связанные коммиты:

- `bd06b9c Add DB readonly normalization proposal parser`
- `64522af Document standalone normalization parser boundary`
- `a87d7da Add DB readonly normalization approval flow spec`

### Последствие

Следующий безопасный engineering step может быть связан с проектированием или standalone implementation approval flow.

Но approval flow должен оставаться controlled review layer.

SQL preview/apply остаётся blocked до отдельного production SQL/apply spec и отдельного architecture decision.

## 2026-07-06 — Local approval fixture является review artifact, а не storage или SQL/apply layer

### Решение

Local approval fixture / manual review bridge может использоваться только как локальный review artifact/process между standalone parser и standalone approval flow.

Рекомендуемый первый safe format:

- `JSON`

JSON fixture не является:

- DB storage;
- production storage;
- SQL file;
- SQL diff;
- apply plan;
- pipeline stage;
- SQL preview input сам по себе.

### Причина

Standalone parser создаёт normalization proposals, но не принимает review decisions.

Standalone approval flow применяет explicit review actions, но ему нужен безопасный способ получить actions от reviewer-а.

Local approval fixture нужен, чтобы:

- показать proposals человеку;
- отделить parser-owned fields от reviewer-owned fields;
- дать reviewer-у место для explicit action;
- передать actions в `DbReadOnlyNormalizationApprovalFlow::apply($proposals, $reviewActions)`;
- сохранить весь процесс вне pipeline, SQL/apply и live DB.

JSON выбран как первый safe format, потому что он лучше сохраняет nested data:

- `parsed_value`;
- `parser_warnings`;
- `review`;
- grouped examples.

CSV можно рассмотреть позже отдельным spec, если понадобится табличный review workflow.

### Разрешено

Fixture может содержать parser-owned proposal data:

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

Fixture может содержать reviewer-owned block:

- `review.action`;
- `review.reviewer`;
- `review.review_note`.

Допустимые `review.action`:

- `approve`;
- `reject`;
- `mark_needs_review`;
- `mark_unknown`;
- `reset_to_proposed`.

Пустой `review.action` означает:

- no review action;
- proposal status не меняется.

### Граница редактирования

Reviewer может менять только:

- `review.action`;
- `review.reviewer`;
- `review.review_note`.

Reviewer не должен менять руками parser-owned fields:

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

- `reject`;
- `mark_needs_review`;
- `mark_unknown`;

и при необходимости добавить `review_note`.

Ручное исправление parser-owned fields требует отдельного future spec, потому что меняет traceability и может потребовать parser override model.

### Bridge boundary

Bridge может преобразовать fixture rows в:

- `$proposals`;
- `$reviewActions`.

`$reviewActions` должен содержать только rows с непустым `review.action`.

Status transitions должен выполнять только:

- `DbReadOnlyNormalizationApprovalFlow::apply($proposals, $reviewActions)`.

Bridge не должен напрямую выставлять statuses.

Approval flow остаётся единственным standalone layer, который может явно создать:

- `approved`;
- `rejected`;
- `needs_review`;
- `unknown`;
- `proposed`.

### Approval boundary

`approved` в fixture или после approval flow означает только:

- future SQL preview candidate eligibility.

`approved` не означает:

- SQL apply;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- executable SQL;
- apply-ready output.

Только future SQL preview architecture может решить, как читать approved proposals как candidate input.

Даже future SQL preview candidate input не должен автоматически означать SQL apply.

### Запрещено

Local approval fixture / manual review bridge не должен:

- становиться DB storage;
- становиться production storage;
- становиться pipeline stage;
- становиться SQL preview implementation;
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
- генерировать executable SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply.

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

- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`
- `docs/RUNTIME_CHECKS.md`

Связанный коммит:

- `e79e865 Add DB readonly local approval fixture spec`

### Последствие

Следующий безопасный engineering step может быть standalone implementation local approval fixture bridge.

Он должен оставаться local review artifact/process.

Нельзя подключать fixture bridge к pipeline или SQL/apply path без отдельного architecture decision.

## 2026-07-06 — Local review fixture generation создаёт только human-reviewable JSON

### Решение

Local review fixture generation может использоваться только как standalone generation/export step, который превращает output standalone parser-а в human-reviewable local JSON fixture.

Generator создаёт review artifact для человека, но не выполняет approval, normalization, SQL preview или apply.

Минимальный поток:

- standalone parser output;
- local review JSON fixture;
- human review;
- standalone fixture bridge;
- standalone approval flow.

### Причина

Standalone parser уже может создавать `normalization_value_proposals`, но эти proposals неудобны для ручной проверки напрямую.

Нужен безопасный промежуточный generation step, который:

- берёт parser proposals;
- сохраняет parser-owned proposal facts;
- добавляет пустой reviewer-owned `review` block;
- готовит JSON artifact для ручной проверки;
- не меняет proposal semantics;
- не принимает review decisions.

### Разрешено

Generator может читать standalone parser output:

- `normalization_value_proposals`;
- `parser_diagnostics`;
- `source`.

Generator может создавать local JSON fixture shape:

- `source`;
- `fixture_type = db_readonly_normalization_review`;
- `generated_at`;
- `generator_mode = standalone_local_review_fixture_generation`;
- `proposals[]`.

Каждый proposal row может содержать parser-owned fields:

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

Каждый proposal row должен получить пустой reviewer-owned block:

- `review.action = ""`;
- `review.reviewer = ""`;
- `review.review_note = ""`.

### Граница review block

Generator должен создавать только пустой `review` block.

Generator не должен:

- pre-approve proposals;
- выставлять `approved`;
- выставлять `rejected`;
- заполнять reviewer identity;
- писать review notes;
- менять `approval_status`;
- выполнять status transitions.

Review fields должны заполняться только человеком или future explicit review tool.

### File/output boundary

Generated fixture files являются local review artifacts.

Они не являются:

- production storage;
- DB storage;
- SQL file;
- SQL diff;
- apply plan;
- pipeline input by default;
- SQL preview input by default.

Рекомендуемый future local-only path:

- `framework-standardization/var/review-fixtures/*.json`

Generated fixture files не должны попадать в git по умолчанию, особенно если они содержат local dump facts.

Создание директории `var`, `.gitignore` rules и actual fixture files должно быть отдельным implementation step.

### Bridge relation

Generated fixture может позже быть:

- отредактирован человеком в `review` blocks;
- загружен как PHP array;
- передан в `DbReadOnlyLocalApprovalFixtureBridge::applyFixture($fixture)`.

Bridge отвечает за mapping:

- fixture `proposals[]` -> `$proposals`;
- non-empty `review.action` rows -> `$reviewActions`.

Status transitions принадлежат только:

- `DbReadOnlyNormalizationApprovalFlow`.

Generator не должен выполнять status transitions.

### Запрещено

Local review fixture generator не должен:

- создавать `approved`;
- создавать `rejected`;
- менять proposals;
- менять parser diagnostics;
- выполнять approval flow;
- вызывать `DbReadOnlyLocalApprovalFixtureBridge`;
- вызывать SQL preview;
- менять `safe_to_apply`;
- менять `statements`;
- генерировать executable SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать live DB;
- выполнять write/schema operations;
- менять pipeline wiring;
- менять runners;
- менять default dry-run path.

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

- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`
- `docs/RUNTIME_CHECKS.md`

Связанный коммит:

- `16294c1 Add DB readonly local review fixture generation spec`

### Последствие

Следующий безопасный engineering step может быть standalone implementation local review fixture generator.

Implementation должен оставаться standalone.

Нельзя подключать generator к pipeline, SQL preview или SQL/apply path без отдельного architecture decision.

## 2026-07-06 — Standalone E2E review flow check является только contract check

### Решение

Standalone E2E review flow check может использоваться только как contract check для standalone components.

Проверяемый flow:

- parser output;
- local review fixture generator;
- manual in-memory edit;
- local approval fixture bridge;
- approval flow.

Этот flow не является:

- pipeline stage;
- SQL preview input;
- production storage;
- apply layer.

### Причина

После появления standalone parser, local review fixture generator, local approval fixture bridge и approval flow нужно проверить их совместимость как цепочки.

Но эту совместимость нужно проверять безопасно:

- без подключения к pipeline;
- без записи fixture JSON files;
- без DB;
- без SQL/apply;
- без production storage.

Цель check-а — доказать, что review decisions появляются только после manual edit и только через approval flow.

### Разрешено

Standalone E2E review flow check может:

- подготовить `parserOutput` array;
- вызвать `DbReadOnlyLocalReviewFixtureGenerator::generate($parserOutput)`;
- проверить, что generator создал пустые `review` blocks;
- симулировать manual review изменением fixture array в памяти;
- вызвать `DbReadOnlyLocalApprovalFixtureBridge::applyFixture($editedFixture)`;
- проверить output approval flow:
  - `updated_proposals`;
  - `approval_audit`;
  - `approval_summary`.

Manual review simulation может использовать in-memory actions:

- `approve`;
- `reject`;
- `mark_needs_review`;
- empty `review.action`.

### Граница status transitions

Parser может создавать только:

- `proposed`;
- `needs_review`;
- `unknown`.

Generator не должен создавать:

- `approved`;
- `rejected`.

Bridge не должен напрямую выставлять statuses.

`approved` и `rejected` могут появляться только через:

- `DbReadOnlyNormalizationApprovalFlow`.

Пустой `review.action` не должен менять исходный proposal status.

### Expected contract

Минимальный standalone check должен подтверждать:

Generator facts:

- `fixture_type = db_readonly_normalization_review`;
- `generator_mode = standalone_local_review_fixture_generation`;
- `review.action` пустой до manual edit;
- `writes_files = 0`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`.

Bridge facts:

- непустые `review.action` rows превращаются в `$reviewActions`;
- empty `review.action` пропускается;
- `bridge_mode = standalone_local_fixture_bridge`;
- `sql_generated = 0`;
- `apply_plan_created = 0`;
- `safe_to_apply = 0`.

Approval flow facts:

- explicit actions создают expected status transitions;
- `approval_audit` создаётся только для changed rows;
- `approved` / `rejected` созданы только approval flow.

### Approval boundary

`approved` в standalone E2E check означает только:

- future SQL preview candidate eligibility.

`approved` не означает:

- SQL apply;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- executable SQL;
- apply-ready output.

Даже successful E2E check не разблокирует SQL/apply.

Для SQL preview/apply нужен отдельный production SQL/apply spec и отдельное architecture decision.

### Запрещено

Standalone E2E review flow check не должен:

- менять pipeline wiring;
- подключать parser к `analyze_values`;
- подключать generator к pipeline;
- подключать bridge к pipeline;
- подключать approval flow к SQL preview;
- создавать fixture JSON files;
- создавать `var` directory;
- менять `.gitignore`;
- использовать live DB;
- менять DB/schema;
- выполнять write/schema operations;
- генерировать executable SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Verification boundary

Future check должен выполняться только как temporary standalone PHP snippet/file.

Если создаётся temporary file:

- не commit-ить его;
- удалить после проверки.

После check-а нужно подтвердить:

- generated fixture JSON files не появились в `git status`;
- temporary PHP check file удалён;
- local runtime config не попал в git;
- dump files не попали в git;
- `.gitignore` не менялся, если это не было отдельным explicit step.

### Контекст

Связанные документы:

- `docs/DB_READONLY_STANDALONE_REVIEW_FLOW_CHECK_SPEC.md`;
- `docs/DB_READONLY_NORMALIZATION_PARSER_SKELETON_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`;
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`;
- `docs/DB_READONLY_NORMALIZATION_APPROVAL_FLOW_SPEC.md`;
- `docs/RUNTIME_CHECKS.md`.

Связанный коммит:

- `d0cd6c9 Add DB readonly standalone review flow check spec`

### Последствие

Следующий безопасный engineering step может быть standalone temporary E2E check implementation/run.

Он должен оставаться contract check для standalone components.

Нельзя превращать E2E review flow в pipeline stage, SQL preview input, production storage или apply layer без отдельного architecture decision.

## 2026-07-06 — Local review artifact storage является local-only boundary

### Решение

Local review artifact storage может использоваться только как local-only file boundary для generated review fixture JSON files.

Рекомендуемый future local path:

- `framework-standardization/var/review-fixtures/*.json`

Этот storage boundary не является:

- production storage;
- DB storage;
- pipeline input by default;
- SQL preview input by default;
- SQL file;
- SQL diff;
- apply plan;
- apply layer.

### Причина

Standalone generator уже может создавать JSON-ready PHP array fixture.

Standalone bridge уже может принимать fixture как PHP array.

Standalone E2E review flow уже проверен in memory.

Перед future file-writing step нужно отдельно зафиксировать, где могут жить local review artifacts и почему они не должны попадать в git по умолчанию.

Review fixture JSON files могут содержать local dump facts:

- `product_id`;
- `attribute_id`;
- `target_attribute_id`;
- `original_raw_value`;
- parser diagnostics;
- review notes.

Поэтому default policy:

- generated review fixtures are local-only;
- generated review fixtures are not committed by default.

### Разрешено в future implementation step

Future explicit implementation может:

- создать local directory `framework-standardization/var/review-fixtures`;
- писать generated review fixture JSON files локально;
- загружать reviewed fixture JSON files локально;
- передавать loaded fixture как PHP array в `DbReadOnlyLocalApprovalFixtureBridge::applyFixture($fixture)`;
- удалять local fixture files после manual review/check.

### Git boundary

Generated review fixture JSON files не должны попадать в git по умолчанию.

Перед file-writing implementation нужно отдельным explicit step проверить или добавить `.gitignore` rules.

`.gitignore` changes должны быть отдельным маленьким шагом.

Actual fixture JSON files не должны быть staged/tracked files.

Рекомендуемый ignore target для future step:

- `framework-standardization/var/review-fixtures/*.json`

Если понадобится sanitized sample fixture для docs/tests, это должен быть отдельный spec и отдельный explicit commit.

### File naming boundary

Future fixture filenames должны быть descriptive local names, например:

- `pump_diameter_YYYYMMDD_HHMMSS.review.json`

Имена файлов не должны использовать executable/apply naming:

- `.sql`;
- `apply`;
- `production`;
- `migration`;
- `patch`.

### Запрещено

Local review artifact storage не должен:

- становиться production storage;
- становиться DB storage;
- становиться pipeline stage;
- становиться SQL preview input by default;
- становиться apply plan;
- подключать generator к pipeline;
- подключать bridge к pipeline;
- подключать approval flow к SQL preview;
- генерировать executable SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать live DB;
- менять DB/schema;
- выполнять write/schema operations.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Verification boundary

Future implementation должен подтвердить:

- `.gitignore` protects `framework-standardization/var/review-fixtures/*.json`;
- generated fixture file appears locally but is not staged/tracked;
- generated fixture contains no SQL content;
- SQL/apply artifacts are not created;
- default dry-run remains ok;
- DB-readonly runner remains ok;
- git status clean except intentional source/doc changes.

### Контекст

Связанные документы:

- `docs/DB_READONLY_LOCAL_REVIEW_ARTIFACT_STORAGE_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`;
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`;
- `docs/DB_READONLY_STANDALONE_REVIEW_FLOW_CHECK_SPEC.md`;
- `docs/RUNTIME_CHECKS.md`.

Связанный коммит:

- `87d8089 Add DB readonly local review artifact storage spec`

### Последствие

Следующий безопасный engineering step может быть `.gitignore` boundary для local review fixtures.

Нельзя реализовывать запись review fixture JSON files на диск до явной проверки/настройки git-ignore boundary.

## 2026-07-06 — Local review fixture writer является только standalone local file writer

### Решение

Local review fixture writer может использоваться только как standalone local file writer для human review fixture artifacts.

Рекомендуемый future class:

- `src/Approval/DbReadOnlyLocalReviewFixtureWriter.php`

Минимальный future API:

- `write($fixture, $filename = null)`

Writer может писать только в local ignored path:

- `framework-standardization/var/review-fixtures/*.json`

Writer не является:

- production storage;
- DB storage;
- pipeline stage;
- SQL preview input;
- SQL file generator;
- SQL diff generator;
- apply plan;
- apply layer.

### Причина

Standalone generator уже создаёт JSON-ready PHP array fixture.

`.gitignore` уже защищает future local review fixture JSON files:

- `framework-standardization/var/review-fixtures/*.json`

Теперь можно проектировать writer, но только как локальную запись review artifact для человека.

Writer не должен принимать review decisions, менять statuses или приближать систему к SQL/apply.

### Разрешено в future implementation step

Writer может:

- принимать JSON-ready fixture array;
- принимать optional local filename;
- создавать directory `framework-standardization/var/review-fixtures`, если её нет;
- записывать `.json` file локально;
- возвращать writer diagnostics:
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

### Path boundary

Writer должен писать только внутрь:

- `framework-standardization/var/review-fixtures/`

Writer не должен:

- принимать absolute paths;
- принимать path traversal;
- писать за пределы allowed directory;
- перезаписывать существующие files по умолчанию без отдельной explicit future option.

### Filename boundary

Разрешены только safe `.json` filenames.

Пример safe filename:

- `pump_diameter_YYYYMMDD_HHMMSS.review.json`

Filename не должен содержать executable/apply naming:

- `.sql`;
- `apply`;
- `production`;
- `migration`;
- `patch`.

Executable extensions запрещены.

### Git boundary

Generated fixture JSON files не должны попадать в git по умолчанию.

Future implementation должен подтвердить:

- generated fixture file существует локально;
- generated fixture file не staged;
- generated fixture file не tracked;
- `.gitignore` защищает `framework-standardization/var/review-fixtures/*.json`.

Actual fixture JSON files нельзя коммитить по умолчанию.

Если понадобится sanitized sample fixture, это должен быть отдельный spec и отдельный explicit commit.

### Запрещено

Local review fixture writer не должен:

- генерировать fixture content;
- менять `approval_status`;
- создавать `approved`;
- создавать `rejected`;
- выполнять approval flow;
- вызывать `DbReadOnlyLocalApprovalFixtureBridge`;
- вызывать SQL preview;
- менять `safe_to_apply`;
- менять `statements`;
- подключаться к pipeline;
- менять runners;
- использовать DB;
- использовать live DB;
- менять DB/schema;
- выполнять write/schema operations;
- генерировать executable SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Verification boundary

Future implementation должен проверить:

- syntax check writer class через PHP 5.6;
- small fixture array written to local ignored path;
- generated file has `.json` extension;
- generated file contains no SQL content;
- generated file is ignored / not staged / not tracked;
- default dry-run remains ok;
- DB-readonly runner remains ok;
- SQL/apply artifacts are not created.

### Контекст

Связанные документы:

- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_WRITER_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_ARTIFACT_STORAGE_SPEC.md`;
- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_GENERATION_SPEC.md`;
- `docs/DB_READONLY_LOCAL_APPROVAL_FIXTURE_SPEC.md`;
- `docs/DB_READONLY_STANDALONE_REVIEW_FLOW_CHECK_SPEC.md`;
- `docs/RUNTIME_CHECKS.md`.

Связанные коммиты:

- `1886a40 Add DB readonly local review fixture writer spec`;
- `668f9ba Ignore local review fixture artifacts`.

### Последствие

Следующий безопасный engineering step может быть standalone implementation local review fixture writer.

Implementation должен оставаться local file writer only.

Нельзя подключать writer к pipeline, SQL preview или SQL/apply path без отдельного architecture decision.

## 2026-07-06 — Implemented local review fixture writer остаётся standalone-only

### Решение

Реализованный `DbReadOnlyLocalReviewFixtureWriter` остаётся только standalone local file writer для human review fixture artifacts.

Класс:

- `src/Approval/DbReadOnlyLocalReviewFixtureWriter.php`

Writer не является:

- production storage;
- DB storage;
- pipeline stage;
- SQL preview input by default;
- SQL/apply layer.

### Разрешено

Writer может:

- принимать JSON-ready review fixture array;
- писать только local ignored JSON artifacts в `framework-standardization/var/review-fixtures/*.json`;
- создавать local target directory при необходимости;
- возвращать writer diagnostics;
- использоваться только в manual/local review workflow.

### Запрещено

Writer не должен:

- подключаться к pipeline;
- подключаться к runners;
- использоваться как production storage;
- использоваться как DB storage;
- использоваться как SQL preview input by default;
- вызывать parser;
- вызывать generator;
- вызывать bridge;
- вызывать approval flow;
- вызывать SQL preview;
- менять `approval_status`;
- создавать `approved`;
- создавать `rejected`;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать DB;
- использовать live DB;
- выполнять DB/schema operations.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

Writer нужен только для локальной записи JSON-ready review fixture, чтобы человек мог вручную проверить или отредактировать review artifact.

Implementation не меняет архитектуру pipeline и не приближает систему к SQL/apply.

### Последствие

После implementation writer-а дальнейшие шаги должны оставаться разделёнными:

- writer = local file artifact only;
- manual review = human-owned;
- bridge/approval flow = отдельная standalone chain;
- SQL/apply architecture = отдельное future decision;
- pipeline wiring = отдельное future decision, сейчас запрещено.

### Контекст

Связанные документы:

- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_WRITER_SPEC.md`;
- `docs/RUNTIME_CHECKS.md`.

Связанные коммиты:

- `5b83d6e Add DB readonly local review fixture writer`;
- `3693117 Document DB readonly local review fixture writer checks`.

## 2026-07-06 — Local review fixture loader должен оставаться standalone-only

### Решение

Future `DbReadOnlyLocalReviewFixtureLoader` должен быть только standalone local JSON reader для human review fixture artifacts.

Future class:

- `src/Approval/DbReadOnlyLocalReviewFixtureLoader.php`

Spec:

- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_LOADER_SPEC.md`

Loader не является:

- pipeline stage;
- production storage;
- DB storage;
- SQL preview input by default;
- SQL/apply layer;
- OpenCart runtime.

### Разрешено

Loader может:

- читать только local `.json` fixture files из `framework-standardization/var/review-fixtures/`;
- принимать только local filename, не arbitrary path;
- декодировать JSON в PHP array;
- возвращать loader result / diagnostics;
- использоваться только в manual/local review workflow между manually edited JSON file и bridge.

### Запрещено

Loader не должен:

- подключаться к pipeline;
- подключаться к runners;
- использоваться как production storage;
- использоваться как DB storage;
- использоваться как SQL preview input by default;
- принимать absolute paths;
- принимать path traversal;
- читать из `docs`, `src`, `config` или project root;
- читать SQL/apply-like filenames;
- выполнять содержимое файла;
- интерпретировать SQL;
- вызывать bridge;
- вызывать approval flow;
- менять `review.action`;
- менять `approval_status`;
- создавать `approved`;
- создавать `rejected`;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать DB;
- использовать live DB;
- выполнять DB/schema operations.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

Loader нужен только как безопасная граница чтения локального JSON review artifact после ручного редактирования.

Он не должен превращать manual fixture artifact в production decision, SQL preview input или apply-ready source.

### Последствие

Дальнейшая chain остаётся разделённой:

- writer = local JSON artifact writer;
- human/manual review = владелец review edits;
- loader = local JSON artifact reader;
- bridge = отдельная standalone conversion boundary;
- approval flow = отдельная standalone status transition boundary;
- SQL/apply architecture = отдельное future decision;
- pipeline wiring = отдельное future decision, сейчас запрещено.

### Контекст

Связанные документы:

- `docs/DB_READONLY_LOCAL_REVIEW_FIXTURE_LOADER_SPEC.md`.

Связанный коммит:

- `5358890 Add DB readonly local review fixture loader spec`.

## 2026-07-06 — Review chain result reporter должен оставаться standalone reporting-only

### Решение

Future `DbReadOnlyReviewChainResultReporter` должен быть только standalone reporting/diagnostics boundary после approval flow.

Future class:

- `src/Approval/DbReadOnlyReviewChainResultReporter.php`

Spec:

- `docs/DB_READONLY_REVIEW_CHAIN_RESULT_REPORTER_SPEC.md`

Reporter не является:

- pipeline stage;
- runner integration;
- SQL preview input by default;
- production normalization;
- production storage;
- DB storage;
- apply-ready output;
- SQL/apply layer.

### Разрешено

Reporter может:

- принимать result из standalone approval flow;
- считать summary counts по statuses;
- показывать количество proposals;
- показывать количество `approved`;
- показывать количество `rejected`;
- показывать количество `needs_review`;
- показывать количество `unknown`;
- показывать количество `proposed`;
- показывать unsupported/unsafe statuses как diagnostics;
- возвращать human-readable/reporting diagnostics;
- явно показывать, что SQL/apply still blocked;
- использоваться только standalone/manual.

### Запрещено

Reporter не должен:

- менять statuses;
- создавать `approved`;
- создавать `rejected`;
- принимать review decisions;
- вызывать bridge;
- вызывать approval flow;
- вызывать SQL preview;
- генерировать SQL;
- создавать SQL files;
- создавать SQL diff;
- создавать apply plan;
- выполнять SQL apply;
- использовать DB;
- использовать live DB;
- менять DB/schema;
- подключаться к pipeline;
- подключаться к runners;
- менять default dry-run path.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Approval boundary

`approved` остаётся только review-chain status.

`approved` не означает:

- SQL apply allowed;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

`rejected`, `needs_review`, `unknown` и `proposed` также не являются SQL/apply instructions.

### Причина

После approval flow нужен безопасный reporting layer, который показывает человеку итог review chain, но не превращает `approved` / `rejected` statuses в SQL/apply permission или production normalization.

### Последствие

Дальнейшая chain остаётся разделённой:

- writer = local JSON artifact writer;
- human/manual review = владелец review edits;
- loader = local JSON artifact reader;
- bridge = standalone conversion boundary;
- approval flow = standalone status transition boundary;
- result reporter = standalone reporting/diagnostics boundary;
- SQL/apply architecture = отдельное future decision;
- pipeline wiring = отдельное future decision, сейчас запрещено.

### Контекст

Связанные документы:

- `docs/DB_READONLY_REVIEW_CHAIN_RESULT_REPORTER_SPEC.md`;
- `docs/RULES.md`.

## 2026-07-07 — Standalone review chain E2E check должен оставаться diagnostic-only и standalone-only

### Решение

Standalone review chain E2E check нужен только для проверки совместимости standalone review chain components.

Chain под проверкой:

```text
parser output
-> generator
-> writer
-> local ignored JSON
-> manual review
-> loader
-> bridge
-> approval flow
-> reporter
```

Future checker class допустим только как standalone diagnostic tool:

```text
src/Approval/DbReadOnlyStandaloneReviewChainE2EChecker.php
```

E2E checker не является:

- pipeline stage;
- runner integration;
- SQL preview input;
- production output;
- SQL/apply layer.

### Границы

Generated local fixture JSON должен удаляться после check.

Fixture JSON не коммитится.

`approved` остаётся только review-chain status.

`approved` не означает:

- SQL apply allowed;
- `safe_to_apply = 1`;
- `production_ready = 1`;
- apply-ready output.

### Запрещено

Standalone review chain E2E checker не должен:

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
- менять default dry-run path.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

После реализации standalone chain до reporter включительно нужен безопасный способ проверить совместимость всей chain целиком.

Такая проверка должна оставаться diagnostic-only: она подтверждает, что standalone components могут пройти через synthetic/manual local review workflow, но не создаёт production output и не приближает систему к SQL/apply.

### Последствие

Дальнейшие шаги должны оставаться разделёнными:

- standalone E2E check = diagnostics/compatibility boundary;
- generated fixture JSON = temporary local ignored artifact;
- approval flow statuses = review-chain statuses only;
- SQL/apply architecture = отдельное future decision;
- pipeline wiring = отдельное future decision, сейчас запрещено.

### Контекст

Связанный документ:

- `docs/DB_READONLY_STANDALONE_REVIEW_CHAIN_E2E_CHECK_SPEC.md`.

## 2026-07-07 — Review chain result export artifact не реализуется сейчас; возможен только как future diagnostic-only boundary

### Решение

Сейчас отдельный review-chain result export writer не нужен.

Причины:

- `DbReadOnlyStandaloneReviewChainE2EChecker` уже возвращает diagnostics;
- `DbReadOnlyReviewChainResultReporter` уже возвращает summary;
- observed facts уже фиксируются в `docs/RUNTIME_CHECKS.md`;
- дополнительный artifact layer увеличит surface area;
- дополнительный artifact layer повышает риск восприятия файла как production/export output.

Возможный future class допустим только как option:

```text
src/Approval/DbReadOnlyReviewChainResultExportWriter.php
```

Future implementation допустима только после отдельного explicit decision step.

### Границы future artifact

Если когда-либо будет реализован, artifact должен быть только local ignored diagnostic/reporting snapshot.

Обязательные границы:

- target dir только внутри `framework-standardization/var/`;
- no SQL content;
- no apply plan;
- no production output;
- no committed runtime artifacts;
- `approved` остаётся только review-chain status, не SQL/apply permission.

Future artifact не должен становиться:

- pipeline stage;
- runner integration;
- SQL preview input;
- production export;
- SQL/apply layer.

### Запрещено

Для review-chain result export/report artifact boundary запрещено:

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
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

Текущий safe layer уже достаточен:

```text
DbReadOnlyStandaloneReviewChainE2EChecker result
-> reporter_summary
-> e2e_diagnostics
-> component_diagnostics
-> runtime checks documentation
```

Отдельный persisted artifact может быть полезен только при явной потребности в human-readable diagnostic snapshot.

Без такой потребности новый file artifact добавит риск неправильного использования как production/export output или SQL preview input.

### Последствие

Следующие шаги не должны реализовывать `DbReadOnlyReviewChainResultExportWriter` без отдельного explicit decision.

Если future decision разрешит artifact layer, он должен оставаться local ignored diagnostic/reporting-only boundary без SQL/apply и production semantics.

### Контекст

Связанный документ:

- `docs/DB_READONLY_REVIEW_CHAIN_RESULT_EXPORT_BOUNDARY_SPEC.md`.

## 2026-07-07 — First real-data review-chain usage scenario должен оставаться controlled standalone readonly scenario

### Решение

Первый real-data usage scenario нужен только для controlled проверки уже собранной standalone review-chain.

Допустимый input:

- local readonly snapshot;
- local readonly fixture;
- dump-derived test input;
- маленький контролируемый набор строк.

Real-data rows должны оборачиваться в parser-like output.

Scenario может доводить standalone chain до:

- review-ready diagnostics;
- reporter summary;
- E2E diagnostics.

Scenario не является:

- pipeline stage;
- runner integration;
- SQL preview input;
- production output.

### Границы input

Запрещено использовать:

- live DB;
- production DB;
- full category batch;
- arbitrary uploaded data;
- OpenCart runtime path;
- production data source.

Scenario не должен менять production data.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Запрещено

Для первого real-data review-chain usage scenario запрещено:

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
- production output;
- committed runtime artifacts.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

После synthetic/in-memory E2E checker нужен следующий безопасный шаг: проверить, что standalone review-chain может подготовить review-ready diagnostics на real-data-like input.

Это должно быть сделано на small controlled readonly slice, а не на full category batch и не через pipeline/runners.

SQL preview и SQL/apply преждевременны, потому что review-chain statuses не являются SQL/apply permission.

### Next-step boundary

Implementation допустима только после отдельного explicit `+`.

Если implementation будет подтверждена, она должна быть minimal standalone usage checker/fixture command.

Такой implementation не должен:

- менять pipeline;
- менять runners;
- менять default dry-run path;
- использовать live DB;
- генерировать SQL/apply;
- создавать production output.

### Контекст

Связанный документ:

- `docs/DB_READONLY_REAL_DATA_REVIEW_CHAIN_USAGE_SCENARIO_SPEC.md`.

## 2026-07-07 — First real-data usage input source должен быть small local readonly fixture/snapshot

### Решение

Первый concrete input source для `DbReadOnlyRealDataReviewChainUsageChecker` должен быть маленьким local readonly dump-derived/test fixture.

Preferred context:

- `pump_diameter`.

Prepared fixture может содержать:

- до 12 controlled rows.

Первый actual run должен брать только маленький slice:

- `input_rows_count <= 2`.

Формат rows:

- `product_id`;
- `attribute_id`;
- `attribute_name`;
- `raw_value`;
- `normalized_value`;
- `confidence`.

Input должен быть readonly и local.

### Границы input

Запрещено:

- live DB;
- production DB;
- full category batch;
- arbitrary uploaded data;
- OpenCart runtime paths;
- production data changes.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Запрещено

Для first real-data usage input source запрещено:

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
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

Первый real-data-like check должен быть максимально малым и controlled.

Prepared fixture до 12 rows даёт достаточно examples для ручной подготовки, но first actual run остаётся ограниченным `input_rows_count <= 2`, чтобы не перейти к batch-like behavior.

Такой input source проверяет usage checker и standalone review-chain без live DB, без pipeline/runners, без SQL/apply и без production semantics.

### Next-step boundary

После decision implementation допустима только после explicit `+`.

Если implementation будет подтверждена, она должна быть минимальной standalone local fixture / manual check path.

Не добавлять постоянный runner.

Не подключать к pipeline.

Implementation не должен:

- менять pipeline;
- менять runners;
- менять default dry-run path;
- использовать live DB;
- генерировать SQL/apply;
- создавать production output.

### Контекст

Связанный документ:

- `docs/DB_READONLY_FIRST_REAL_DATA_USAGE_INPUT_SOURCE_SPEC.md`.

## 2026-07-07 — First real-data usage manual command должен оставаться standalone manual entrypoint

### Решение

Future command:

```text
bin/db-readonly-first-real-data-usage-check.php
```

допустим только как standalone manual command для первого controlled real-data-like usage check.

Command должен связывать:

```text
DbReadOnlyFirstRealDataUsageInputFixture::getFirstRunSlice(2)
-> DbReadOnlyRealDataReviewChainUsageChecker::run($readonlyInput)
```

Command может печатать только concise diagnostics:

- `used`;
- `review_ready`;
- `input_rows_count`;
- `e2e_checked`;
- SQL/apply markers;
- `errors_count`;
- `warnings_count`.

Command не является:

- pipeline stage;
- runner integration;
- SQL preview input;
- production output.

Command не должен принимать arbitrary input, filenames, paths или URLs.

Command не должен использовать live DB или production DB.

Command не должен делать SQL/apply.

Command не должен менять default dry-run path.

Implementation допустима только после explicit `+`.

### Запрещено

Для first real-data usage manual command запрещено:

- pipeline wiring;
- runner integration;
- live DB / production DB;
- full category batch;
- arbitrary input;
- filenames/paths/URLs input;
- SQL preview;
- SQL generation/files/diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Причина

Manual command может дать безопасный локальный entrypoint для already implemented controlled fixture provider and usage checker.

Но такой entrypoint не должен расширять архитектурную поверхность до pipeline/runners, arbitrary input, live DB или SQL/apply.

Первый real-data-like check должен оставаться минимальным, readonly и diagnostic-only.

### Последствие

Следующий implementation step, если будет явно разрешён, должен создать только минимальный standalone manual command.

Он не должен:

- добавлять постоянный runner;
- подключаться к pipeline;
- принимать external input;
- использовать live DB;
- генерировать SQL/apply;
- создавать production output.

### Контекст

Связанный документ:

- `docs/DB_READONLY_FIRST_REAL_DATA_USAGE_MANUAL_COMMAND_SPEC.md`.

## 2026-07-07 — Real-data usage review batch expansion должен оставаться small standalone readonly batch

### Решение

Controlled review batch expansion допустим только после successful first manual command baseline.

Baseline:

- context: `pump_diameter`;
- prepared fixture: `4` controlled rows;
- first manual command использовал `getFirstRunSlice(2)`;
- first manual command вернул `used = 1`;
- first manual command вернул `review_ready = 1`;
- first manual command вернул `e2e_checked = 1`;
- first manual command вернул `input_rows_count = 2`.

Future expansion может использовать только малый batch из prepared fixture.

Recommended first expanded batch:

```text
input_rows_count = 4
```

Expansion не должен превышать documented prepared fixture max:

```text
input_rows_count <= 12
```

Expansion остаётся:

- manual;
- standalone;
- readonly;
- diagnostic/review-chain check only.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Запрещено

Для real-data usage review batch expansion запрещено:

- pipeline wiring;
- runner integration;
- live DB / production DB;
- full category batch;
- arbitrary input;
- filenames/paths/URLs input;
- SQL preview;
- SQL generation/files/diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

Текущий first-run manual command уже подтвердил safe readonly path на `2` rows.

Следующий осторожный шаг может проверить, что standalone review-chain остаётся stable на малом batch из already prepared fixture rows.

Но expansion не должен превращаться в full category batch, pipeline/runner integration, SQL preview или production workflow.

### Next-step boundary

Implementation допустима только после explicit `+`.

Если implementation будет подтверждена, она должна быть minimal standalone manual command/check path.

Implementation не должен:

- добавлять постоянный runner;
- подключаться к pipeline;
- принимать arbitrary input;
- принимать filenames/paths/URLs;
- использовать live DB;
- генерировать SQL/apply;
- создавать production output;
- менять default dry-run path.

### Контекст

Связанный документ:

- `docs/DB_READONLY_REAL_DATA_USAGE_REVIEW_BATCH_EXPANSION_SPEC.md`.

## 2026-07-07 — Prepared fixture expansion должен оставаться small local readonly fixture expansion

### Решение

Prepared fixture expansion допустим только для context:

```text
pump_diameter
```

Current baseline:

```text
DbReadOnlyFirstRealDataUsageInputFixture
```

Current prepared fixture rows count:

```text
4
```

Expanded batch command уже успешно прошёл:

```text
input_rows_count = 4
```

Future expansion может увеличить prepared fixture до:

```text
prepared rows count > 4
prepared rows count <= 12
```

Rows должны оставаться:

- local;
- readonly;
- dump-derived/test-like;
- controlled.

Row format сохраняется:

- `product_id`;
- `attribute_id`;
- `attribute_name`;
- `raw_value`;
- `normalized_value`;
- `confidence`.

Prepared fixture expansion не должен добавлять:

- external file reading;
- CLI input;
- DB access.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Запрещено

Для prepared fixture expansion запрещено:

- pipeline wiring;
- runner integration;
- live DB / production DB;
- full category batch;
- arbitrary input;
- filenames/paths/URLs input;
- SQL preview;
- SQL generation/files/diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

Current prepared fixture уже доказал safe path на `4` rows через expanded batch command.

Следующий controlled step может увеличить local fixture coverage, но только в пределах documented max `<= 12 rows`.

Это должно оставаться local readonly fixture expansion, а не новым runtime data source, pipeline step, live DB integration или production workflow.

### Next-step boundary

Implementation допустима только после explicit `+`.

Implementation должна быть minimal controlled update of fixture provider.

Если manual command boundary нужно расширить, это должно оставаться standalone/manual, без runner/pipeline.

Не добавлять постоянный runner.

Implementation не должен:

- подключаться к pipeline;
- подключаться к runners;
- читать external files;
- принимать CLI arbitrary input;
- использовать DB/live DB;
- генерировать SQL/apply;
- создавать production output;
- менять default dry-run path.

### Контекст

Связанный документ:

- `docs/DB_READONLY_PREPARED_FIXTURE_EXPANSION_SPEC.md`.

## 2026-07-07 — Prepared fixture full batch check должен оставаться standalone manual readonly check

### Решение

Prepared fixture full batch check допустим только для текущего prepared fixture set.

Context:

```text
pump_diameter
```

Current prepared fixture rows count:

```text
8
```

Full batch check должен брать весь текущий prepared fixture set.

Expected input:

```text
input_rows_count = 8
input_rows_count > 4
input_rows_count <= 12
```

Future command допустим только как standalone manual entrypoint:

```text
bin/db-readonly-prepared-fixture-full-batch-check.php
```

Command не является:

- pipeline stage;
- runner integration;
- SQL preview input;
- production output.

Command не должен:

- принимать arbitrary input;
- принимать filenames, paths или URLs;
- использовать live DB / production DB;
- делать SQL/apply;
- создавать production output;
- менять default dry-run path.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Запрещено

Для prepared fixture full batch manual check запрещено:

- pipeline wiring;
- runner integration;
- live DB / production DB;
- full category batch;
- arbitrary input;
- filenames/paths/URLs input;
- SQL preview;
- SQL generation/files/diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

После расширения prepared fixture до 8 rows и successful checks на 2-row и 4-row manual commands следующий осторожный шаг может проверить весь текущий prepared fixture set.

Такой check должен оставаться manual, standalone и readonly. Он не должен превращаться в full category batch, pipeline/runner integration, production workflow, SQL preview или apply path.

### Next-step boundary

Implementation допустима только после explicit `+`.

Implementation должна быть standalone manual command only.

Не добавлять постоянный runner.

Не подключать к pipeline.

Implementation не должен:

- принимать arbitrary input;
- принимать filenames/paths/URLs;
- использовать live DB;
- использовать production DB;
- генерировать SQL/apply;
- создавать production output;
- менять default dry-run path.

### Контекст

Связанный документ:

- `docs/DB_READONLY_PREPARED_FIXTURE_FULL_BATCH_CHECK_SPEC.md`.

## 2026-07-07 — Next controlled source должен быть second pump_diameter sample source

### Решение

Текущий verified baseline:

- context: `pump_diameter`;
- prepared fixture: `8` controlled readonly rows;
- full batch command successful.

Следующий controlled source выбирается внутри той же характеристики:

```text
pump_diameter
```

Следующий source должен быть second small local readonly sample source.

Сейчас не переходить к новой характеристике.

Сейчас не расширять до full category batch.

Цель следующего source:

- проверить устойчивость standalone review-chain на другом controlled sample source внутри той же характеристики;
- сохранить уже проверенную characteristic boundary;
- не смешивать разные характеристики в одном шаге;
- сохранить простые runtime checks.

Source должен оставаться:

- local;
- readonly;
- dump-derived/test-like;
- controlled.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Запрещено

Для next controlled source запрещено:

- pipeline wiring;
- runner integration;
- live DB / production DB;
- full category batch;
- arbitrary input;
- filenames/paths/URLs input;
- SQL preview;
- SQL generation/files/diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

Prepared `pump_diameter` fixture уже успешно прошёл:

- first manual command на `2` rows;
- review batch command на `4` rows;
- full batch command на `8` rows.

Следующий полезный шаг — проверить устойчивость той же standalone review-chain внутри той же характеристики на другом controlled source, а не увеличивать batch автоматически и не переходить сразу к новой характеристике.

Это минимизирует scope creep, снижает риск смешивания характеристик и сохраняет текущую standalone/manual/readonly модель.

### Next-step boundary

Implementation допустима только после explicit `+`.

Implementation должна быть minimal controlled local fixture/source.

Не добавлять постоянный runner.

Не подключать к pipeline.

Implementation не должен:

- принимать arbitrary input;
- принимать filenames/paths/URLs;
- использовать live DB;
- использовать production DB;
- генерировать SQL/apply;
- создавать production output;
- менять default dry-run path.

### Контекст

Связанный документ:

- `docs/DB_READONLY_NEXT_CONTROLLED_SOURCE_SELECTION_SPEC.md`.

## 2026-07-07 — Pump diameter controlled sources stability criteria должны быть gate перед новой характеристикой

### Решение

Перед переходом к новой характеристике нужно сравнить два controlled readonly sample sources для одной характеристики:

```text
pump_diameter
```

Первый source:

```text
DbReadOnlyFirstRealDataUsageInputFixture
```

Prepared rows count:

```text
8
```

Второй source:

```text
DbReadOnlySecondPumpDiameterUsageInputFixture
```

Prepared rows count:

```text
8
```

Оба source уже проверяются через standalone readonly review-chain.

Full batch checks используют bounded chunking из-за текущего лимита usage checker:

```text
MAX_ROWS = 5
```

Stability gate считается пройденным только если:

- оба source дают `used = 1`;
- оба source дают `review_ready = 1`;
- оба source дают `e2e_checked = 1`;
- full batch по обоим source проходит без errors;
- bounded chunking не ломает aggregate result;
- SQL/apply markers all `0`;
- runtime artifacts не остаются;
- default dry-run и DB-readonly runner остаются ok;
- diagnostics достаточно понятны для manual review.

Только после подтверждения этих criteria можно переходить к spec для первой новой характеристики.

Comparison command не реализовывать без отдельного explicit `+`.

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Запрещено

Для pump diameter controlled sources stability gate запрещено:

- pipeline wiring;
- runner integration;
- live DB / production DB;
- full category batch;
- arbitrary input;
- filenames/paths/URLs input;
- SQL preview;
- SQL generation/files/diff;
- apply plan;
- SQL apply;
- DB/schema changes;
- write/schema operations;
- production output;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Причина

Два controlled `pump_diameter` sources дают минимальную проверку устойчивости внутри одной уже проверенной характеристики.

Gate нужен, чтобы не переходить к новой характеристике на основании одного source и не расширяться автоматически до full category batch, pipeline/runners, SQL preview или production workflow.

### Последствие

Следующий engineering step должен сначала подтвердить или задокументировать stability criteria.

Если criteria подтверждены, следующий безопасный шаг:

```text
spec для первой новой характеристики
```

Implementation comparison command допустима только после отдельного explicit `+`.

Implementation не должен:

- подключаться к pipeline;
- подключаться к runners;
- принимать arbitrary input;
- принимать filenames/paths/URLs;
- использовать live DB;
- использовать production DB;
- генерировать SQL/apply;
- создавать production output;
- менять default dry-run path.

### Контекст

Связанный документ:

- `docs/DB_READONLY_PUMP_DIAMETER_CONTROLLED_SOURCES_STABILITY_SPEC.md`.

## 2026-07-07 — Pump max head должен быть первой новой controlled характеристикой после pump_diameter

### Решение

Первой новой controlled характеристикой после `pump_diameter` выбирается:

```text
attribute_id = 12
attribute_name = Максимальный напор
canonical key = pump_max_head
```

Source discovery:

```text
category_id = 11900213
language_id = 1
direct_products = 1972
usage_count = 385
```

Canonical meaning:

```text
maximum pump head
```

Canonical unit:

```text
m
```

Canonical `normalized_value`:

```text
decimal number in meters
```

Examples:

- `46.5м.` -> `normalized_value = 46.5`, `unit = m`;
- `68м.` -> `normalized_value = 68`, `unit = m`;
- `93м.` -> `normalized_value = 93`, `unit = m`;
- `133м.` -> `normalized_value = 133`, `unit = m`;
- `20.5м` -> `normalized_value = 20.5`, `unit = m`.

`pump_max_head` выбран, потому что:

- имеет высокий coverage;
- значения имеют формат число + метры;
- semantic понятна внутри pump domain;
- contract проще и безопаснее, чем у flow/performance attributes;
- подходит для проверки масштабирования после `pump_diameter`.

Flow/performance attributes postponed из-за production safety note по `max_flow_l_min`.

Не запускать cache rebuild, пока permanent flow normalization не исправлена.

`pump_max_head` нельзя смешивать с `max_flow_l_min`.

### Explicit anti-errors

Запрещено:

- передавать в подборщик raw строку `68м.`;
- терять единицу измерения на уровне contract;
- конвертировать метры в миллиметры;
- конвертировать метры в сантиметры;
- трактовать `68м` как `68 мм`;
- сохранять `normalized_value` как строку с suffix;
- смешивать `Максимальный напор` с `Минимальный напор`;
- смешивать max head с flow / `max_flow_l_min`.

### Границы

Это решение является standalone / DB-readonly boundary.

В этом шаге запрещено:

- PHP implementation;
- config/jobs changes;
- pipeline wiring;
- runner integration;
- SQL preview;
- SQL generation/files/diff;
- apply plan;
- SQL apply;
- live DB / production DB;
- DB/schema changes;
- write/schema operations;
- production output;
- cache rebuild;
- committed runtime artifacts;
- default dry-run path changes.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

`approved` остаётся только review-chain status.

`approved` не означает SQL/apply permission.

### Причина

Readonly discovery по категории `Скважинные насосы` показал, что `Максимальный напор` имеет высокий usage count и clean numeric meter values.

Это делает `pump_max_head` более безопасным первым новым controlled source, чем flow/performance attributes, где есть production risk вокруг `max_flow_l_min`, dual-unit semantics и cache rebuild safety.

### Последствие

Следующий implementation step допустим только после отдельного explicit `+`.

Future implementation должна оставаться small controlled readonly fixture/source и не должна:

- подключаться к pipeline;
- подключаться к runners;
- принимать arbitrary input;
- принимать filenames/paths/URLs;
- использовать live DB;
- использовать production DB;
- генерировать SQL/apply;
- создавать production output;
- менять default dry-run path.

### Контекст

Связанный документ:

- `docs/DB_READONLY_PUMP_MAX_HEAD_CONTROLLED_SOURCE_SPEC.md`.

## 2026-07-07 — Framework standardization as controlled attribute consolidation workflow

### Решение

Framework standardization работает как controlled attribute consolidation workflow, а не как fully automatic normalizer.

Основной workflow:

1. User задаёт целевой смысл характеристики.
2. System делает readonly discovery похожих attribute names / aliases / duplicates.
3. System показывает candidates:
   - `attribute_id`;
   - `attribute_name`;
   - `usage_count`;
   - samples;
   - warnings.
4. User выбирает canonical `attribute_id`.
5. User явно подтверждает included alias `attribute_id`.
6. User явно исключает similar-but-different `attribute_id`.
7. System собирает raw values inventory по выбранной группе.
8. System предлагает canonical unit и `normalized_value` contract.
9. User утверждает canonical unit/contract.
10. System создаёт normalization proposals.
11. User review approves/rejects proposals.
12. Apply plan возможен только отдельным explicit step после review.

`config/jobs` не является начальной точкой угадывания характеристики.

`config/jobs` должен быть результатом принятого canonical decision/contract.

Новая характеристика не должна означать новый уникальный PHP-обработчик.

Архитектурная модель:

- одна характеристика = один job/contract;
- один тип значений = один parser/normalizer family;
- если value semantics уже покрыта существующим parser family, отдельный обработчик под конкретный `attribute_id` не нужен.

Production selector/cache usage требует explicit canonical unit contract до implementation.

`approved` в review chain не означает SQL apply permission.

No auto-apply.

No production cache rebuild without separate explicit approval.

Уже реализованная standalone review-chain остаётся полезной второй половиной workflow:

```text
normalization proposals -> human review -> approval flow -> reporter
```

Недостающая следующая область развития:

```text
attribute name discovery -> canonical selection -> raw values inventory
```

### Причина

Readonly discovery по категории `Скважинные насосы` показал, что похожие названия могут иметь разные смыслы и не должны объединяться автоматически.

Примеры риска:

- `Максимальный напор`;
- `Минимальный напор`;
- `Номинальный напор`;
- `Max напор, м`;
- `Максимальный напор, м.вод.ст.`.

Production incident с `max_flow_l_min` показал риск неправильных единиц измерения и cache rebuild.

На production был временный cache hotfix для Belamos/Pedrollo `max_flow_l_min`.

Production rebuild восстановил старые flow values в шкале `m/h`.

Поэтому любые характеристики, которые могут попасть в selector/cache, требуют explicit canonical unit contract до implementation.

### Границы

Framework не должен сам автоматически объединять похожие характеристики только по названию.

Human canonical selection обязательна.

Apply plan не является частью review approval.

Production/cache actions требуют отдельного explicit approval.

Запрещено:

- pipeline wiring;
- runner integration;
- SQL preview без отдельного explicit step;
- SQL generation;
- SQL files;
- SQL diff;
- apply plan как часть review approval;
- SQL apply;
- live DB / production DB;
- DB/schema changes;
- write/schema operations;
- production/cache changes;
- production cache rebuild без отдельного explicit approval;
- committed runtime artifacts;
- default dry-run path changes;
- automatic alias consolidation только по похожему названию.

Запрещённые operation families:

- `INSERT`
- `UPDATE`
- `DELETE`
- `REPLACE`
- `ALTER`
- `DROP`
- `TRUNCATE`
- `CREATE`

### Последствие

Следующие production-facing steps должны развивать сначала первую половину workflow:

- readonly attribute name discovery;
- candidate grouping;
- alias / duplicate diagnostics;
- human canonical selection;
- raw values inventory;
- explicit unit/contract decision.

Только после этого standalone review-chain должна использоваться для normalization proposals and human review.

SQL/apply и production/cache actions остаются отдельными future decisions.
