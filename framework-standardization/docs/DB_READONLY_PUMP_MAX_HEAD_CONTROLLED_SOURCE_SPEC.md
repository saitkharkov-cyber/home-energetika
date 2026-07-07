# DB-readonly pump max head controlled source spec

Дата: 2026-07-07

Статус: spec / decision analysis only.

Этот документ фиксирует первую новую характеристику после verified `pump_diameter` stability gate.

Implementation в этом шаге не выполняется.

## 1. Purpose

Цель spec:

* зафиксировать controlled source для новой характеристики после `pump_diameter`;
* описать canonical normalization contract для характеристики `Максимальный напор`;
* подготовить безопасную основу для будущего controlled readonly fixture/source;
* сохранить standalone / DB-readonly boundary;
* не выполнять PHP implementation;
* не менять config/jobs;
* не запускать runtime checks;
* не создавать runtime artifacts.

Эта boundary нужна только для будущего small controlled readonly source.

Она не является:

* pipeline wiring;
* runner integration;
* SQL preview integration;
* SQL/apply architecture;
* production output;
* cache rebuild step.

## 2. Source candidate

Readonly discovery по категории `Скважинные насосы` уже выполнен.

Discovery context:

* `category_id = 11900213`;
* `language_id = 1`;
* `direct_products = 1972`;
* использовалась direct category logic, как у существующего `DbReadOnlyScopeResolver`.

Выбранный candidate:

* `attribute_id = 12`;
* `attribute_name = Максимальный напор`;
* `usage_count = 385`.

Raw samples из discovery:

* `46.5м.`;
* `68м.`;
* `93м.`;
* `133м.`;
* `77м.`;
* `107м.`;
* `148м.`;
* `206м.`.

## 3. Canonical contract

Canonical attribute key:

```text
pump_max_head
```

Canonical meaning:

```text
maximum pump head
```

Canonical unit:

```text
m
```

Canonical normalized value:

```text
decimal number in meters
```

Expected examples:

| Raw value | Expected normalized_value | Expected unit |
| --- | ---: | --- |
| `46.5м.` | `46.5` | `m` |
| `68м.` | `68` | `m` |
| `93м.` | `93` | `m` |
| `133м.` | `133` | `m` |
| `20.5м` | `20.5` | `m` |

Anti-examples / запрещённые ошибки:

* не передавать в подборщик raw строку `68м.`;
* не терять единицу измерения на уровне contract;
* не конвертировать метры в миллиметры;
* не конвертировать метры в сантиметры;
* не трактовать `68м` как `68 мм`;
* не сохранять `normalized_value` как строку с suffix;
* не смешивать `Максимальный напор` с `Минимальный напор`;
* не смешивать max head с flow / `max_flow_l_min`.

## 4. Candidate rationale

`Максимальный напор` выбран как следующий controlled source после `pump_diameter`, потому что:

* имеет высокий coverage: `usage_count = 385`;
* значения имеют понятный формат: число + метры;
* semantic принадлежит тому же pump domain;
* contract проще, чем у производительности с dual units;
* source безопаснее, чем подключение/дюймы с HTML entities и дробями;
* подходит для проверки масштабирования standalone review-chain после `pump_diameter`.

Flow/performance attributes postponed.

Причина:

* production уже имел временный cache hotfix для Belamos/Pedrollo `max_flow_l_min`;
* production rebuild восстановил старые flow values в шкале `m/h`;
* после hotfix смешанная выдача брендов Belamos, Pedrollo, SUMOTO восстановлена;
* cache rebuild нельзя запускать снова, пока permanent flow normalization не исправлена;
* flow / производительность сейчас не должна быть первой новой характеристикой после `pump_diameter`.

Вывод:

* `pump_max_head` выбран как более безопасный controlled source;
* `pump_max_head` не должен смешиваться с `max_flow_l_min`;
* flow/performance normalization должна оставаться отдельным future decision.

## 5. Future controlled fixture expectations

Future source, если будет реализован отдельным explicit step, должен быть small controlled readonly fixture.

Ожидаемая форма:

* context / canonical key: `pump_max_head`;
* attribute id: `12`;
* attribute name: `Максимальный напор`;
* first slice: `1-2 rows`;
* controlled full source: около `8 rows`;
* maximum rows: `<= 12`;
* rows должны содержать raw value и expected normalized decimal meters;
* fixture/source должен быть local readonly dump-derived/test-like;
* fixture/source не должен читать external files;
* fixture/source не должен принимать CLI input;
* fixture/source не должен подключаться к DB;
* fixture/source не должен создавать runtime artifacts.

Suggested row-level fields for future fixture:

* `product_id`;
* `attribute_id`;
* `attribute_name`;
* `raw_value`;
* `normalized_value`;
* `unit`;
* `confidence`.

Expected non-apply markers:

* `sql_generated = 0`;
* `apply_plan_created = 0`;
* `safe_to_apply = 0`;
* `sql_apply_allowed = 0`;
* `production_ready = 0`.

## 6. Boundaries

This spec is standalone / DB-readonly only.

В этом шаге запрещено:

* PHP implementation;
* config/jobs changes;
* pipeline wiring;
* runner integration;
* SQL preview integration;
* SQL generation;
* SQL files;
* SQL diff;
* apply plan;
* SQL apply;
* live DB / production DB;
* DB/schema changes;
* write/schema operations;
* production output;
* cache rebuild;
* committed runtime artifacts;
* default dry-run path changes.

Future implementation, если будет отдельно разрешена, не должна:

* менять PHP-код за пределами явно выбранного fixture/source;
* подключаться к pipeline;
* подключаться к runners;
* принимать arbitrary input;
* принимать filenames/paths/URLs;
* использовать live DB;
* использовать production DB;
* создавать SQL/apply artifacts;
* создавать production output;
* менять default dry-run path.

Запрещённые operation families:

* `INSERT`;
* `UPDATE`;
* `DELETE`;
* `REPLACE`;
* `ALTER`;
* `DROP`;
* `TRUNCATE`;
* `CREATE`.

`approved` остаётся только review-chain status.

`approved` не означает:

* SQL apply allowed;
* `safe_to_apply = 1`;
* `production_ready = 1`;
* apply-ready output.
