# HANDOFF — catalog-standardization-new / pump-selector

Дата: 2026-08-09

## Контекст

Репозиторий: `saitkharkov-cyber/home-energetika`

Ветка: `main`

Рабочие области:

- `catalog-standardization-new`
- `pump-selector`

Работа ведётся строго по принципу **одного ограниченного шага за раз**. Не переходить к следующему шагу без отдельного `+` / `++` пользователя.

V1 и V2 подборщика сохраняются рядом с V3 как reference / rollback. Не удалять и не переписывать их без отдельного решения.

---

# ГЛАВНЫЙ СТАТУС

Основная логика **Pump Selector Ranking V3 реализована и прошла широкую проверку на реальном test runtime / cache**.

V3 использует последовательное назначение ролей:

```text
Candidate Pool
→ Hydraulic evaluation
→ Best Price
→ Exclusion Pool
→ Optimal
→ Exclusion Pool
→ Premium
→ если Premium отсутствует → Alternative
```

Цель V3 достигнута:

- роли понятны человеку;
- один товар не занимает несколько ролей;
- не используется искусственное заполнение трёх карточек плохими насосами;
- 0/1/2 карточки допустимы, если достойных кандидатов действительно нет.

На конец сессии **ranking V3 считаем практически завершённым**.

Главная оставшаяся функциональная задача:

> **consultation popup + server-side повторный V3 calculation + отправка заявки менеджеру + отправка результатов подбора клиенту.**

---

# GIT STATE

Последний подтверждённый пользователем commit, находящийся и в `HEAD`, и в `origin/main` до локальных UX/regression-правок:

`26394e6 — Add ranking v3 controller and template`

Ключевые V3 commits:

- `b5ed3dc` — Add ranking v3 sequential assignment specification
- `395fa1e` — Add ranking v3 best price assignment helper
- `02aea0d` — Add isolated ranking v3 runtime skeleton
- `52f2f20` — Add ranking v3 optimal assignment
- `3775c7e` — Add ranking v3 premium assignment
- `2f448fd` — Add ranking v3 alternative fallback
- `26394e6` — Add ranking v3 controller and template

При старте новой сессии обязательно проверить фактический `main` / `HEAD` и локальный `git status --short`: после `26394e6` выполнялись локальные regression/CTA-правки, часть которых могла оставаться незакоммиченной.

---

# NORMATIVE V3 SPEC

Главный нормативный документ:

`pump-selector/docs/RANKING_V3_SPEC.md`

V2 spec остаётся историческим документом предыдущей Pareto-архитектуры и не является контрактом V3.

---

# V1 / V2 / V3 — ИЗОЛЯЦИЯ

## V1

Исходный controller:

`pump-selector/module/catalog/controller/extension/module/pump_selector.php`

Исходная версия сохраняется.

## V2

Controller:

`pump-selector/module/catalog/controller/extension/module/pump_selector_v2.php`

Model:

`pump-selector/module/catalog/model/extension/module/pump_selector_v2.php`

V2 сохраняется как reference / rollback.

## V3

Controller:

`pump-selector/module/catalog/controller/extension/module/pump_selector_v3.php`

Model:

`pump-selector/module/catalog/model/extension/module/pump_selector_v3.php`

Assignment helper:

`pump-selector/module/system/library/pump_selector_assignment_v3.php`

Template:

`pump-selector/module/catalog/view/theme/revolution/template/extension/module/pump_selector_v3.tpl`

Route:

`extension/module/pump_selector_v3`

V3 работает отдельным контуром и не требует замены V1/V2.

---

# V3 ROLE CONTRACT

Поддерживаются роли:

- `best_price`
- `optimal_choice`
- `premium`
- `alternative`

V3 template поддерживает `alternative` с пользовательской подписью **«Альтернатива»**.

---

# V3 OPERATIONAL RULES

## Best Price

- сначала PASS;
- если PASS отсутствуют — BORDERLINE;
- FAIL исключены;
- выбор: `price ASC → product_id ASC`;
- выбранный product_id добавляется в Exclusion Pool.

## Optimal

- только PASS;
- минус Exclusion Pool;
- eligibility: `reserve_grade >= 1`;
- выбор: `fit_grade DESC → price ASC → product_id ASC`.

Reserve grade после eligibility не используется как дополнительный ranking preference.

## Premium

- только PASS;
- минус Exclusion Pool;
- только `brand_tier == 3` (Pedrollo / Grundfos);
- `fit_grade >= 2`;
- `reserve_grade >= 2`;
- выбор: `price ASC → product_id ASC`.

## Alternative

Используется только если Premium отсутствует.

- только PASS;
- минус Exclusion Pool;
- manufacturer отличается от Optimal;
- valid `manufacturer_id`;
- `fit_grade >= 2`;
- `reserve_grade >= 1`;
- выбирается ближайшая к Optimal рабочая точка по `(q_rel, h_rel)`, затем цена, затем product_id.

Если Optimal отсутствует, Alternative не строится.

## Exclusion invariant

При достаточном числе достойных кандидатов роли должны иметь разные `product_id`.

Не ослаблять gates только ради трёх карточек.

