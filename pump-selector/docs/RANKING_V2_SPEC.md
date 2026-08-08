# Ranking v2 Mathematical Specification

## 1. Статус документа

Документ фиксирует утверждённое математическое ядро новой логики ранжирования подборщика насосов.

Статус: **working specification v2**.

Документ является нормативной основой для реализации ranking v2, unit-тестов и regression-проверок.

На этом этапе документ сам по себе **не изменяет** production-выдачу, cache Builder или текущий selector pipeline.

---

## 2. Цель

Новая модель сохраняет три независимых бизнес-роли:

- **Best Price** — самый дешёвый уверенно допустимый вариант;
- **Optimal** — лучший инженерно-экономический баланс;
- **Premium** — лучший допустимый вариант более высокого продуктового/брендового класса либо инженерно улучшенный вариант из Premium Pool; цена для Premium не является hard gate.

Модель должна масштабироваться на многобрендовый каталог без жёсткой линейной лестницы `brand_priority` в Best Price/Optimal, но роль Premium сознательно использует `brand_tier` как главный business factor после инженерного допуска.

---

## 3. Архитектура

```text
CATALOG
   │
   ▼
GATES
   │
   ├── PASS
   │     │
   │     ├── BEST PRICE → min(price)
   │     │
   │     ├── OPTIMAL PRICE GATE
   │     │       ↓
   │     │   exclude Best Price
   │     │   if another valid PASS candidate remains
   │     │       ↓
   │     │   GENERAL grade-aware ε-PARETO
   │     │       ↓
   │     │     OPTIMAL
   │     │
   │     └── PREMIUM POOL
   │             ↓
   │      tier-scoped ε-PARETO
   │             ↓
   │        compare vs Optimal
   │             ↓
   │        Engineering Gate
   │             ↓
   │       Premium justification
   │             ↓
   │      brand-first role ranking
   │             ↓
   │          PREMIUM
   │
   ├── BORDERLINE
   │       ↓
   │   fallback only
   │   Best Price / Optimal
   │
   └── FAIL
           ↓
        excluded
```

Разделение ответственности:

- **Gates** — физическая допустимость;
- **ε-Pareto** — устранение физико-экономически доминируемых вариантов;
- **Role Ranking** — назначение бизнес-роли;
- **Brand tier** — допуск в Premium Pool и главный business factor финального Premium ranking;
- бренд не входит в общий физико-экономический Pareto-вектор для Optimal.

---

## 4. Доступные данные и уровень достоверности

Для текущего каталога доступны только предельные характеристики:

- `H_max` — максимальный напор;
- `Q_max` — максимальная производительность.

Реальная заводская Q-H кривая и BEP отсутствуют.

Поэтому применяется приближённая модель:

```text
hydraulic_model = endpoint_parabolic_estimate
confidence = approximate
```

В будущем hydraulic provider может быть заменён на реальные заводские кривые без изменения слоёв `Gates → Pareto → Roles`.

---

## 5. Аппроксимация Q-H характеристики

```text
q_rel = Q_req / Q_max
h_rel = H_req / H_max

H_est(Q_req) = H_max * (1 - q_rel^2)
```

Формула используется как ранжирующая аппроксимация, а не как лабораторно точная характеристика конкретной модели.

---

## 6. Hydraulic Gate

```text
reserve_rel = (H_est - H_req) / H_req
```

Default v1:

```text
reserve_rel < -0.10       → FAIL
-0.10 <= reserve_rel < 0  → BORDERLINE
reserve_rel >= 0          → PASS
```

### 6.1. Использование BORDERLINE

- **Best Price:** сначала PASS; BORDERLINE только fallback при полном отсутствии PASS.
- **Optimal:** сначала PASS; BORDERLINE только отдельный fallback при полном отсутствии PASS.
- **Premium:** BORDERLINE запрещён.
- **FAIL:** не участвует ни в одной роли.

Порог `-10%` остаётся default v1 и подлежит regression-проверке на полном candidate pool.

