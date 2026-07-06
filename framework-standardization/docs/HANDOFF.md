# Handoff — Framework Standardization

Дата: 06.07.2026, конец вечерней сессии

Проект: HmEnerg_Характеристики / Home Energetika / Framework Standardization  
Репозиторий: `saitkharkov-cyber/home-energetika`  
Рабочая папка: `framework-standardization`  
Локальный путь пользователя: `D:\Git\home-energetika`

## 1. Обязательные правила работы

Перед продолжением ChatGPT должен прочитать для себя:

* `framework-standardization/docs/RULES.md`
* `framework-standardization/docs/HANDOFF.md`
* `framework-standardization/docs/DECISIONS.md`
* `framework-standardization/docs/RUNTIME_CHECKS.md`

`RULES.md` — регламент исключительно для ChatGPT-ассистента. Он описывает, как ChatGPT должен вести пользователя, выбирать следующий маленький шаг, формулировать задания Codex и оценивать отчёты Codex.

`RULES.md` не является инструкцией для Codex напрямую. Не давать `RULES.md` Codex как обязательный файл для чтения. Если ограничение важно для Codex, включать его явно в конкретное задание.

Роли:

* User принимает решения, запускает команды, приносит отчёты Codex / `git status` / `git log`, подтверждает реализацию знаком `+`.
* ChatGPT выбирает следующий маленький шаг, формулирует задание Codex, оценивает отчёт Codex, даёт commit-команду.
* Codex выполняет только конкретное задание, не выбирает roadmap и не делает commit без явного разрешения.

ChatGPT запрещено спрашивать Codex:

* “что дальше?”
* “какой следующий шаг?”
* “что предлагаешь делать дальше?”

Базовый цикл:

`spec -> decision -> implementation -> runtime checks`

`HANDOFF.md` не обновлять после каждого маленького шага. Обновлять только при закрытии сессии, переносе в новый чат, существенном устаревании или когда handoff нужен как новая точка входа.

Команды давать под PowerShell 7, желательно одной строкой.

PHP checks выполнять только через `C:\php56\php.exe`, не через глобальный `php`.

Если Codex дал отчёт с нужными checks и `git status` показывает только ожидаемые файлы — не делать дополнительных review / diff / security проверок, а давать commit-команду.

## 2. Текущая стабильная точка

Текущая стабильная точка:

`1914182 Document DB readonly review chain result reporter checks`

Актуальный git log:

* `1914182 Document DB readonly review chain result reporter checks`
* `d5b66da Add DB readonly review chain result reporter`
* `7a86a1c Add framework standardization working rules`
* `5a01881 Document review chain result reporter standalone decision`
* `34f9f77 Add DB readonly review chain result reporter spec`

Ожидаемое состояние репозитория:

* `HEAD/main/origin/main = 1914182 Document DB readonly review chain result reporter checks`
* working tree clean

В новом чате сначала проверить:

`git status --short`

`git log --oneline --decorate -5`

Если HEAD отличается от `1914182`, сначала разобраться с фактической рабочей точкой и не начинать новый engineering step.

## 3. Текущий закрытый инженерный блок

Последний закрытый инженерный блок:

`standalone local review chain components до result reporter включительно`

Закрыто:

* standalone local review fixture writer implemented;
* writer runtime checks documented;
* writer standalone-only decision documented;
* standalone local review fixture loader spec created;
* loader standalone-only decision documented;
* standalone local review fixture loader implemented;
* loader runtime checks documented;
* review chain result reporter spec created;
* review chain result reporter standalone reporting-only decision documented;
* standalone review chain result reporter implemented;
* reporter runtime checks documented;
* working rules documented.

## 4. Актуальная standalone review chain

Актуальная standalone review chain:

`parser output -> DbReadOnlyLocalReviewFixtureGenerator -> JSON-ready review fixture array -> DbReadOnlyLocalReviewFixtureWriter -> local ignored review JSON file -> manual review / edited review blocks -> DbReadOnlyLocalReviewFixtureLoader -> PHP array fixture -> DbReadOnlyLocalApprovalFixtureBridge -> DbReadOnlyNormalizationApprovalFlow -> DbReadOnlyReviewChainResultReporter`

Вся chain остаётся standalone-only:

* не pipeline stage;
* не runner integration;
* не SQL preview input by default;
* не production storage;
* не SQL/apply layer.

## 5. Component status

### Parser

`src/Normalizer/DbReadOnlyNormalizationProposalParser.php`

Status:

* standalone parser skeleton;
* не подключён к pipeline;
* не подключён к `analyze_values`;
* не создаёт `approved/rejected`;
* не создаёт SQL/apply output.

