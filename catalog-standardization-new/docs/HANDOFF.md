# HANDOFF — catalog-standardization-new / pump-selector

Дата: 2026-08-08

## Контекст

Репозиторий:

`saitkharkov-cyber/home-energetika`

Ветка:

`main`

Рабочие области:

- `catalog-standardization-new`
- `pump-selector`

Работа ведётся строго по принципу **одного ограниченного шага за раз**.
Не переходить к следующему шагу без отдельного `+` / `++` пользователя.
Не менять production без отдельного явного разрешения.

---

# ГЛАВНЫЙ СТАТУС НА КОНЕЦ СЕССИИ

Стандартизация selector-critical данных VINKO / Grundfos / DYU и расширение selector cache уже выполнены ранее.

Текущая задача больше НЕ состоит в подготовке этих данных.

Текущая задача:

> **перепроектировать бизнес-логику Pump Selector ranking так, чтобы выдача Best Price / Optimal / Premium была понятной человеку и не требовала постоянного латания математических исключений.**

В конце сессии принято решение **не продолжать немедленно менять runtime ranking**.
Сначала нужно вручную сформулировать человеческую бизнес-логику ролей на реальных сценариях, а уже потом решать, какая математика действительно нужна.

---

# КАТАЛОГ / CACHE — БАЗОВОЕ СОСТОЯНИЕ

Root selector scope:

`category_id = 11900213`

Category normalization ранее завершена:

- каждый товар в subtree `11900213` явно привязан к родительским категориям пути;
- missing ancestor links = 0.

Cache Builder после расширения root scope:

- total scanned = 2466
- eligible inserted = 293

Eligible по брендам:

- Sumoto — 97
- VINKO — 78
- Pedrollo — 58
- Belamos — 32
- Grundfos — 27
- DYU — 1

Brand tier mapping через текущий `brand_priority`:

- `>=10` → tier 3 PREMIUM → Pedrollo / Grundfos
- `>=8` → tier 2 UPPER → Sumoto / Summoto
- `>=5` → tier 1 STANDARD → VINKO / Belamos / DYU
- иначе tier 0 UNKNOWN

Известный технический долг:

- `brand_priority` сейчас перегружен как transport для brand tier;
- Builder eligibility не требует attr15, хотя runtime требует точное voltage match;
- эти вопросы в текущей сессии не трогались.

---

# RANKING V2 — ЧТО УЖЕ РЕАЛИЗОВАНО

Основные runtime-файлы:

- `pump-selector/module/system/library/pump_selector_hydraulic.php`
- `pump-selector/module/system/library/pump_selector_pareto.php`
- `pump-selector/module/system/library/pump_selector_ranking.php`
- `pump-selector/module/system/library/pump_selector_optimal_price_gate.php`
- `pump-selector/module/catalog/model/extension/module/pump_selector_v2.php`

Нормативный документ:

- `pump-selector/docs/RANKING_V2_SPEC.md`

## Hydraulic approximation

Используется approximate endpoint-parabolic model:

```text
q_rel = Q_req / Q_max
h_rel = H_req / H_max
H_est = H_max * (1 - q_rel^2)
reserve_rel = (H_est - H_req) / H_req
```

Hydraulic gate:

```text
reserve_rel < -0.10       → FAIL
-0.10 <= reserve_rel < 0  → BORDERLINE
reserve_rel >= 0          → PASS
```

Target box:

```text
q_rel ∈ [0.35, 0.65]
h_rel ∈ [0.45, 0.75]
```

Reserve comfort region:

```text
+10% ... +25%
```

## Grades

Reserve:

```text
3 IDEAL       10..20%
2 GOOD        5..<10% OR >20..35%
1 ACCEPTABLE  0..<5% OR >35..50%
0 POOR        >50%
```

Fit:

```text
3 IDEAL       d = 0
2 GOOD        0 < d <= .10
1 ACCEPTABLE  .10 < d <= .22
0 POOR        > .22
```

---

# OPTIMAL PRICE GATE

Зафиксировано и реализовано:

```text
Optimal.price <= BestPrice.price * 1.80
```

То есть дорогой технически хороший вариант не может стать Optimal при чрезмерной переплате относительно Best Price.

---

# PREMIUM V2 — ТЕКУЩАЯ РЕАЛИЗОВАННАЯ ПОЛИТИКА

Premium:

- только PASS;
- `brand_tier >= 2`;
- отдельный tier-scoped continuous Pareto;
- разные tier не доминируют друг друга до финального ranking;
- Premium сравнивается с выбранным Optimal;
- допустима инженерная деградация максимум на 1 grade только по одному измерению;
- Premium должен иметь justification:
  - более высокий brand tier, или
  - инженерное улучшение хотя бы на 1 grade;
- **нет hard price gate** относительно Optimal;
- цена — только поздний tie-break.

Premium final sort:

```text
brand_tier DESC
→ engineering improvement DESC
→ engineering degradation ASC
→ reserve_grade DESC
→ fit_grade DESC
→ price ASC
→ product_id ASC
```

---

# GRADE-AWARE GENERAL PARETO — ИЗМЕНЕНИЕ ЭТОЙ СЕССИИ

Проблема была обнаружена на реальном сценарии №3:

DYU DWH3-125 с немного лучшим continuous `reserve_penalty`, но той же парой discrete grades, удалял более дешёвый SUMOTO до Role Ranking.

Принято и реализовано новое правило **только для General Pareto / Optimal**:

> если кандидат дороже, ценовая epsilon-поблажка `ε_price = 2%` разрешается только если он реально улучшает хотя бы один discrete engineering grade и не ухудшает другой.

При одинаковых `reserve_grade / fit_grade` более дорогой кандидат не может удалить более дешёвый только благодаря меньшему `technical_fit` или `reserve_penalty`.

Важно:

- для Optimal используется новый `buildGradeAwareFront()`;
- Premium по-прежнему использует старый continuous `buildFront()` внутри каждого tier.

Изменённые файлы:

- `pump-selector/module/system/library/pump_selector_pareto.php`
- `pump-selector/module/catalog/model/extension/module/pump_selector_v2.php`
- `pump-selector/tests/unit/pump_selector_pareto_test.php`
- `pump-selector/docs/RANKING_V2_SPEC.md`

Тесты, реально выполненные пользователем после изменения:

```text
Pareto unit:
OK (20 tests, 32 assertions)

Pipeline integration:
OK (1 test, 15 assertions)
```

Не утверждать, что после этого запускался полный suite.

---

# ПОСЛЕДНИЕ КОММИТЫ СЕССИИ

До обновления этого HANDOFF локальный и origin HEAD пользователя был:

`adb9600 Define Best Price exclusion from Optimal`

Последовательность ключевых коммитов сессии:

- `3c9f764 Make general Pareto grade-aware`
- `75ab6ff...` — добавлен grade-aware путь в Pareto library
- `dd305a6...` — Optimal переведён на grade-aware front
- `f1eb2f5 Add grade-aware Pareto unit tests`
- `adb9600 Define Best Price exclusion from Optimal`

После коммита этого HANDOFF HEAD будет новее `adb9600`; при старте новой сессии обязательно проверить фактический `main` / `HEAD` через GitHub и локально по выводу пользователя.

---

# РЕАЛЬНЫЕ СЦЕНАРИИ — ПРОВЕРЕННОЕ

## Сценарий №1

Фактические требования формы:

```text
H_req = 65 м
Q_req = 30 л/мин
220 В
casing unknown
```

После Optimal price gate:

```text
Best Price → Belamos 3TF-90/6 — 16 380
Optimal    → Belamos TF-100/5 — 22 370
Premium    → нет
```

Сценарий прошёл.

## Сценарий №2

Фактические требования формы:

```text
H_req = 73 м
Q_req = 40 л/мин
220 В
casing = 125 мм
```

Текущая реализованная выдача после Premium redesign:

```text
Best Price → Belamos 3TF-90/6 — 16 380
Optimal    → Belamos TF-100/5 — 22 370
Premium    → Grundfos SQ 3-80 — 103 000
```

Сценарий прошёл.

## Сценарий №3 — ГЛАВНЫЙ ДИАГНОСТИЧЕСКИЙ СЦЕНАРИЙ

Параметры формы:

```text
Глубина = 60 м
Уровень воды = 35 м
Расстояние = 20 м
Верхняя точка = 2 этаж
Точки расхода = раковина + душ + стиральная машина + туалет + ванна + полив
Обсадная = 125 мм
Напряжение = 220 В
```

Фактические требования формы:

```text
H_req = 78 м
Q_req = 65 л/мин
```

PASS-кандидаты из диагностики:

```text
1856 SUMOTO OPC 4SRM4/26       87 900  reserve 28.1%  grade 2/3  tier2
1869 SUMOTO 4SRM6/20           95 500  reserve 28.5%  grade 2/3  tier2
1967 VINKO 4STM6/20            95 500  reserve 28.8%  grade 2/3  tier1
7316 DYU DWH3-125              97 000  reserve 22.7%  grade 2/3  tier1
1952 VINKO 4STM4/27           100 100  reserve 28.1%  grade 2/3  tier1
1953 VINKO 4STM4/35           138 600  reserve 66.6%  grade 0/1  tier1
1954 VINKO 4STM4/48           171 600  reserve 128%   grade 0/1  tier1
```

После деплоя grade-aware Pareto в локальный OpenCart реальная форма всё равно показала только одну карточку:

```text
SUMOTO OPC 4SRM4/26 — 87 900
роль: Optimal
```

Причина:

- grade-aware Pareto исправил проблему DYU vs SUMOTO 1869;
- но Best Price product 1856 всё ещё участвует в Optimal pool;
- он сам выигрывает Optimal ranking;
- `prepareResultCards()` объединяет одинаковый `product_id` ролей в одну карточку.

---

# BEST PRICE EXCLUSION FROM OPTIMAL — ТОЛЬКО В SPEC, НЕ В RUNTIME

После сценария №3 в `RANKING_V2_SPEC.md` зафиксирована рабочая гипотеза:

> Best Price исключается из пула Optimal, если после Optimal price gate остаётся хотя бы один другой допустимый PASS-кандидат. Если альтернативы нет — тот же насос может быть и Best Price, и Optimal.

Для scenario №3 это должно дать:

```text
Best Price → 1856 SUMOTO OPC 4SRM4/26 — 87 900
Optimal    → 1869 SUMOTO 4SRM6/20     — 95 500
```

**Но это правило НЕ реализовано в runtime.**

В конце сессии решено пока НЕ реализовывать его, потому что возникло сомнение в общей архитектуре ranking.

---

# КЛЮЧЕВОЕ РЕШЕНИЕ В КОНЦЕ СЕССИИ

Пользователю в целом **не нравится поведение нового подборщика**.

Наблюдение:

- результат слишком чувствителен к Pareto / reserve_penalty / grades / порядку фильтров;
- приходится постфактум объяснять, почему очевидный человеку насос не стал Optimal;
- роли конфликтуют и начинают требовать всё новых исключений;
- есть риск, что математическая архитектура стала сложнее самой бизнес-задачи.

Поэтому принято решение:

> **остановить дальнейшее латание ranking v2 и сначала перепроектировать бизнес-логику ролей сверху вниз на реальных сценариях.**

Ничего из обсуждения ниже пока не считать утверждённой спецификацией.

---

# РУЧНАЯ БИЗНЕС-ЛОГИКА НА СЦЕНАРИИ №3

Существующий алгоритм временно игнорировали и посмотрели на 7 PASS-кандидатов как продавец-консультант.

Ручной выбор получился:

```text
BEST PRICE
1856 SUMOTO OPC 4SRM4/26 — 87 900

OPTIMAL
1869 SUMOTO 4SRM6/20 — 95 500

PREMIUM
нет убедительного кандидата
```

Почему Optimal = 1869, а не DYU:

- +7600 к Best Price, то есть умеренная доплата;
- рабочая точка `q_rel≈0.406 / h_rel≈0.65` выглядит более комфортно внутри target zone;
- Best Price 1856 стоит почти на двух границах target box: `q_rel=.65 / h_rel=.45`;
- reserve почти одинаковый: ~28.1% vs ~28.5%;
- DYU 7316 тоже технически хорош, но стоит 97 000 и не даёт очевидного бизнес-преимущества перед Sumoto 1869 за 95 500.

Почему Premium = none:

- в этих 7 PASS нет Pedrollo / Grundfos;
- дорогие VINKO 1953/1954 — это скорее переразмер, а не Premium;
- высокая цена сама по себе не делает насос Premium.

---

# НОВАЯ ИДЕЯ: PREMIUM-OR-ALTERNATIVE

В обсуждении появилась идея, которая пользователю показалась логичной:

```text
1. Best Price
   → самый дешёвый нормально подходящий насос

2. Optimal
   → лучший баланс цены и рабочей точки

3. Premium
   → настоящий более высокий класс, если он есть

   ИНАЧЕ

   Alternative
   → хороший сопоставимый насос ДРУГОГО БРЕНДА относительно Optimal
```

Для scenario №3 естественная выдача могла бы быть:

```text
Best Price  → SUMOTO OPC 4SRM4/26 — 87 900
Optimal     → SUMOTO 4SRM6/20     — 95 500
Alternative → DYU DWH3-125        — 97 000
```

Смысл Alternative:

- другой бренд относительно Optimal;
- технически нормально подходит;
- близок к Optimal по качеству подбора;
- не является заведомым переразмером;
- не нужно ложно маркировать его Premium.

Эта идея **ТОЛЬКО обсуждается**, не зафиксирована в spec и не реализована.

Также обсуждалось, что роли могут совпадать по product_id при очень малом candidate pool, но это тоже оставлено на уровне обсуждения.

---

# НАПРАВЛЕНИЕ НА СЛЕДУЮЩУЮ СЕССИЮ

Не начинать с кода.

Сначала продолжить ручное проектирование бизнес-логики.

Следующий ограниченный шаг:

> **взять реальный сценарий №2 и вручную, без Pareto и без текущего ranking, определить Best Price / Optimal / Premium-or-Alternative по тем же человеческим принципам, что использовали для scenario №3.**

Цель:

- проверить, повторяется ли одна и та же простая бизнес-логика на другом реальном scenario;
- понять, какие критерии действительно стабильны;
- только потом формализовать новый ranking design.

До этого НЕ:

- реализовывать Best Price exclusion из Optimal;
- менять Premium runtime;
- добавлять Alternative в код;
- переписывать Pareto;
- менять target box / grades / epsilon;
- коммитить новую ranking-логику;
- трогать production.

---

# ТЕСТОВАЯ СРЕДА

PHP 5.6:

```text
C:\php56\php.exe
php.ini: C:\php56\php.ini
PHPUnit: D:\Git\home-energetika\tools\phpunit-5.7.27.phar
```

Команды должны быть совместимы с PHP 5.6 / PHPUnit 5.7.27.

Последние реально подтверждённые тесты:

```text
pump_selector_pareto_test.php
OK (20 tests, 32 assertions)

pump_selector_ranking_pipeline_test.php
OK (1 test, 15 assertions)
```

После Premium redesign ранее отдельно подтверждалось:

```text
pump_selector_ranking_test.php
OK (29 tests, 30 assertions)
```

Не выдавать это за единый полный suite run.

---

# PROTECTED USER-OWNED / UNTRACKED

Не трогать и не удалять без отдельного разрешения:

- `catalog-standardization-new/docs/HANDOFF_OLD.md`
- `catalog-standardization-new/scripts/probe_vinko_known_search_context.py`
- `catalog-standardization-new/scripts/probe_vinko_missing_models.py`
- `catalog-standardization-new/scripts/probe_vinko_product.py`
- `catalog-standardization-new/scripts/probe_vinko_search.py`
- `catalog-standardization-new/scripts/probe_vinko_search_form.py`
- `catalog-standardization-new/tmp/`
- `catalog-standardization/scripts/parsers/parse_vinko_catalog.py`
- `catalog-standardization/scripts/vinko_parsed_preview.csv`

Не утверждать, что локальное рабочее дерево чистое, если пользователь сам не показал `git status`.

---

# ПРАВИЛА КОММУНИКАЦИИ

Пользователь предпочитает:

- русский язык;
- строго один bounded step за раз;
- `+` / `++` = разрешение на следующий шаг;
- не прыгать вперёд;
- сначала объяснять бизнес-смысл, потом формулы;
- не подменять проверку предположениями;
- SQL production пользователь запускает сам;
- PowerShell команды желательно давать одной строкой;
- при завершении сессии обновлять HANDOFF и давать стартовый промпт.

---

# FIRST ACTION NEXT SESSION

1. Прочитать `catalog-standardization-new/docs/START_HERE.md` и документы в порядке, который он задаёт.
2. Обязательно прочитать актуальные:
   - `catalog-standardization-new/docs/RULES.md`
   - `catalog-standardization-new/docs/DECISIONS.md`
   - `catalog-standardization-new/docs/HANDOFF.md`
   - `pump-selector/docs/SELECTOR_CONTRACT.md`
   - `pump-selector/docs/SELECTION_STRATEGY.md`
   - `pump-selector/docs/RANKING_V2_SPEC.md`
3. Проверить GitHub `main` / актуальный HEAD, ничего не изменяя.
4. Кратко восстановить статус.
5. Назвать ровно один следующий шаг: **ручной разбор scenario №2 по схеме Best Price / Optimal / Premium-or-Alternative без изменения кода.**
6. STOP и ждать отдельного `+`.