---

## 7. Target Box и technical_fit

Default target box:

```text
q_rel ∈ [0.35, 0.65]
h_rel ∈ [0.45, 0.75]
```

Это условная целевая рабочая область approximate-модели, а не заявленная производителем POR/BEP.

Расстояние до зоны:

```text
Δq =
    0.35 - q_rel,  если q_rel < 0.35
    q_rel - 0.65,  если q_rel > 0.65
    0,             иначе

Δh =
    0.45 - h_rel,  если h_rel < 0.45
    h_rel - 0.75,  если h_rel > 0.75
    0,             иначе

technical_fit = d_zone = sqrt(Δq^2 + Δh^2)
```

Направление оптимизации:

```text
technical_fit → MIN
```

Если точка находится внутри Target Box, `technical_fit = 0`.

---

## 8. Reserve Penalty

Сырой `reserve_rel` не минимизируется к нулю. Для Pareto используется непрерывная функция:

```text
reserve_penalty(R) =
    2 * (0.10 - R),  если R < 0.10
    0,                если 0.10 <= R <= 0.25
    R - 0.25,         если R > 0.25
```

Default v1:

```text
comfort reserve = +10% ... +25%
low_reserve_multiplier = 2.0
```

Направление оптимизации:

```text
reserve_penalty → MIN
```

---

## 9. General ε-Pareto

Общий Pareto-вектор:

```text
technical_fit     → MIN
reserve_penalty   → MIN
price             → MIN
```

Brand / brand tier в общий Pareto-вектор не входит.

### 9.1. Grade-aware ε-доминирование по цене

Default v2:

```text
ε_price = 0.02
```

Непрерывные метрики сохраняются в Pareto-векторе, но ценовая ε-поблажка для более дорогого кандидата разрешается только при реальном улучшении хотя бы одного дискретного инженерного grade.

Сначала требуется отсутствие ухудшения по grades:

```text
A.reserve_grade >= B.reserve_grade
AND
A.fit_grade >= B.fit_grade
```

#### Режим A — есть улучшение инженерного класса

Если хотя бы один grade строго лучше:

```text
A.reserve_grade > B.reserve_grade
OR
A.fit_grade > B.fit_grade
```

то `A` может ε-доминировать `B`, если одновременно:

```text
A.technical_fit <= B.technical_fit
AND
A.reserve_penalty <= B.reserve_penalty
AND
A.price <= B.price * 1.02
AND
(
    A.technical_fit < B.technical_fit
    OR
    A.reserve_penalty < B.reserve_penalty
)
```

То есть переплата до 2% допускается только за переход в лучший дискретный инженерный класс.

#### Режим B — grades одинаковые

Если:

```text
A.reserve_grade = B.reserve_grade
AND
A.fit_grade = B.fit_grade
```

то более дорогой `A` **не может** доминировать `B` только за счёт лучшего `technical_fit` или `reserve_penalty`.

Для доминирования внутри одинаковой пары grades требуется:

```text
A.price <= B.price
```

и при этом непрерывная инженерия должна быть не хуже:

```text
A.technical_fit <= B.technical_fit
AND
A.reserve_penalty <= B.reserve_penalty
```

с хотя бы одним строгим улучшением по непрерывной инженерии или цене.

Для полностью одинаковой инженерии более дешёвый кандидат доминирует более дорогой.

### 9.2. Контрольный смысл правила

Для кандидатов с одинаковыми `reserve_grade / fit_grade` небольшое улучшение continuous metric не должно уничтожать более дешёвый вариант до Role Ranking.

Контрольный сценарий `H_req=78`, `Q_req=65`, `220 В`, `125 мм`:

```text
SUMOTO 4SRM6/20:
reserve_grade = 2
fit_grade = 3
price = 95500
reserve_penalty ≈ 0.0346

DYU DWH3-125:
reserve_grade = 2
fit_grade = 3
price = 97000
reserve_penalty = 0
```

