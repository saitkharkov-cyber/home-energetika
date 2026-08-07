# HANDOFF — catalog-standardization-new
Дата: 2026-08-07

## Контекст

Репозиторий:

`saitkharkov-cyber/home-energetika`

Рабочая область:

`catalog-standardization-new`

Связанный потребитель данных:

`pump-selector`

Работа ведётся по принципу одного ограниченного шага за раз.
Никакой production apply без отдельного явного разрешения пользователя.

---

# Текущая цель

Подготовить стандартизированные selector-critical характеристики для производителей VINKO и Grundfos, проверить соответствие текущему production и только после этого перейти к отдельному PRODUCTIVE apply gate.

Обязательные характеристики для Pump Selector:

- `12` — Максимальный напор (м)
- `13` — Максимальная производительность (л/мин)
- `15` — Напряжение питания (В)
- `125` — Минимальный внутренний диаметр обсадной трубы (мм)

`attr14 / power` НЕ является selector-critical и НЕ должен блокировать readiness.

`attr44 / Диаметр насоса` для совместимости подборщика не используется.

---

# VINKO

Источник NasosyMarket полностью разобран.

Source rows:

- 91

Production target inventory на момент предыдущей проверки:

- manufacturer_id = 59
- category = 11900213
- active target products = 119

Сопоставление:

- unique matched = 78
- ambiguous source = 1
- source-only = 12
- target-only = 39

Решение:

- safe MVP scope = 78 товаров
- ambiguous `1947 / 1955` исключены
- 39 target-only товаров исключены из текущего MVP

Прямой `attr125` из detail pages:

- 3STm → 85 мм
- 4ST / 4STm → 110 мм
- 6ST → 160 мм

Распределение:

- 85 мм = 12
- 110 мм = 61
- 160 мм = 5

Важно:

- НЕ использовать старое 3STm = 90
- attr125 получен DIRECT, не по series heuristic

Generated:

`catalog-standardization-new/generated/vinko/vinko_match_preview.csv`

`catalog-standardization-new/generated/vinko/vinko_attributes_preview.csv`

`catalog-standardization-new/generated/vinko/vinko_attributes_insert.sql`

Последняя validation:

- products = 78
- selector-ready = 78
- preview rows = 390
- selector-critical coverage = 312 / 312
- SQL rows = 313
- conflicts = 0
- duplicates = 0
- validation = PASS

SQL distribution:

- attr12 = 78 INSERT
- attr13 = 78 INSERT
- attr14 = 78 INSERT
- attr15 = 1 INSERT
- attr125 = 78 INSERT

Production DB НЕ изменялась.

---

# GRUNDFOS

Category:

`11900360`

Полный target scope:

- 27 товаров

После ручной верификации:

- selector-ready = 27
- review = 0

Generated пересобран после всех manual decisions.

Файлы:

`catalog-standardization-new/generated/grundfos/grundfos_match_preview.csv`

`catalog-standardization-new/generated/grundfos/grundfos_attributes_preview.csv`

`catalog-standardization-new/generated/grundfos/grundfos_attributes_insert.sql`

Итог:

- products = 27
- selector-ready = 27
- preview rows = 108
- attr12 = 27 / 27
- attr13 = 27 / 27
- attr15 = 27 / 27
- attr125 = 27 / 27
- SQL INSERT rows = 108
- conflicts = 0
- duplicates = 0
- empty = 0
- nonnumeric = 0

SQL содержит:

- attr12 = 27
- attr13 = 27
- attr15 = 27
- attr125 = 27
- UPDATE = 0
- DELETE = 0

## Последние manual decisions

### product_id 3277

Grundfos SP 125-3  
Product number: `17A01903`

- attr12 = 90
- attr13 = 2700
- attr15 = 380
- attr125 = 215

H/Q:
`CURVE_APPROXIMATE`

### product_id 7997

Grundfos SP 17-18  
Candidate/product configuration: `12A01918`

Identity:
`STRONG_CONFIG_MATCH`

- attr12 = 200
- attr13 = 365
- attr15 = 380
- attr125 = 145

H/Q:
`CURVE_APPROXIMATE`

attr125:
DIRECT official product data

### product_id 3307

Grundfos SQ 5-70  
Product number: `96510217`

- attr12 = 106
- attr13 = 125
- attr15 = 220
- attr125 = 76

Identity confirmed using exact-product Lenntech/Grundfos Product Centre material.

Old malformed NasosyMarket `sqe_5_70` URL is rejected as source artifact.