---

# UNIT TESTS V3

Последние подтверждённые результаты:

```text
pump_selector_assignment_v3_test.php
OK (36 tests, 36 assertions)

pump_selector_v3_model_test.php
OK (14 tests, 50 assertions)
```

PHP:

`C:\php56\php.exe`

PHPUnit:

`.\tools\phpunit-5.7.27.phar`

---

# РУЧНОЕ ТЕСТИРОВАНИЕ

На test-site вручную проверено около 15 сценариев.

- примерно 14 дали в целом понятную/разумную выдачу;
- один тяжёлый сценарий дал только одну карточку и был отдельно исследован.

## Валидный edge case

```text
H_req = 112.5
Q_req = 65
220 V
casing = 125
```

Фактический результат: одна карточка Best Price — VINKO 4STM4/35.

DB diagnostic показал только два hydraulic PASS:

- product_id 1953 VINKO: Hmax 225, Qmax 100, reserve 15.5%, reserve_grade 3;
- product_id 1954 VINKO: Hmax 308, Qmax 100, reserve 58.1%, reserve_grade 0.

1953 занимает Best Price и исключается. 1954 не проходит Optimal из-за `reserve_grade < 1`.

Это признано **валидным edge case**, а не багом.

---

# HTTP REGRESSION RUNNER

Создан локальный runner:

`pump-selector/tests/regression/run_v3_http_matrix.py`

Он работает через настоящий V3 route:

`index.php?route=extension/module/pump_selector_v3`

Runner использует реальный POST contract, умеет single/matrix/CSV и proxy через `--proxy-file`.

Project proxy:

`catalog-standardization-new/scripts/local_proxy.txt`

Proxy credentials не должны логироваться.

## Реальный form contract

POST fields:

- `total_well_depth_m`
- `water_level_mode`
- `water_level_m`
- `distance_to_house_m`
- `highest_water_point_floor`
- `custom_vertical_lift_m`
- `water_points[]`
- `casing_diameter_mode`
- `casing_diameter_mm`
- `voltage_mode`
- `website`

Water points:

- `sink`
- `shower`
- `toilet`
- `washing_machine`
- `dishwasher`
- `irrigation`

H/Q нельзя напрямую подставлять в HTTP test: controller/model вычисляют их из пользовательских inputs.

---

# SINGLE REAL HTTP VERIFICATION

Проверенный scenario:

```text
H = 75
Q = 40
220 V
casing = 125
```

Фактический result:

```text
HTTP 200
cards = 3
unique = 3
best = 1569
optimal = 1600
card3 = premium:3307
flags =
```

Карточки:

- 1569 — `best_price` — Belamos 3TF-90/6 — 16 380 руб.
- 1600 — `optimal_choice` — Belamos TF-100/5 — 22 370 руб.
- 3307 — `premium` — Grundfos SQ 5-70 — 104 000 руб.

Это подтвердило полный путь:

`HTTP → V3 controller → real test DB/cache → V3 model → V3 template → parser`.

---

# MASS HTTP REGRESSION MATRIX

Выполнена матрица:

`6 H × 5 Q × 2 voltage × 3 casing = 180 scenarios`

Ключевые итоги:

```text
HTTP 200:                180/180
PARSE_ERROR:             0
duplicate product IDs:   0
MORE_THAN_3_CARDS:       0
PREMIUM_AND_ALTERNATIVE: 0
```

Распределение:

- 3 cards — 134
- 2 cards — 6
- 1 card — 18
- 0 cards — 22

Card3:

- Premium — 97
- Alternative — 37
- no card3 — 46

Основная концентрация малых результатов — `casing = 100`.

CSV:

`pump-selector/tests/regression/output/v3_http_matrix.csv`

Перед коммитом regression output отдельно решить, хранить ли generated CSV в Git.

---

# NO_RESULT — ПОЛНАЯ ДИАГНОСТИКА

Создан read-only SQL:

`pump-selector/tests/regression/sql/v3_no_result_diagnostics.sql`

Все 22 `NO_RESULT` проверены на реальной test DB `insurgent_ocar22`.

Группы:

- 380 V + casing 100 — 20
- 220 V + casing 100 — 2

Результат:

```text
22/22 = NO_HYDRAULIC_MATCH
UNEXPECTED_NO_RESULT = 0
```

То есть voltage pool и casing pool существуют, но нет даже hydraulic BORDERLINE candidate.

Следовательно, подтверждённые `NO_RESULT` **не являются defect V3 ranking/runtime**.

Для 380 V + casing 100 типовой pool:

```text
eligible_total = 293
after_voltage = 136
after_casing = 5
```

Эти 5 — узкие Pedrollo 3SR:

- 1759 — 3SR 2/14
- 1760 — 3SR 2/21
- 1761 — 3SR 2/28
- 1762 — 3SR 2/41
- 1805 — 3SR 1/62

У первых четырёх Qmax=45 л/мин, у последнего Qmax=30 л/мин. Узкий ассортимент физически объясняет большое число NO_RESULT при высоких Q/H.

---

# CONSULTATION CTA — НАЙДЕН ИСХОДНЫЙ DEFECT