При одинаковых grades DYU дороже, поэтому он не может удалить SUMOTO только из-за меньшего `reserve_penalty`. Оба должны дожить до Role Ranking, где цена выбирается раньше low-order brand factor.

Инженерные epsilon в v2 не вводятся.

---

## 10. Непрерывные метрики и дискретные grades

- `technical_fit`, `reserve_penalty`, `price` — непрерывные величины для Pareto;
- `reserve_grade`, `fit_grade` — дискретные ранги для Role Ranking и для ограничения применения ценового ε-доминирования.

Pareto и Role Ranking сознательно используют разные представления одной и той же инженерной картины, но General Pareto не должен удалять кандидата из-за малой continuous-разницы внутри одинакового инженерного grade-класса, если доминирующий кандидат дороже.

---

## 11. Reserve Grade

Применяется только к PASS-кандидатам.

| Grade | Rank | reserve_rel |
|---|---:|---|
| IDEAL | 3 | `0.10 <= R <= 0.20` |
| GOOD | 2 | `0.05 <= R < 0.10` или `0.20 < R <= 0.35` |
| ACCEPTABLE | 1 | `0 <= R < 0.05` или `0.35 < R <= 0.50` |
| POOR | 0 | `R > 0.50` |

Для BORDERLINE `reserve_grade = N/A`.

В Pareto диапазон `+10...+25%` имеет `reserve_penalty = 0`, но Role Ranking сознательно различает:

```text
+10...+20% → IDEAL
>20...+25% → GOOD
```

---

## 12. Technical Fit Grade

| Grade | Rank | technical_fit |
|---|---:|---|
| IDEAL | 3 | `d_zone = 0` |
| GOOD | 2 | `0 < d_zone <= 0.10` |
| ACCEPTABLE | 1 | `0.10 < d_zone <= 0.22` |
| POOR | 0 | `d_zone > 0.22` |

`IDEAL` означает попадание в условную Target Box approximate-модели.

---

## 13. Best Price

Best Price независим от Optimal и Premium.

```text
1. взять PASS-кандидатов;
2. выбрать min(price);
3. если PASS нет — min(price) среди BORDERLINE;
4. FAIL не участвует.
```

Brand, Pareto-front и Premium-tier не влияют на выбор Best Price.

---

## 14. Optimal

Основной Optimal выбирается только среди PASS-кандидатов:

```text
PASS
  ↓
ценовой gate относительно Best Price
  ↓
если после gate существует хотя бы один кандидат
с product_id != BestPrice.product_id:
    исключить Best Price из Optimal candidate pool
иначе:
    оставить Best Price как единственного допустимого кандидата
  ↓
GENERAL grade-aware ε-PARETO
  ↓
лучшая reserve_grade
  ↓
лучшая fit_grade
  ↓
минимальная price
  ↓
brand factor только как low-order tie-break
  ↓
product_id ASC
```

Для нормального PASS-сценария применяется:

```text
Optimal.price <= BestPrice.price * 1.80
```

### 14.1. Разделение Best Price и Optimal при наличии альтернативы

Best Price и Optimal имеют разные бизнес-смыслы. Поэтому Best Price не должен повторно занимать роль Optimal, если после применения Optimal price gate существует хотя бы один другой допустимый PASS-кандидат.

Нормальное правило:

```text
IF exists candidate C where
    C is PASS
    AND C passed Optimal price gate
    AND C.product_id != BestPrice.product_id
THEN
    remove BestPrice.product_id from Optimal candidate pool
ELSE
    keep BestPrice in Optimal candidate pool
```

Это не является абсолютным запретом совпадения ролей. Если Best Price — единственный допустимый кандидат после gate, он может одновременно быть Optimal.

Исключение Best Price выполняется **до General grade-aware ε-Pareto**, чтобы Pareto и Role Ranking работали уже по альтернативам для роли Optimal.

Контрольный сценарий:

```text
H_req = 78 м
Q_req = 65 л/мин
voltage = 220 В
casing = 125 мм

Best Price:
SUMOTO OPC 4SRM4/26
product_id = 1856
price = 87900

после исключения Best Price ожидаемый Optimal:
SUMOTO 4SRM6/20
product_id = 1869
price = 95500
```

Таким образом, роль Optimal остаётся инженерно-экономическим балансом и не может превратиться в технически идеальный вариант с неограниченной переплатой относительно Best Price, а при наличии реальной альтернативы не дублирует роль минимальной цены.

Weighted score и сумма `Rank_R + Rank_F` не используются.

При отсутствии PASS допускается отдельный BORDERLINE fallback, диагностически отделённый от нормального выбора.

---

## 15. Brand Tier v1

`brand_tier` — это коммерческая классификация **в рамках данного подборщика**, а не заявление об абсолютном мировом качестве брендов.

| Tier | Код | Бренды v1 | Premium Pool |
|---:|---|---|---|
| 3 | PREMIUM | Grundfos, Pedrollo | yes |
| 2 | UPPER | Sumoto / Summoto | yes |
| 1 | STANDARD | VINKO, Belamos, DYU | no |
| 0 | UNKNOWN | любой ещё не классифицированный бренд | no |

Правило допуска:

```text
in_premium_pool = (brand_tier >= 2)
```

### 15.1. Проверка Sumoto в production DB

Перед фиксацией `Sumoto = UPPER` проведена read-only проверка production-каталога:

- `manufacturer_id = 58` соответствует `Sumoto`;
- всего товаров с `manufacturer_id = 58`: `153`;
- `141` содержат `SUMOTO` в названии;
- из оставшихся `12`: `11` — отдельные погружные электродвигатели `SUMMOTO`, `1` — насос `3OPC4/19` без бренда в названии;
- отдельные электродвигатели Sumoto/Summoto в `oc_pump_selector_product` отсутствуют: `0` строк.

Следовательно, для selector candidate pool безопасно использовать:

```text
manufacturer_id = 58
→ brand_tier = UPPER
→ in_premium_pool = true
```

При этом сам Candidate Provider обязан работать только с квалифицированным selector-pool, а не со всеми товарами производителя из общего каталога.

---

## 16. Premium Pool

Premium строится отдельно от общего Pareto-front:

```text
PASS
  ↓
brand_tier >= 2
  ↓
разделение по brand_tier
  ↓
tier-scoped PREMIUM ε-PARETO
  ↓
compare vs Optimal
```

### 16.1. Tier-scoped Premium ε-Pareto

Для Premium кандидаты разных `brand_tier` **не доминируют друг друга**.

Pareto строится отдельно внутри каждого tier по тому же непрерывному вектору:

```text
technical_fit   → MIN
reserve_penalty → MIN
price           → MIN
```

Это сохраняет защиту от явно доминируемых вариантов внутри одного класса, но не позволяет более дешёвому tier 2 удалить tier 3 до бизнес-ранжирования.

Цена остаётся полезной экономической координатой внутри одного tier, но не является межtierовым hard gate.

Premium не обязан иметь большие абсолютные `H_max` или `Q_max`, чем Optimal.

---

## 17. Инженерная деградация Premium относительно Optimal

Пусть:

```text
O = Optimal
P = Premium candidate
```

Определяем ухудшение:

```text
D_R = max(0, Rank_R(O) - Rank_R(P))
D_F = max(0, Rank_F(O) - Rank_F(P))
```

И улучшение:

```text
I_R = max(0, Rank_R(P) - Rank_R(O))
I_F = max(0, Rank_F(P) - Rank_F(O))
```

Engineering Gate Premium:

```text
D_R <= 1
AND
D_F <= 1
AND
D_R + D_F <= 1
```

Смысл: Premium может уступить Optimal максимум по одному инженерному измерению и максимум на одну ступень.

BORDERLINE-кандидаты в Premium запрещены.

---

## 18. Premium Justification v2

