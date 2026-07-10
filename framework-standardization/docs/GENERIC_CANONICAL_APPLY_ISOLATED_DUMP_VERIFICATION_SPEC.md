# Generic canonical apply isolated dump verification spec

## 1. Назначение

Текущая `he_framework_local_dump` уже находится после Phase 1 canonical value apply и Phase 2 alias cleanup для характеристики `максимальный напор`. Поэтому она не подходит для честного source-based proof generic canonical apply: source alias rows для уже применённых canonical rows частично удалены, а canonical-only verification не доказывает исходный UPDATE/INSERT plan.

Кандидат для будущей проверки:

`_local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql`

Дата и имя dump сами по себе недостаточны. Они могут указывать на состояние до controlled local apply и alias cleanup, но не подтверждают фактическое содержимое `oc_product_attribute`, scope `11900213`, наличие alias rows и состояние canonical rows.

Нужна отдельная DB-backed verification в isolated controlled local environment. Этот документ описывает будущий gate только как spec: на текущем шаге restore, DB connection, SQL checks и dry-run не выполняются.

## 2. Scope

- dump candidate: `_local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql`
- target meaning: `максимальный напор`
- category scope: `11900213`
- canonical attribute: `12`
- alias attributes: `101,119,81`
- contract: `framework-standardization/config/attribute-contracts/max_head_11900213.php`
- normalizer: `simple_meters`
- expected source-based purpose: подтвердить, что generic canonical apply может построить dry-run plan из source alias rows, а не из уже существующих canonical rows.

## 3. Preconditions

Перед будущим выполнением gate должны быть подтверждены:

- working tree clean;
- исходный dump существует;
- dump не изменён;
- известен checksum или другая проверяемая identity dump;
- доступна новая пустая isolated local DB;
- имя isolated DB не совпадает с production DB;
- имя isolated DB не совпадает с live DB;
- имя isolated DB не совпадает с `he_framework_local_dump`;
- учётные данные разрешают работу только с isolated local DB;
- runtime config для isolated DB создаётся только отдельным implementation step;
- текущий runtime config не меняется;
- production/cache недоступны из isolated runtime.

## 4. Модель изоляции

Будущая проверка должна использовать отдельную новую local DB с уникальным именем. Эта DB должна иметь disposable lifecycle и не должна заменять текущую `he_framework_local_dump`.

Требования изоляции:

- отдельная новая local DB;
- отдельное уникальное DB name;
- отсутствие overwrite существующей DB;
- отсутствие `DROP` текущей local DB;
- отсутствие изменения production/live DB;
- runtime allowlist для isolated runtime;
- явный `runtime_mode`;
- явный запрет `production_ready`;
- явный запрет cache rebuild;
- отдельный disposable lifecycle isolated DB.

Реальные пароли, секреты и live credentials в spec не указываются.

## 5. Будущий restore gate

Restore является отдельным explicit gate и не выполняется в рамках этого документа.

Будущий restore может выполняться только после отдельного `+` и должен быть направлен только в isolated DB. Он не должен изменять dump, заменять текущую local DB или запускать framework commands автоматически.

Restore не является SQL/apply стандартизации. Это только восстановление controlled test state для последующей readonly verification.

Этот документ не содержит готовых destructive restore-команд.

## 6. Post-restore readonly verification

После будущего restore должны выполняться только read-only проверки. Они должны подтвердить следующие факты без изменения данных:

- таблицы OpenCart существуют;
- category scope `11900213` существует;
- продукты scope доступны;
- присутствуют строки `oc_product_attribute` для canonical `12`;
- присутствуют строки `oc_product_attribute` для aliases `101,119,81`;
- source alias rows ещё не удалены;
- значения aliases содержат ожидаемые raw source values;
- canonical rows не находятся полностью в already-applied состоянии;
- unresolved значения остаются различимыми;
- данные позволяют построить source-based plan;
- проверка не меняет данные.

Реальные SQL-команды в этом spec не фиксируются. Будущий gate должен описать конкретные readonly checks отдельно до выполнения.

## 7. Классификация состояния dump

### `pre_canonical_apply`

- source alias rows доступны;
- canonical values ещё требуют UPDATE/INSERT;
- потенциально подходит для полного source-based generic dry-run.

### `after_canonical_apply_before_alias_cleanup`

- canonical rows уже применены;
- source alias rows ещё присутствуют;
- подходит только для ограниченной already-applied/source-presence проверки;
- не доказывает исходный UPDATE/INSERT plan полностью.

### `after_alias_cleanup`

- source alias rows удалены;
- не подходит для source-based generic dry-run proof.

### `unknown_or_inconsistent`

- данные не позволяют достоверно определить состояние;
- дальнейшие действия блокируются.

## 8. Generic dry-run verification gate

После подтверждения подходящего isolated DB state будущий отдельный gate может разрешить:

- создать отдельный isolated runtime config;
- запустить generic canonical apply только без `--confirm-apply`;
- получить source-based diagnostic plan;
- проверить expected counts;
- проверить unresolved exclusions;
- проверить scope boundaries.

На этом этапе должны оставаться:

- `sql_applied: 0`;
- `product_data_changed: 0`;
- `transaction_started: 0`;
- production/cache untouched.

`--confirm-apply` должен оставаться запрещён.

## 9. Expected acceptance criteria

Dump может быть признан пригодным для source-based generic dry-run только если:

- isolated DB подтверждена;
- current local DB не затронута;
- source alias rows присутствуют;
- source-based plan доступен;
- scope ограничен `11900213`;
- canonical/alias IDs соответствуют contract;
- unresolved значения исключаются;
- expected counts объяснимы;
- SQL/apply не выполнялся;
- production/cache не затронуты.

## 10. Stop conditions

Будущую проверку нужно немедленно остановить, если:

- DB name совпадает с текущей local DB или production DB;
- runtime не идентифицирован как isolated controlled local;
- dump identity не подтверждена;
- restore требует overwrite существующей DB;
- source aliases отсутствуют;
- состояние уже `after_alias_cleanup`;
- contract не соответствует target;
- появляются признаки production/cache access;
- dry-run пытается войти в write path;
- требуется `--confirm-apply`;
- expected counts нельзя объяснить;
- обнаружена неоднозначность состояния.

## 11. Запрещённые действия

В рамках этого spec и будущего verification gate без дополнительного решения запрещены:

- изменение текущей local DB;
- production/live DB;
- изменение dump;
- overwrite существующей DB;
- `--confirm-apply`;
- generic UPDATE/INSERT;
- alias cleanup;
- SQL/apply стандартизации;
- production/cache changes;
- cache rebuild;
- OpenCart runtime integration;
- pipeline wiring;
- изменение default dry-run path.

## 12. Required future evidence

Будущий runtime-check должен зафиксировать:

- dump identity;
- isolated DB identity;
- classification state;
- counts canonical/alias rows;
- source-based plan availability;
- expected-count result;
- unresolved exclusions;
- отсутствие write operations;
- отсутствие изменений current local DB;
- отсутствие production/cache действий;
- полный список выполненных команд;
- итоговый `git status --short`.

## 13. Out of scope

Этот spec не разрешает:

- restore;
- runtime config implementation;
- DB connection;
- SQL checks;
- PHP/code implementation;
- dry-run execution;
- `--confirm-apply`;
- SQL/apply;
- production readiness.

## 14. Следующий gate

Следующий bounded gate:

`isolated dump restore plan and command review`

Это должен быть отдельный documentation/planning step до любого восстановления.
