# Isolated dump restore plan and command review

## 1. Назначение

Этот документ фиксирует documentation/planning step для будущего restore gate по dump-кандидату:

`_local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql`

Цель будущего восстановления — загрузить этот dump только в новую isolated controlled local DB, не затрагивая текущую `he_framework_local_dump`, production/live DB, cache и runtime configs.

Связь с будущей проверкой: isolated DB нужна как source-based состояние для generic canonical apply dry-run по характеристике `максимальный напор`, где generic command сможет строить plan из alias/source rows по contract `framework-standardization/config/attribute-contracts/max_head_11900213.php`.

Этот документ не является разрешением на restore. Команды ниже являются только `proposed commands for review` и помечены как `НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ`.

## 2. Исходные факты

- Текущая DB из `framework-standardization/config/runtime/local.dump.php`: `he_framework_local_dump`.
- `he_framework_local_dump` уже находится после canonical apply и alias cleanup по `max_head`.
- `he_framework_local_dump` нельзя изменять, очищать, удалять или переиспользовать как target restore.
- Production/live DB не участвуют.
- Dump-кандидат `_local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql` потенциально pre-cleanup, но это ещё не подтверждено DB-backed проверкой.

## 3. Выбор isolated DB identity

Предлагаемое имя новой isolated DB:

`he_framework_isolated_max_head_precleanup`

Статический review имени:

- отличается от `he_framework_local_dump`;
- не похоже на production DB name;
- явно указывает на isolated purpose;
- явно указывает на `max_head`;
- явно указывает на expected precleanup state;
- не содержит динамической даты, потому что для этого gate нужна стабильная human-reviewable identity;
- может быть включено в runtime allowlist отдельным будущим step.

DB на этом шаге не создаётся.

## 4. Preconditions будущего restore gate

Будущий restore разрешён только после проверки:

- working tree clean;
- dump существует;
- dump является обычным файлом;
- dump path совпадает с утверждённым path;
- вычислен SHA-256 checksum;
- checksum записан в runtime report до restore;
- MySQL host является локальным;
- isolated DB ещё не существует либо отдельным gate принято решение о её безопасном удалении;
- текущая DB `he_framework_local_dump` существует отдельно и не является target;
- production/live credentials не используются;
- команда restore явно содержит isolated DB name `he_framework_isolated_max_head_precleanup`;
- пользователь дал отдельный explicit `+`.

## 5. Restore strategy

Рекомендуемая стратегия будущего gate:

1. preflight;
2. checksum dump;
3. проверка отсутствия конфликта имени DB;
4. отдельное создание пустой isolated DB;
5. импорт dump только в isolated DB;
6. post-restore readonly sanity checks;
7. классификация состояния;
8. остановка до отдельного runtime-config gate.

Создание DB, restore и framework dry-run нельзя объединять в одну команду.

## 6. Proposed PowerShell commands for review

Все команды в этом разделе являются proposed commands for review.

НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ.

Команды предполагают PowerShell 7+ и используют `&&` для последовательных read-only шагов там, где это нужно. Реальные credentials и пароли в документ не включаются.

### 6.1 Repository preflight

НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ.

```powershell
git status --short && git log --oneline --decorate -5 && Test-Path -LiteralPath "_local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql" -PathType Leaf && Get-Item -LiteralPath "_local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql" | Select-Object FullName,Length,LastWriteTime
```

Назначение: проверить repo state, последние коммиты, наличие dump, размер и дату изменения dump.

### 6.2 SHA-256 checksum

НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ.

```powershell
Get-FileHash -Algorithm SHA256 -LiteralPath "_local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql"
```

Назначение: получить проверяемую identity dump до любого restore.

### 6.3 Проверка инструментов

НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ.

Безопасный способ сначала определить фактический executable MySQL client:

```powershell
Get-Command mysql -ErrorAction Stop && mysql --version
```

Если используется MariaDB client, сначала определить фактический executable:

```powershell
Get-Command mariadb -ErrorAction Stop && mariadb --version
```

Назначение: показать доступность и версии client tools без DB connection.

### 6.4 Проверка имени isolated DB

НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ.

Future read-only connection command для проверки существования только exact isolated DB name:

```powershell
mysql -h 127.0.1.19 -u <isolated_local_user> -p -N -e "SHOW DATABASES LIKE 'he_framework_isolated_max_head_precleanup';"
```

Назначение: убедиться, что target DB name свободен или явно обнаружен. Пароль в команду не включается. Host должен быть только local.

### 6.5 Создание isolated DB

НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ.

Команда требует отдельного explicit restore gate:

```powershell
mysql -h 127.0.1.19 -u <isolated_local_user> -p -e "CREATE DATABASE he_framework_isolated_max_head_precleanup CHARACTER SET utf8 COLLATE utf8_general_ci;"
```

Назначение: создать только `he_framework_isolated_max_head_precleanup`.

Запрещено добавлять к этой команде удаление или очистку существующих DB, wildcard, переменные с неочевидным DB name, несколько SQL operations, текущую DB как target.

### 6.6 Импорт dump

НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ.

Команда требует отдельного explicit restore gate:

```powershell
mysql -h 127.0.1.19 -u <isolated_local_user> -p he_framework_isolated_max_head_precleanup < _local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql
```

Назначение: импортировать утверждённый dump path только в isolated DB.

Команда не должна изменять dump, не должна использовать текущую DB, не должна использовать production DB и не должна скрывать target DB через pipe или process substitution.