После Engineering Gate кандидат должен иметь хотя бы одно явное основание для роли Premium.

Кандидат допускается, если выполняется хотя бы одно условие:

```text
brand_tier(P) > brand_tier(O)
OR
I_R >= 1
OR
I_F >= 1
```

Таким образом:

- более высокий брендовый класс сам по себе является допустимым основанием, если инженерный Gate пройден;
- кандидат того же брендового tier должен давать инженерное улучшение хотя бы по одной grade;
- более низкий tier не может стать Premium только за счёт цены.

Старые категории `STRONG / MEDIUM / WEAK` больше не являются gate-механизмом Premium.

---

## 19. Premium Price Policy v2

Для Premium **нет hard price gate относительно Optimal**.

Не применяются ограничения вида:

```text
Premium <= Optimal * 1.15
Premium <= Optimal * 1.35
Premium <= Optimal * 1.60
```

Высокая цена сама по себе не является причиной отклонить технически допустимый Premium-кандидат.

Цена используется только:

1. как координата Pareto внутри одного `brand_tier`;
2. как low-order tie-break при финальном сравнении кандидатов, которые уже прошли Premium Engineering Gate и Premium Justification.

Если Premium стоит в несколько раз дороже Optimal, это допустимо: роли имеют разные бизнес-смыслы.

---

## 20. Финальный выбор Premium v2

После tier-scoped Premium ε-Pareto, Engineering Gate и Premium Justification кандидаты сортируются так:

```text
1. brand_tier
      MAX

2. improvement_total = I_R + I_F
      MAX

3. degradation_total = D_R + D_F
      MIN

4. reserve_grade
      MAX

5. fit_grade
      MAX

6. price
      MIN

7. product_id
      ASC
```

Для Premium `brand_tier DESC` сознательно является первым business factor.

Это не распространяется на Best Price или Optimal и поэтому не возвращает старую глобальную модель `brand_priority`.

Следствие:

- tier 3 имеет приоритет над tier 2, если оба кандидата прошли Engineering Gate и Premium Justification;
- tier 2 остаётся допустимым Premium, когда подходящего tier 3 нет;
- цена не может автоматически вытеснить качественный tier 3 кандидатом tier 2.

### 20.1. Контрольный реальный сценарий

Для локального сценария:

```text
H_req = 73 м
Q_req = 40 л/мин
voltage = 220 В
casing = 125 мм
```

Optimal:

```text
Belamos TF-100/5
brand_tier = 1
reserve_grade = 2
fit_grade = 3
price = 22370
```

По Premium v2 ожидаемый кандидат:

```text
Grundfos SQ 3-80
brand_tier = 3
reserve_grade = 2
fit_grade = 3
price = 103000
```

Он не хуже Optimal по дискретным инженерным grades, относится к более высокому tier и не должен отбрасываться из-за разницы в цене.

---

## 21. Диагностируемость

Минимальная диагностическая запись ranking v2:

```text
product_id
hydraulic_model
confidence
hydraulic_gate
q_rel
h_rel
H_est
reserve_rel
technical_fit
reserve_penalty
reserve_grade
fit_grade
brand_tier
in_premium_pool
pareto_status
pareto_dominator_product_id
D_R
D_F
I_R
I_F
improvement_total
degradation_total
premium_justification
role_candidate_status
role_rejection_reason
final_role
```

`price_delta` может сохраняться как диагностическая метрика, но больше не участвует в Premium admission gate.

Диагностика является частью архитектуры, а не временным debug-механизмом.

---

## 22. Зафиксированные defaults v2

```text
hydraulic_model           = endpoint_parabolic_estimate
confidence                = approximate

q_target_min              = 0.35
q_target_max              = 0.65
h_target_min              = 0.45
h_target_max              = 0.75

hydraulic_fail_threshold  = -0.10
borderline range          = [-0.10, 0)

reserve_penalty ideal     = [0.10, 0.25]
low_reserve_multiplier    = 2.0

ε_price                   = 0.02
general_pareto_price_eps  = grade-aware
same_grades_price_epsilon = disabled

optimal_max_price_factor  = 1.80
optimal_exclude_best_price_if_alternative = true
premium_pool_min_tier     = 2
premium_price_hard_gate   = none
premium_cross_tier_pareto = disabled
```

