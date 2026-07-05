# Handoff - Framework Standardization
04.07.2026 23:10

Before DB-readonly work: close SUMOTO deploy using pump-selector/docs/SUMOTO_DEPLOY_VERIFICATION_REPORT.md.

Проект: Home Energetika / Framework Standardization  
Репозиторий: `D:\Git\home-energetika`  
Рабочая папка: `framework-standardization`

## Текущий статус

Framework Standardization - отдельный PHP 5.6-compatible CLI/tooling layer для инженерной работы со стандартизацией характеристик.

Текущая стабильная точка: все 9 stages имеют no-DB boundary, dry-run зелёный на PHP 5.6, DB/OpenCart/SQL apply не подключались.

Последний закрытый шаг: `read-only DB runtime skeleton`.

Последний коммит: `b037fa7 Add read-only DB runtime skeleton`.

Ожидаемое состояние репозитория: `working tree clean`, `origin/main = main`.

Предыдущий документационный split закрыт коммитом `7c590c4 Split handoff and stage boundaries documentation`.

## Документация

Ключевые документы:

- `docs/STAGES_PIPELINE.md`
- `docs/ATTRIBUTE_CONTEXT.md`
- `docs/IMPLEMENTATION_STRUCTURE.md`
- `docs/STAGE_BOUNDARIES.md`

Подробные stage boundaries вынесены в `docs/STAGE_BOUNDARIES.md`.

## Stage-модель

Порядок Pipeline и technical names:

1. `validate_job`
2. `resolve_canonical`
3. `resolve_scope`
4. `export_attributes`
5. `analyze_names`
6. `analyze_values`
7. `build_sql_preview`
8. `build_report`
9. `build_framework_result`

Все 9 stages сейчас имеют no-DB boundary.

## Главные архитектурные решения

- Один запуск = один `Attribute Job`.
- Один `Attribute Job` = одна характеристика / один canonical attribute / один scope.
- Поток: `Attribute Job -> AttributeContext -> Pipeline -> FrameworkResult`.
- SQL не применяется автоматически.
- OpenCart сейчас не подключён.
- `dry_run_fixture` mode сохранён.
- Pipeline / CLI / stages всё ещё не подключены к DB.

## Runtime-ограничения

- Runtime первого MVP: `PHP 5.6-compatible CLI/tooling layer`.
- Проверки выполнять через `C:\php56\php.exe`.
- Не полагаться на глобальный `php` из `PATH`.
- Framework Standardization - не OpenCart-модуль и не модуль админки OpenCart.
- Не создавать OpenCart module paths: `admin/controller`, `admin/model`, `admin/view`, `catalog/controller`, `catalog/model`, `language`.

## CLI entrypoint для dry-run

Файлы: `bootstrap.php`, `bin/dry-run.php`, `config/jobs/pump_diameter.php`.

Happy path command из корня репозитория:

```text
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
```

Ожидаемый результат:

```text
result_status: ok
warnings_count: 0
errors_count: 0
all 9 stages ok
```

## Что не делать

- No DB connection.
- No OpenCart connection.
- No SQL apply.
- No executable SQL.
- No INSERT/UPDATE/DELETE.
- No production exporter/analyzer/parser/generator.
- No OpenCart module paths.
- No Composer/YAML/test framework без отдельного решения.

## DB/runtime skeleton

В `b037fa7` добавлен только skeleton для будущей работы с локальным OpenCart dump:

- `.gitignore` защищает `framework-standardization/config/runtime/*.php` и разрешает `*.example.php`;
- `config/runtime/local.dump.example.php`;
- `src/Contract/ReadOnlyDbConnectionInterface.php`;
- `src/OpenCart/OpenCartRuntimeConfig.php`;
- `src/OpenCart/OpenCartTableName.php`;
- `src/OpenCart/PdoReadOnlyDbConnection.php`.

Skeleton не подключён к Pipeline, не создаёт `PDO` сам и не меняет dry-run.

`PdoReadOnlyDbConnection` принимает `PDO` в constructor, разрешает только `SELECT` / `SHOW`, запрещает `;`, `INTO OUTFILE`, `INTO DUMPFILE`, `INSERT/UPDATE/DELETE/REPLACE/ALTER/DROP/TRUNCATE/CREATE`. `WITH` / CTE и leading comments остаются blocked. Write/transaction API нет.

## Следующий шаг: dump/local DB

Следующий шаг - подготовить локальный OpenCart dump или локальную БД без персональных данных. Live DB не использовать.

Нужен dump только нужных таблиц OpenCart. Исключить персональные и операционные таблицы:

```text
customer*
order*
address*
session
user / user_token
api / api_session
cart
coupon / voucher
affiliate
return*
logs
analytics
```

После развёртывания dump создать локальный ignored config:

```text
framework-standardization/config/runtime/local.dump.php
```

Создавать его на основе:

```text
framework-standardization/config/runtime/local.dump.example.php
```

`local.dump.php` не коммитить.

Только после готового dump/config делать следующий кодовый шаг: read-only repository для canonical lookup / `DbReadOnlyCanonicalAttributeResolver`.

Первым DB-backed stage остаётся `ResolveCanonicalStage`.

До отдельного утверждения не подключать DB к `PipelineFactory` / CLI.

SQL apply, executable SQL, `INSERT/UPDATE/DELETE` и OpenCart module paths запрещены.

## Старт в новом чате

Новый чат должен использовать GitHub Connector или локальный репозиторий `home-energetika`.

Сначала открыть и прочитать:

- `framework-standardization/docs/HANDOFF.md`
- `framework-standardization/docs/STAGE_BOUNDARIES.md`

Затем проверить:

```text
git status
git log --oneline -5
```

Ожидаемая точка:

```text
HEAD/main/origin/main = b037fa7 Add read-only DB runtime skeleton
```

Первый ответ нового чата должен быть только кратким пониманием проекта, текущей задачи и одного следующего шага.

Не реализовывать следующий шаг без отдельного подтверждения.

Если пользователь пишет "дамп - завтра", текущий следующий шаг - подготовка dump/local DB checklist, а не код.

## Правило работы

Двигаться маленькими шагами: read-only mini-spec -> implementation -> verification -> review -> commit -> push.

PHP 5.6 checks выполнять через `C:\php56\php.exe`.
