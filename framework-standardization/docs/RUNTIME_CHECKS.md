# Runtime Checks

Документ хранит историю ручных запусков и проверок Framework Standardization.

Он фиксирует команды, краткие результаты и проверяемые режимы. Документ не заменяет `docs/HANDOFF.md`.

## DB readonly manual runner runtime-check

Контрольная точка:

```text
DB readonly manual runner runtime-check
```

Коммиты на момент проверки:

```text
c6c19d2 Update handoff after DB readonly manual runner
8d98d61 Add DB readonly manual runner
```

### Проверка 1: DB-readonly manual runner

Команда:

```text
C:\php56\php.exe -c C:\php56\php.ini framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
```

Результат:

```text
runtime_mode: db_readonly
db_backed_stage: resolve_canonical
result_status: ok
warnings_count: 0
errors_count: 0
все 9 stages ok
```

### Проверка 2: обычный dry-run

Команда:

```text
C:\php56\php.exe -c C:\php56\php.ini framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Результат:

```text
result_status: ok
warnings_count: 0
errors_count: 0
все 9 stages ok
```

## 2026-07-06 — DbReadOnlyAttributeExporter standalone check

Commit:

`235a579 Add DB readonly attribute exporter`

Проверялась новая standalone capability:

`framework-standardization/src/Exporter/DbReadOnlyAttributeExporter.php`

Цель проверки:

- подтвердить, что `DbReadOnlyAttributeExporter` работает отдельно от pipeline wiring;
- подтвердить совместимость с real DB product IDs из local dump;
- подтвердить отсутствие регрессии default dry-run path;
- подтвердить, что DB-readonly runner пока не переводился на пару `resolve_scope` / `export_attributes`.

### Syntax check

Команда:

`C:\php56\php.exe -l framework-standardization\src\Exporter\DbReadOnlyAttributeExporter.php`

Результат:

`No syntax errors detected`

### Standalone check against local dump

Проверка выполнялась через временный manual-check файл.

Временный файл после проверки удалён.

Результат:

`exported: 1`

`source: local_dump_db_readonly`

`attributes_count: 72`

`attribute_groups_count: 6`

`product_attributes_count: 4908`

`raw_values_count: 385`

`target_attribute_id: 44`

`first_raw_product_id: 1068`

`first_raw_attribute_id: 44`

Вывод:

- exporter читает данные из local dump DB в read-only режиме;
- exporter работает с реальными `product_id`;
- fixture `product_id = 0` не используется;
- target attribute берётся из resolved canonical data;
- standalone capability готова к будущему paired wiring.

### Default dry-run regression check

Команда:

`C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php`

Результат:

`result_status: ok`

`warnings_count: 0`

`errors_count: 0`

`all 9 stages ok`

Вывод:

- default dry-run path не сломан;
- `bin/dry-run.php` остаётся no-DB;
- fixture path остаётся рабочим.

### Wiring status

Pipeline wiring не менялся.

`DbReadOnlyPipelineFactory` по-прежнему использует:

`DryRunScopeResolver`

`DryRunAttributeExporter`

`bin/db-readonly-run.php` по-прежнему сообщает:

`db_backed_stage: resolve_canonical`

Вывод:

- `DbReadOnlyAttributeExporter` создан только как standalone capability;
- `DbReadOnlyScopeResolver` не подключался в `DbReadOnlyPipelineFactory`;
- `DbReadOnlyAttributeExporter` не подключался в `DbReadOnlyPipelineFactory`;
- DB-backed stage в runner пока только `resolve_canonical`.

### Boundary

Этот шаг не является paired wiring.

Следующий инженерный шаг должен отдельно решать подключение пары:

`DbReadOnlyScopeResolver + DbReadOnlyAttributeExporter`

Подключать только один компонент пары нельзя.
