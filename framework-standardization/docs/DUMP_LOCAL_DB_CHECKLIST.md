# Dump / Local DB Checklist

Короткий checklist для подготовки локального OpenCart dump или локальной БД для будущих read-only шагов Framework Standardization.

## Цель

- Подготовить локальный OpenCart dump/local DB без персональных и операционных данных.
- Использовать dump только как read-only источник для будущей проверки canonical attributes.
- Сохранить no-DB boundary для всех текущих pipeline stages.

## Жесткие границы

- Live DB запрещена.
- Production DB напрямую не использовать.
- SQL apply не создавать и не выполнять.
- `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `ALTER`, `DROP`, `TRUNCATE`, `CREATE` запрещены в tooling flow.
- DB не подключать к `PipelineFactory` / CLI.
- Код pipeline не менять.
- OpenCart module paths не создавать.
- Runtime config не менять в рамках checklist.

## Исключить из dump

Исключить персональные и операционные таблицы:

```text
customer*
order*
address*
session
user
user_token
api
api_session
cart
coupon
voucher
affiliate
return*
logs
analytics
```

## Допустимый состав dump

Оставить только минимально нужные OpenCart catalog/reference таблицы для read-only анализа:

- товары и описания товаров;
- категории и связи товар-категория;
- атрибуты, группы атрибутов, описания атрибутов;
- product attributes;
- производители;
- языки;
- другие lookup/config таблицы только если они нужны для read-only canonical lookup.

## Проверки после импорта

- Dump импортирован только в локальную БД.
- В локальной БД нет исключенных персональных/операционных таблиц.
- Подтвержден `db_prefix`.
- Подтвержден рабочий `language_id`.
- Нужные catalog/reference таблицы доступны.
- Если возможно, пользователь локальной БД имеет только read-only права.

## Local runtime config

- Локальный config создавать только после готового dump/local DB.
- Использовать шаблон:

```text
framework-standardization/config/runtime/local.dump.example.php
```

- Локальный файл:

```text
framework-standardization/config/runtime/local.dump.php
```

- `local.dump.php` ignored через `.gitignore` и не коммитится.
- Перед коммитом проверять `git status`, чтобы runtime config или dump-файлы не попали в commit.

## Разрешенный следующий кодовый шаг

Только после готового dump/config и отдельного утверждения:

- добавить read-only repository/resolver для canonical lookup;
- будущий resolver: `DbReadOnlyCanonicalAttributeResolver`;
- первым DB-backed stage может быть только `ResolveCanonicalStage`.

До отдельного утверждения:

- не подключать `DbReadOnlyCanonicalAttributeResolver` к `PipelineFactory`;
- не подключать DB к CLI;
- не менять no-DB поведение существующего dry-run.

## Stop conditions

Остановиться и не продолжать к коду, если:

- dump содержит персональные или операционные таблицы;
- используется live DB;
- `local.dump.php` не ignored;
- нет подтвержденного `db_prefix` или `language_id`;
- требуется SQL apply или write operation;
- следующий шаг требует изменения pipeline/CLI без отдельного утверждения.