### 6.7 Post-restore sanity checks

НЕ ВЫПОЛНЯТЬ НА ЭТОМ ШАГЕ.

Readonly check текущей выбранной DB:

```powershell
mysql -h 127.0.1.19 -u <isolated_local_user> -p -N he_framework_isolated_max_head_precleanup -e "SELECT DATABASE();"
```

Readonly check количества таблиц:

```powershell
mysql -h 127.0.1.19 -u <isolated_local_user> -p -N he_framework_isolated_max_head_precleanup -e "SHOW TABLES;"
```

Readonly check наличия ключевых OpenCart tables:

```powershell
mysql -h 127.0.1.19 -u <isolated_local_user> -p -N he_framework_isolated_max_head_precleanup -e "SHOW TABLES LIKE 'oc_product_attribute';"
```

Readonly check category scope `11900213`:

```powershell
mysql -h 127.0.1.19 -u <isolated_local_user> -p -N he_framework_isolated_max_head_precleanup -e "SELECT COUNT(*) FROM oc_category WHERE category_id = 11900213;"
```

Readonly check attribute IDs `12,101,119,81`:

```powershell
mysql -h 127.0.1.19 -u <isolated_local_user> -p -N he_framework_isolated_max_head_precleanup -e "SELECT attribute_id, COUNT(*) FROM oc_product_attribute WHERE attribute_id IN (12,101,119,81) GROUP BY attribute_id;"
```

Эти checks отделены от restore и не содержат write operations.

## 7. Static command review

| Command family | Target host | Target DB | Read-only или write | Destructive potential | Может затронуть `he_framework_local_dump` | Может затронуть production | Только после отдельного gate | Что проверить вручную |
|---|---|---|---|---|---|---|---|---|
| Repository preflight | нет DB host | нет DB | read-only filesystem/git | нет | нет | нет | нет | working tree clean, dump path, size, modified time |
| SHA-256 checksum | нет DB host | нет DB | read-only filesystem | нет | нет | нет | нет | checksum записан в отчёт до restore |
| Проверка инструментов | нет DB host | нет DB | read-only local executable lookup | нет | нет | нет | нет | какой client найден, версия client |
| Проверка имени isolated DB | `127.0.1.19` | только name lookup | read-only DB metadata | низкий, если credentials local-only | нет, target name literal isolated | нет, если host/credentials local-only | да | host local, пароль не в command history, output пустой или понятный |
| Создание isolated DB | `127.0.1.19` | `he_framework_isolated_max_head_precleanup` | write DB metadata | создаёт новую DB | нет, если target literal isolated | нет, если host/credentials local-only | да | target DB literal, no existing DB conflict, нет текущей DB в target |
| Импорт dump | `127.0.1.19` | `he_framework_isolated_max_head_precleanup` | write только isolated DB | заполняет isolated DB | нет, если target literal isolated | нет, если host/credentials local-only | да | target DB виден явно, dump path утверждён, checksum совпадает |
| Post-restore sanity checks | `127.0.1.19` | `he_framework_isolated_max_head_precleanup` | read-only DB checks | нет | нет | нет, если host/credentials local-only | да | выбранная DB, tables, category, attributes, no write commands |

## 8. Safety invariants

Обязательные invariants:

- restore target всегда literal: `he_framework_isolated_max_head_precleanup`;
- `he_framework_local_dump` не встречается в restore target;
- production DB name отсутствует;
- `DROP DATABASE` отсутствует как proposed command;
- confirm apply flag отсутствует;
- framework command отсутствует в restore command;
- cache command отсутствует;
- dump path указан полностью и однозначно;
- restore и verification разделены;
- runtime config создаётся только отдельным будущим step.

## 9. Secret handling

- Пароли не записывать в repo.
- Пароли не вставлять в command history в открытом виде.
- Не создавать credential files в tracked paths.
- Использовать интерактивный password prompt или уже существующий безопасный локальный механизм.
- Реальные credentials не включать в отчёт Codex.

Не создавать `.env`, config или credential file.

## 10. Stop conditions

Будущий restore должен быть остановлен, если:

- dump checksum не подтверждён;
- dump path отличается;
- isolated DB name отличается;
- isolated DB уже существует;
- для продолжения требуется `DROP DATABASE`;
- host не подтверждён как local;
- credentials могут обращаться к production;
- команда содержит текущую DB как target;
- target DB невозможно однозначно увидеть в команде;
- команда одновременно создаёт DB, импортирует dump и запускает framework;
- git working tree не clean;
- пользователь не дал отдельный explicit `+`.

## 11. Expected future restore evidence

Будущий отчёт должен содержать:

- точный dump path;
- размер;
- modified time;
- SHA-256;
- MySQL client path/version;
- local host;
- isolated DB name;
- результат проверки отсутствия DB до создания;
- факт создания isolated DB;
- exit code restore;
- post-restore table sanity checks;
- подтверждение, что `he_framework_local_dump` не менялась;
- подтверждение отсутствия production/cache действий;
- список выполненных команд;
- `git status --short`.

## 12. Out of scope

Текущий документ не разрешает:

- создание DB;
- restore;
- DB connection;
- SQL execution;
- runtime config;
- PHP/code changes;
- generic dry-run;
- confirm apply flag;
- canonical apply;
- alias cleanup;
- SQL/apply стандартизации;
- production/cache;
- cache rebuild.

## 13. Следующий bounded gate

`isolated dump restore execution approval`

Этот gate должен требовать отдельного explicit `+`.