### Local review fixture generator

`src/Approval/DbReadOnlyLocalReviewFixtureGenerator.php`

Status:

* implemented;
* принимает standalone parser output;
* возвращает JSON-ready review fixture array;
* создаёт empty reviewer-owned `review` block;
* не пишет fixture JSON files;
* не вызывает bridge или approval flow;
* не подключён к pipeline.

### Local review fixture writer

`src/Approval/DbReadOnlyLocalReviewFixtureWriter.php`

Status:

* implemented;
* checks documented;
* standalone-only decision documented.

Boundary:

* пишет только local ignored JSON review artifacts в `framework-standardization/var/review-fixtures/*.json`;
* не подключён к pipeline/runners;
* не вызывает bridge/approval flow/SQL preview;
* не использует DB/live DB;
* не создаёт SQL/apply artifacts;
* не меняет `approval_status`;
* не создаёт `approved/rejected`.

### Local review fixture loader

`src/Approval/DbReadOnlyLocalReviewFixtureLoader.php`

Status:

* implemented;
* checks documented;
* standalone-only decision documented.

Boundary:

* читает только local `.json` fixture files из `framework-standardization/var/review-fixtures/`;
* принимает только local filename, не arbitrary path;
* запрещает absolute paths, path traversal, path separators, unsafe filename tokens;
* декодирует JSON в PHP array;
* не вызывает bridge/approval flow;
* не меняет `review.action`;
* не меняет `approval_status`;
* не создаёт `approved/rejected`;
* не создаёт SQL/apply artifacts;
* не использует DB/live DB.

### Local approval fixture bridge

`src/Approval/DbReadOnlyLocalApprovalFixtureBridge.php`

Status:

* implemented;
* standalone bridge;
* принимает JSON-shaped fixture как PHP array;
* отделяет parser-owned proposal rows от reviewer-owned `review.action`;
* передаёт review actions в standalone approval flow;
* не выставляет statuses напрямую;
* не подключён к pipeline.

### Normalization approval flow

`src/Approval/DbReadOnlyNormalizationApprovalFlow.php`

Status:

* implemented as standalone approval flow skeleton;
* единственный текущий standalone component, который может создавать `approved/rejected`;
* не подключён к pipeline;
* не подключён к parser автоматически;
* не создаёт SQL/apply output.

### Review chain result reporter

`src/Approval/DbReadOnlyReviewChainResultReporter.php`

Status:

* implemented;
* checks documented;
* standalone reporting-only decision documented.

Boundary:

* standalone reporting/diagnostics only;
* расположен после approval flow;
* принимает result standalone approval flow;
* считает summary counts по statuses;
* показывает counts по `approved`, `rejected`, `needs_review`, `unknown`, `proposed`;
* показывает unsupported statuses как diagnostics;
* явно фиксирует, что SQL/apply still blocked;
* не меняет statuses;
* не создаёт `approved/rejected`;
* не принимает review decisions;
* не вызывает bridge;
* не вызывает approval flow;
* не вызывает SQL preview;
* не генерирует SQL;
* не создаёт SQL files/diff/apply plan;
* не выполняет SQL apply;
* не использует DB/live DB;
* не меняет DB/schema;
* не подключается к pipeline/runners;
* не меняет default dry-run path.

## 6. Runtime checks status

Runtime checks зафиксированы в:

`docs/RUNTIME_CHECKS.md`

Ключевые закрытые checks:

* writer implementation check;
* loader implementation check;
* reporter implementation check;
* default dry-run regression после каждого implementation step;
* DB-readonly runner regression после каждого implementation step.

Reporter checks commit:

`1914182 Document DB readonly review chain result reporter checks`

Reporter implementation commit:

`d5b66da Add DB readonly review chain result reporter`

Reporter checks по отчёту:

Syntax:

`C:\php56\php.exe -l framework-standardization\src\Approval\DbReadOnlyReviewChainResultReporter.php`

Result:

`No syntax errors detected`

Standalone manual check:

* `proposed_count = 1`
* `unsupported_statuses_count = 1`
* `unsupported_status_seen = 1`
* `sql_generated = 0`
* `apply_plan_created = 0`
* `safe_to_apply = 0`
* `sql_apply_allowed = 0`
* `production_ready = 0`
* `input_unchanged = 1`
* `errors_count = 0`
* `warnings_count = 1`

Default dry-run:

* `result_status: ok`
* `warnings_count: 0`
* `errors_count: 0`
* all 9 stages ok

DB-readonly runner:

