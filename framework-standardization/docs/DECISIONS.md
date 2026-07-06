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