Кнопка **«Получить консультацию»** была inert ещё в V1.

Доказанная причина:

- `<button type="button">`;
- нет href;
- нет onclick;
- нет JS listener;
- нет modal target;
- нет controller endpoint.

Это не V3 regression.

Git blame связывал появление CTA с commit `b6d5b54` от 2026-06-13.

Был согласован временный fallback на существующую contact page:

`index.php?route=information/contact`

Перед продолжением проверить локальный diff/status и определить, применён ли fallback сейчас в общем template и V3 template и закоммичен ли он. Это не финальный UX.

---

# FINAL CONSULTATION POPUP CONTRACT

Это **главная оставшаяся функциональность перед окончательным завершением Pump Selector**.

## UX

Кнопка `Получить консультацию` должна открывать popup на текущей странице.

Поля:

- Имя
- Телефон
- Email

Email можно сделать необязательным.

## Security / data trust

Нельзя доверять переданным браузером `product_id`, price, role или product name.

Правильный flow:

1. browser передаёт исходные пользовательские параметры selector;
2. server повторно выполняет `calculateRequirements()`;
3. server повторно запускает V3;
4. server сам получает актуальные рекомендованные карточки;
5. только серверный результат используется для писем.

## Письмо менеджеру

Отправлять:

- имя;
- телефон;
- email;
- исходные параметры пользователя;
- расчётный H/Q;
- фактические результаты V3;
- role;
- product;
- price;
- product URL.

Если рекомендаций 1 или 2 — отправлять фактические рекомендации, не дополнять до трёх.

## Письмо клиенту

Если указан email, автоматически отправлять письмо:

**«Результаты подбора скважинного насоса»**

Содержание:

- пользовательские параметры;
- расчётный напор;
- расчётный расход;
- напряжение;
- диаметр обсадной трубы;
- 1–3 фактические рекомендации;
- роль каждой рекомендации;
- название;
- цена;
- ссылка на товар.

Добавить короткое примечание, что подбор предварительный и специалист может уточнить модель с учётом фактических условий скважины.

После успешной отправки popup показывает success state.

---

# ЧТО НЕ НУЖНО ПЕРЕДЕЛЫВАТЬ

Без нового доказанного дефекта:

- не переделывать V3 ranking;
- не возвращаться к Pareto V2;
- не ослаблять hydraulic/ranking gates ради трёх карточек;
- не считать 0/1/2 карточки автоматически ошибкой;
- не считать подтверждённые NO_RESULT дефектом;
- не удалять V1/V2;
- не доверять client-side product/price/role в consultation submit.

---

# CACHE / SELECTOR DATA

Selector использует отдельную cache table:

`oc_pump_selector_product`

Root category:

`11900213 — Скважинные насосы`

Последний подтверждённый cache:

- eligible — 293
- Sumoto — 97
- VINKO — 78
- Pedrollo — 58
- Belamos — 32
- Grundfos — 27
- DYU — 1

При появлении новых товаров нужен отдельный cache builder / refresh.

---

# PROTECTED / UNRELATED LOCAL FILES

Не трогать без отдельного решения:

- `catalog-standardization-new/docs/HANDOFF_OLD.md`
- `catalog-standardization-new/scripts/probe_vinko_known_search_context.py`
- `catalog-standardization-new/scripts/probe_vinko_missing_models.py`
- `catalog-standardization-new/scripts/probe_vinko_product.py`
- `catalog-standardization-new/scripts/probe_vinko_search.py`
- `catalog-standardization-new/scripts/probe_vinko_search_form.py`
- `catalog-standardization-new/tmp/`
- `catalog-standardization/scripts/parsers/parse_vinko_catalog.py`
- `catalog-standardization/scripts/vinko_parsed_preview.csv`
- `pump-selector/module/catalog/model/extension/module/module.php`
- `tools/`

Regression-файлы текущей работы не путать с protected unrelated files.

---

# REGRESSION FILES ТЕКУЩЕЙ РАБОТЫ

Созданы/используются локально:

- `pump-selector/tests/regression/run_v3_http_matrix.py`
- `pump-selector/tests/regression/output/v3_http_single.csv`
- `pump-selector/tests/regression/output/v3_http_matrix.csv`
- `pump-selector/tests/regression/sql/v3_no_result_diagnostics.sql`

Перед коммитом решить, какие generated output CSV следует хранить в Git, а какие оставить локальными.

---

# ТЕКУЩАЯ ЦЕЛЬ НОВОЙ СЕССИИ

Ranking V3 не является текущей задачей.

Текущая задача:

> **завершить Pump Selector UX/lead flow через consultation popup и отправку результатов.**

---

# РОВНО ОДИН СЛЕДУЮЩИЙ BOUNDED STEP

**Спроектировать минимальный server-side contract consultation popup для V3: endpoint, входные поля, повторный server-side V3 calculation, manager email, optional client email и success/error response.**

На этом шаге ranking не менять и popup пока не реализовывать. Сначала определить contract и способ переиспользования существующей OpenCart mail infrastructure.

После этого STOP и ждать отдельного `+`.
