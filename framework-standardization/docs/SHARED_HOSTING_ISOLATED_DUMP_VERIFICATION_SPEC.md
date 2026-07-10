# Shared hosting isolated dump verification spec

## 1. Назначение

`framework-standardization` должен оставаться совместимым с shared hosting. Системный MySQL CLI не является обязательной runtime-зависимостью framework.

Windows services, локальная установка MySQL server, OSPanel-specific tooling и локальные server tools не входят в архитектуру framework. Предыдущий stop на restore gate был ограничением конкретного локального Windows preflight, где не был найден `mysql.exe` или `mariadb.exe`, а не архитектурным требованием framework.

Нужен portable hosting-compatible путь для будущей проверки dump-кандидата:

`_local/dumps/insurgent_ocar22_after_SUMOTO.sql/insurgent_ocar22.sql`

Цель будущей проверки — isolated DB state для target:

- meaning: `максимальный напор`;
- category scope: `11900213`;
- canonical attribute: `12`;
- alias attributes: `101,119,81`;
- normalizer: `simple_meters`.

Этот spec не разрешает restore, import, DB connection, PHP implementation или generic dry-run.

## 2. Hosting constraints

Типичные ограничения shared hosting, которые нужно учитывать:

- нет root-доступа;
- нет Windows services;
- может отсутствовать SSH;
- может отсутствовать mysql CLI;
- доступна PHP-среда;
- доступен PDO MySQL или `mysqli`;
- DB создаётся через hosting control panel или phpMyAdmin;
- credentials выдаются хостингом;
- нельзя управлять server settings;
- нельзя создавать DB произвольным SQL, если это запрещено хостингом;
- upload/import может иметь ограничения по размеру и timeout;
- production DB должна быть отделена от isolated DB.

Этот документ не утверждает конкретные возможности текущего хостинга. Их нужно проверить отдельным gate.

## 3. Architectural principle

- Framework runtime использует PHP/PDO.
- Наличие `mysql.exe` не требуется.
- Restore/import является внешним инфраструктурным gate.
- Framework не должен сам создавать production или isolated DB.
- Framework не должен содержать универсальный dump importer как часть standardization workflow.
- Dump verification начинается только после того, как isolated DB уже подготовлена.
- DB provisioning и DB verification являются разными шагами.

## 4. Допустимые hosting-compatible paths

### Path A: hosting control panel / phpMyAdmin

Порядок:

- создать отдельную isolated DB через hosting control panel;
- создать отдельного DB user либо использовать разрешённого isolated user;
- импортировать dump через phpMyAdmin или hosting import tool;
- после импорта подключить только readonly verification tool.

Плюсы:

- не зависит от локального Windows MySQL/MariaDB client;
- использует штатный hosting workflow;
- не требует custom PHP importer;
- проще отделить provisioning/import от framework verification.

Ограничения:

- upload/import limits;
- web timeout;
- возможные ограничения на размер dump;
- разные панели хостинга показывают target DB по-разному;
- нужен ручной контроль, что выбрана именно isolated DB.

### Path B: temporary bounded PHP import utility

Только как будущий отдельный implementation/security gate.

Возможная форма:

- PHP CLI или web-triggered utility;
- работает только с explicit isolated DB config;
- читает конкретный dump;
- запрещает production DB;
- имеет runtime allowlist;
- не является частью default framework path;
- удаляется или отключается после использования;
- не хранит credentials в tracked файлах;
- не запускает generic apply;
- не выполняет cache operations.

Риски:

- timeout;
- memory;
- upload limits;
- partial import;
- multi-statement parsing;
- delimiter/procedure handling;
- повторный запуск;
- CSRF/authentication для web mode;
- accidental production target.

PHP utility в рамках этого spec не реализуется.

### Path C: provider-assisted restore

Порядок:

- isolated DB создаётся хостингом;
- dump импортирует support или hosting backup tool;
- framework получает только readonly credentials.

Плюсы:

- подходит, если import limit превышает возможности phpMyAdmin/PHP;
- снижает риск partial import из-за web timeout;
- использует штатные инструменты провайдера.

Ограничения:

- требуется точное подтверждение target DB;
- требуется отчёт или evidence от провайдера;
- нет прямого контроля над процессом import;
- нужно отдельно подтвердить, что production DB не затронута.

Evidence requirements:

- actual isolated DB name;
- import tool/method;
- import status;
- timestamp результата;
- подтверждение, что production DB не была target;
- подтверждение, что dump identity соответствует утверждённому файлу.

## 5. Выбор рекомендуемого пути

Предпочтительный путь для текущего проекта: Path A, hosting control panel / phpMyAdmin, если размер dump и лимиты хостинга позволяют.

Причина: это самый переносимый путь для shared hosting, не требующий локального CLI, Windows services, локального MySQL server, OSPanel tooling или shell-доступа.

Fallback: Path C, provider-assisted restore, если phpMyAdmin/import limits недостаточны.

Path B, temporary bounded PHP import utility, допустим только последним вариантом и только через отдельный implementation/security gate.

## 6. Isolation requirements

Независимо от выбранного path обязательны:

- отдельная DB;
- имя DB не совпадает с production/current DB;
- отдельные credentials или явно ограниченный user;
- отсутствие production tables в target;
- отсутствие overwrite;
- отсутствие reuse текущей DB;
- отсутствие cache integration;
- отсутствие OpenCart runtime connection;
- отсутствие auto-discovery production credentials;
- explicit allowlist DB name;
- manual confirmation target identity.

## 7. Proposed isolated DB identity

Logical name:

`he_framework_isolated_max_head_precleanup`

Shared hosting может добавить account prefix, например:

`account_he_framework_isolated_max_head_precleanup`

Validation должна учитывать:

- фактическое имя из control panel;
- explicit exact match в isolated runtime config;
- запрет fuzzy matching;
- запрет угадывания prefix;
- запрет использования production DB name.

Runtime config в этом step не создаётся.

## 8. Dump identity

Перед импортом должны фиксироваться:

- exact file name;
- size;
- modified time;
- SHA-256;
- source path;
- expected DB/schema identity из dump header, если доступно;
- факт отсутствия изменений dump.

Текущий observed SHA-256:

`FD9AEA1A6263BA28E842C3A980B37FD114C21F178AF96A500A2495D84DA9CB52`

Это observed value из текущего local preflight. Его нужно повторно проверить перед будущим import gate. Он не является вечным contract без повторной проверки.

## 9. Import gate

Будущий import gate должен отдельно подтвердить:

- isolated DB создана;
- target DB видна пользователю явно;
- production DB не выбрана;
- dump identity подтверждена;
- выбран один approved import path;
- лимиты upload/import достаточны;
- есть стратегия для partial failure;
- повторный импорт без очистки запрещён;
- автоматический `DROP`/overwrite запрещён;
- import не запускает framework commands;
- пользователь дал отдельный explicit `+`.

## 10. Post-import verification architecture

После импорта framework должен использовать отдельный PHP/PDO readonly verification entrypoint.

Будущий verification entrypoint должен:

- принимать explicit runtime config;
- требовать `runtime_mode = isolated_db_readonly`;
- проверять allowlisted DB name;
- hard-fail для production/live DB;
- использовать только SELECT, SHOW, DESCRIBE;
- не содержать write path;
- не создавать временные таблицы;
- не запускать generic apply;
- не запускать cache;
- не подключаться к OpenCart runtime;
- возвращать structured console/report output.

Entrypoint в этом spec не реализуется.

## 11. Verification facts

Future readonly verification должна проверить:

- выбранную DB identity;
- наличие OpenCart tables;
- наличие `oc_product_attribute`;
- наличие `oc_product_to_category`;
- category scope `11900213`;
- products in scope;
- counts для attributes `12,101,119,81`;
- distinct products;
- raw samples aliases;
- canonical/source relationship;
- unresolved examples;
- source aliases presence;
- признаки already-applied состояния.

## 12. Classification model

### `pre_canonical_apply`

Source aliases присутствуют, есть будущие canonical UPDATE/INSERT candidates.

### `after_canonical_apply_before_alias_cleanup`

Source aliases присутствуют, canonical rows уже применены.

### `after_alias_cleanup`

Source rows удалены, source-based proof невозможен.

### `unknown_or_inconsistent`

Facts недостаточны или противоречат друг другу.

Классификация выполняется только по DB facts, не по имени dump.

## 13. Evidence requirements

Будущий runtime report должен содержать:

- actual hosting environment type;
- chosen import path;
- actual isolated DB name;
- dump SHA-256;
- import result;
- import tool;
- PHP version;
- PDO MySQL availability;
- DB host без секрета;
- readonly verification counts;
- classification;
- отсутствие write operations verification tool;
- отсутствие production/cache действий;
- `git status --short`.

## 14. Security boundaries

Запрещено:

- credentials в repo;
- credentials в отчёте;
- auto-detection production config;
- reuse OpenCart production config;
- web utility без authentication;
- web utility без CSRF/nonce;
- arbitrary dump path;
- arbitrary DB name;
- arbitrary SQL;
- production DB;
- live DB;
- cache rebuild;
- SQL/apply стандартизации;
- generic confirm apply;
- framework write path;
- permanent importer in public web root.

## 15. Stop conditions

Остановить будущий gate, если:

- isolated DB не подтверждена;
- actual DB name неизвестно;
- hosting prefix угадан, а не подтверждён;
- production credentials используются;
- dump checksum отличается;
- import tool скрывает target DB;
- partial import не может быть обнаружен;
- повторный импорт требует очистки существующей DB;
- PDO MySQL недоступен;
- runtime config не может ограничить exact DB name;
- verification требует write operations;
- хостинг не позволяет безопасно изолировать DB;
- classification остаётся `unknown_or_inconsistent`.

## 16. Out of scope

Текущий spec не разрешает:

- создание DB;
- import/restore;
- phpMyAdmin operations;
- hosting panel operations;
- provider request;
- PHP importer implementation;
- readonly verifier implementation;
- runtime config;
- DB connection;
- SQL execution;
- generic dry-run;
- confirm apply flag;
- SQL/apply;
- production/cache;
- cache rebuild.

## 17. Следующий bounded gate

`shared hosting isolated DB provisioning and import path selection`

Это только decision/preflight step:

- проверить реальные возможности hosting control panel;
- выбрать Path A, B или C;
- без создания DB;
- без import;
- без PHP implementation.