### product_id 3314

Grundfos SQ 3-105 с кабелем 80 м  
Correct product number: `96524448`

- attr12 = 147
- attr13 = 75
- attr15 = 220
- attr125 = 76

`96510210` НЕ является identity для 3314.

`96510210` = SQ 3-105 с коротким кабелем 1.5 м и относится к другой конфигурации/товару.

---

# COMBINED VALIDATION

Последняя combined file-level проверка:

## VINKO

- products = 78
- selector-ready = 78
- selector-critical coverage = 312 / 312
- validation = PASS

## Grundfos

- products = 27
- selector-ready = 27
- selector-critical coverage = 108 / 108
- validation = PASS

## Combined

- products = 105
- attr12 = 105 / 105
- attr13 = 105 / 105
- attr15 = 105 / 105
- attr125 = 105 / 105
- duplicate product+attribute = 0
- cross-vendor product_id overlap = 0
- validation = PASS

SQL safety:

- VINKO only INSERT = PASS
- Grundfos only INSERT = PASS
- UPDATE = NO
- DELETE = NO
- forbidden DML/DDL = NO
- scope leakage = NO

---

# ТЕКУЩИЙ BLOCKER

Combined production-readiness пока НЕ разрешён.

Причина:

актуальная production DB не была повторно проверена после завершения подготовки generated-данных.

Предыдущая попытка production collision check остановилась, потому что у Codex не было настроенного read-only production DB доступа.

Но перед collision check принято новое решение:

**сначала заново проверить сам production inventory на соответствие нашим входящим данным.**

Причина:
между предыдущей инвентаризацией и сегодняшним днём в production могли:

- добавить товары;
- удалить товары;
- отключить товары;
- изменить category assignment;
- изменить manufacturer;
- изменить target scope.

Поэтому нельзя сразу проверять только 421 intended INSERT rows.

---

# NEXT STEP — РОВНО ОДИН

Следующий ограниченный шаг:

## PRODUCTION INVENTORY RE-CHECK

Только READ-ONLY.

Нужно заново снять актуальный inventory production DB для VINKO и Grundfos и сравнить его с scope, на котором построены текущие generated artifacts.

Пока НЕ проверять collision атрибутов.

Проверить минимум:

### VINKO

- текущие active товары производителя VINKO в соответствующем scope/category
- существуют ли все 78 intended product_id
- не появились ли новые target products
- не исчезли ли старые
- status changes
- category changes
- manufacturer changes
- изменение общего target assortment

### Grundfos

- актуальные товары category `11900360`
- существуют ли все 27 intended product_id
- active/status
- category membership
- manufacturer
- added / removed / changed scope

Вывести diff:

- unchanged
- added_since_snapshot
- removed_since_snapshot
- status_changed
- category_changed
- manufacturer_changed
- intended_product_missing

Никаких INSERT / UPDATE / DELETE.

Если Codex не может подключиться к production DB read-only:
сгенерировать небольшой SELECT-only SQL probe для ручного запуска пользователем.

После inventory reconciliation — STOP.

Только отдельным следующим gate можно переходить к attribute collision check.

---

# НЕ ДЕЛАТЬ В СЛЕДУЮЩЕЙ СЕССИИ БЕЗ ОТДЕЛЬНОГО `+`

Не:

- выполнять VINKO SQL
- выполнять Grundfos SQL
- менять production DB
- пересобирать generated без обнаруженной причины
- начинать collision check до inventory reconciliation
- исправлять attr14/power
- возвращаться к спору P1/P2
- менять selector contract
- commit
- push

---

# PRODUCTION APPLY SEQUENCE

После успешного inventory re-check порядок остаётся таким:

1. production inventory reconciliation
2. attribute collision check против актуальной production DB
3. финальный production-readiness verdict
4. отдельный PRODUCTIVE apply gate
5. production SQL apply
6. post-apply verification
7. только затем commit/push/documentation cleanup при необходимости

Ни один пункт автоматически не разрешает следующий.

---

# ВАЖНО ДЛЯ НОВОГО ЧАТА

Пользователь предпочитает:

- один bounded step за раз;
- не переходить дальше без отдельного `+`;
- SQL production запускает сам;
- не делать предположений вместо проверки;
- Codex должен отвечать только на русском языке;
- не тратить время на attr14/power, если задача касается Pump Selector.

Первое действие новой сессии:

**подготовить bounded-задачу на актуальную read-only production inventory re-check VINKO + Grundfos.**