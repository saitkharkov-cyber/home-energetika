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