Grades:

```text
Reserve:
IDEAL       0.10..0.20
GOOD        0.05..<0.10 OR >0.20..0.35
ACCEPTABLE  0..<0.05 OR >0.35..0.50
POOR        >0.50

Technical fit:
IDEAL       = 0
GOOD        >0..0.10
ACCEPTABLE  >0.10..0.22
POOR        >0.22
```

Brand tiers v1:

```text
3 PREMIUM  → Grundfos, Pedrollo
2 UPPER    → Sumoto / Summoto
1 STANDARD → VINKO, Belamos, DYU
0 UNKNOWN  → any unclassified brand
```

---

## 23. Параметры, требующие regression-калибровки

Архитектурно зафиксированы, но требуют проверки на полном candidate pool:

1. `ε_price = 2%` при grade-aware General Pareto;
2. hydraulic `FAIL/BORDERLINE` threshold `-10%`;
3. `Optimal <= Best Price * 1.80` на полном наборе сценариев;
4. исключение Best Price из Optimal при наличии допустимой альтернативы;
5. распределение Premium по реальным пользовательским сценариям после удаления price hard gate;
6. порядок `brand_tier → improvement → degradation → grades → price` для Premium;
7. необходимость отдельного low-order `brand_factor` для Optimal;
8. будущая стратегия перехода на manufacturer Q-H curves.

---

## 24. Инварианты Ranking v2

- Best Price, Optimal и Premium имеют разные бизнес-смыслы;
- Best Price не зависит от Optimal;
- Optimal ограничен ценовым коридором относительно Best Price;
- если после Optimal price gate существует другой допустимый PASS-кандидат, Best Price исключается из Optimal candidate pool до General Pareto;
- если другой допустимой альтернативы нет, Best Price может одновременно быть Optimal;
- общий Pareto для Optimal не содержит brand как координату;
- ценовая ε-поблажка в General Pareto применяется только при улучшении хотя бы одного discrete engineering grade;
- при одинаковых `reserve_grade/fit_grade` более дорогой кандидат не может доминировать более дешёвый только за счёт лучшего `technical_fit` или `reserve_penalty`;
- Premium имеет отдельный candidate pool;
- Premium Pool допускает только PASS и `brand_tier >= 2`;
- Premium не обязан быть мощнее Optimal по `H_max/Q_max`;
- Premium не может оправдывать двойную инженерную деградацию ради бренда;
- Premium не имеет hard price gate относительно Optimal;
- кандидаты разных Premium brand tier не Pareto-доминируют друг друга;
- после инженерного допуска `brand_tier` является первым business factor финального Premium ranking;
- цена в Premium является low-order factor, а не admission gate;
- BORDERLINE не смешивается с PASS в обычном ranking;
- weighted scoring не является основой выбора Optimal;
- approximate hydraulic model всегда явно маркируется как approximate;
- каждый финальный выбор должен быть диагностируемым;
- переход на реальные Q-H кривые не должен требовать смены архитектуры `Gates → Pareto → Roles`.

---

## 25. Состояние реализации

К моменту этой редакции под PHP 5.6 отдельно реализованы и протестированы:

```text
Hydraulic layer
Pareto layer
Ranking layer
Optimal price gate (Best Price * 1.80)
Premium v2 policy without price hard gate
Grade-aware General Pareto
```

Grade-aware General Pareto подтверждён unit-тестами и integration pipeline test.

Правило исключения Best Price из Optimal при наличии допустимой альтернативы, зафиксированное разделом 14.1, **ещё не реализовано** в runtime-коде. Его реализация должна быть отдельным ограниченным изменением с unit/regression-тестами перед продолжением scenario regression.