* `result_status: ok`
* `warnings_count: 0`
* `errors_count: 0`
* all 9 stages ok

## 7. Главные запреты

Запрещено:

* pipeline wiring без отдельного explicit step;
* подключать writer/loader/generator/bridge/approval flow/reporter к pipeline/runners;
* коммитить fixture JSON files;
* использовать fixture JSON как production storage;
* использовать fixture JSON как DB storage;
* использовать review-chain output как SQL preview input by default;
* считать `approved` разрешением на SQL apply;
* делать `safe_to_apply = 1`;
* делать `production_ready = 1`;
* создавать apply-ready output;
* генерировать SQL;
* создавать SQL files;
* создавать SQL diff;
* создавать apply plan;
* выполнять SQL apply;
* использовать live DB;
* делать DB/schema changes;
* делать write/schema operations;
* менять default dry-run path;
* создавать OpenCart module runtime paths.

Запрещённые operation families:

* `INSERT`
* `UPDATE`
* `DELETE`
* `REPLACE`
* `ALTER`
* `DROP`
* `TRUNCATE`
* `CREATE`

## 8. Следующий инженерный шаг на завтра

Следующий маленький шаг по процессу:

`spec`

Предлагаемый следующий engineering step:

создать mini-spec для standalone review chain end-to-end check boundary.

Рекомендуемый файл:

`docs/DB_READONLY_STANDALONE_REVIEW_CHAIN_E2E_CHECK_SPEC.md`

Почему именно этот шаг:

* отдельные standalone components уже реализованы и проверены по отдельности;
* актуальная chain уже состоит из parser, generator, writer, loader, bridge, approval flow и reporter;
* перед любым дальнейшим development нужно формализовать безопасную границу E2E-проверки всей standalone chain;
* это не pipeline wiring, не runner integration и не SQL/apply;
* spec позволит определить, как безопасно проверить chain целиком на локальных/in-memory fixtures без production side effects.

Что должен описать spec:

* purpose standalone E2E check;
* какие компоненты входят в chain;
* allowed test input shape;
* allowed local fixture artifact usage;
* expected output;
* expected diagnostics;
* cleanup of generated local JSON fixtures;
* git safety;
* default dry-run regression boundary;
* DB-readonly runner regression boundary;
* запрет SQL/apply;
* запрет pipeline/runners integration;
* запрет live DB и DB/schema changes.

Future option, если позже понадобится отдельный checker class:

`src/Approval/DbReadOnlyStandaloneReviewChainE2EChecker.php`

В следующем шаге не реализовывать класс. Только spec.

## 9. Первый prompt для Codex завтра

После проверки `git status --short` и `git log --oneline --decorate -5`, если working tree clean и HEAD = `1914182`, можно дать Codex задачу:

создать только новый spec:

`docs/DB_READONLY_STANDALONE_REVIEW_CHAIN_E2E_CHECK_SPEC.md`

Codex должен читать только те документы, которые явно указаны в конкретном задании. `RULES.md` в задание Codex не включать.

Для Codex явно указать границы:

Не менять:

* PHP-код;
* `docs/HANDOFF.md`;
* `docs/DECISIONS.md`;
* `docs/RUNTIME_CHECKS.md`;
* существующие specs;
* `docs/RULES.md`;
* `.gitignore`;
* pipeline;
* runners;
* jobs;
* config.

Codex не должен:

* реализовывать checker;
* запускать новые проверки;
* делать commit;
* спрашивать, что дальше.

После отчёта Codex, если `git status` показывает только новый spec-файл, дать commit:

`git add framework-standardization/docs/DB_READONLY_STANDALONE_REVIEW_CHAIN_E2E_CHECK_SPEC.md && git commit -m "Add DB readonly standalone review chain E2E check spec"`

## 10. Не делать завтра первым шагом

Не делать:

* спрашивать Codex “что дальше”;
* запускать roadmap-анализ Codex;
* делать diff/security/review-аудит без запроса;
* начинать implementation без `+`;
* pipeline wiring;
* SQL preview integration;
* SQL generation/apply;
* live DB;
* DB/schema changes;
* менять `RULES.md`;
* давать Codex `RULES.md` как обязательный входной документ;
* обновлять `HANDOFF.md` сразу после первого маленького шага.

## 11. Формат ответов пользователю

* кратко;
* один следующий шаг;
* Codex prompt — обычный многострочный текст, не `codex "..."`;
* commit-команды — одной строкой PowerShell;
* без лишнего аудита;
* без повторного чтения всех документов без причины;
* если нужен полный `.md` документ — одним цельным markdown-блоком.