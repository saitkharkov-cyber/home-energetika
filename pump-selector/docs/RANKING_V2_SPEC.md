# Ranking v2 Mathematical Specification

## 1. Статус документа

Документ фиксирует утверждённое математическое ядро новой логики ранжирования подборщика насосов.

Статус: **working specification v1**.

Документ является нормативной основой для реализации ranking v2, unit-тестов и regression-проверок.

На этом этапе документ сам по себе **не изменяет** production-выдачу, cache Builder или текущий selector pipeline.

---

## 2. Цель

Новая модель сохраняет три независимых бизнес-роли:

- **Best Price** — самый дешёвый уверенно допустимый вариант;
- **Optimal** — лучший инженерно-экономический баланс;
- **Premium** — оправданный качественный upgrade относительно Optimal.

Модель должна масштабироваться на многобрендовый каталог без жёсткой линейной лестницы `brand_priority`, при которой бренд автоматически побеждает технически более рациональный вариант.

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
   │     ├── GENERAL ε-PARETO
   │     │       ↓
   │     │     OPTIMAL
   │     │
   │     └── PREMIUM POOL
   │             ↓
   │        PREMIUM ε-PARETO
   │             ↓
   │        compare vs Optimal
   │             ↓
   │        upgrade strength
   │             ↓
   │        price delta gate
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
- **Brand tier** — допуск в Premium Pool и low-order business factor;
- бренд не входит в общий физико-экономический Pareto-вектор.

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

### 9.1. ε-доминирование по цене

Default v1:

```text
ε_price = 0.02
```

`A` ε-доминирует `B`, если:

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

Для полностью одинаковой инженерии:

```text
IF
A.technical_fit = B.technical_fit
AND
A.reserve_penalty = B.reserve_penalty
AND
A.price < B.price
THEN
A dominates B
```

Инженерные epsilon в v1 не вводятся.

---

## 10. Непрерывные метрики и дискретные grades

- `technical_fit`, `reserve_penalty`, `price` — непрерывные величины для Pareto;
- `reserve_grade`, `fit_grade` — дискретные ранги для Role Ranking.

Pareto и Role Ranking сознательно используют разные представления одной и той же инженерной картины.

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
GENERAL ε-PARETO
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
PREMIUM ε-PARETO
  ↓
compare vs Optimal
```

Это предотвращает ситуацию, когда дешёвый standard-бренд уничтожает качественный premium-кандидат только ценой ещё до Role Ranking.

Premium не обязан иметь большие абсолютные `H_max` или `Q_max`, чем Optimal.

---

## 17. Инженерная деградация Premium относительно Optimal

Пусть:

```text
O = Optimal
P = Premium candidate
```

Определяем только ухудшение:

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

## 18. Premium Upgrade Strength v1

### 18.1. STRONG

```text
(I_R + I_F) >= 1
AND
(D_R + D_F) = 0
```

Premium улучшает хотя бы одну инженерную зону и ничего не ухудшает.

Максимальная переплата v1:

```text
price_delta <= 0.60
```

### 18.2. MEDIUM

Вариант A — инженерный trade-off:

```text
(I_R + I_F) >= 1
AND
(D_R + D_F) = 1
```

Вариант B — инженерия та же, но brand tier выше:

```text
I_R + I_F = 0
AND
D_R + D_F = 0
AND
brand_tier(P) > brand_tier(O)
```

Максимальная переплата v1:

```text
price_delta <= 0.35
```

### 18.3. WEAK

```text
I_R + I_F = 0
AND
D_R + D_F = 1
AND
brand_tier(P) > brand_tier(O)
```

Максимальная переплата v1:

```text
price_delta <= 0.15
```

### 18.4. NONE / REJECT

Кандидат не является оправданным Premium upgrade, если:

```text
нет инженерного улучшения
AND
brand_tier(P) <= brand_tier(O)
```

или если превышен допустимый price threshold для вычисленного `upgrade_strength`.

---

## 19. Premium Price Delta

```text
price_delta = (Price_Premium - Price_Optimal) / Price_Optimal
```

Defaults v1:

```text
STRONG  → max +60%
MEDIUM  → max +35%
WEAK    → max +15%
NONE    → REJECT
```

Если Premium дешевле Optimal, `price_delta < 0`, и ценовой Gate естественно проходит.

Пороги `60/35/15` являются **defaults v1** и подлежат калибровке на полном многобрендовом candidate pool. Их изменение не должно требовать изменения архитектуры.

---

## 20. Финальный выбор Premium

После Premium ε-Pareto, Engineering Gate и Price Gate кандидаты сортируются так:

```text
1. upgrade_strength
      STRONG > MEDIUM > WEAK

2. improvement_total = I_R + I_F
      MAX

3. degradation_total = D_R + D_F
      MIN

4. price_delta
      MIN

5. brand_tier
      MAX

6. product_id
      ASC
```

`brand_tier DESC` не ставится первым, чтобы не восстановить скрыто старую модель `brand_priority`.

Например, tier 3 не имеет автоматического преимущества над tier 2, если tier 2 даёт более сильный и более рациональный upgrade.

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
upgrade_strength
price_delta
role_candidate_status
role_rejection_reason
final_role
```

Диагностика является частью архитектуры, а не временным debug-механизмом.

---

## 22. Зафиксированные defaults v1

```text
hydraulic_model          = endpoint_parabolic_estimate
confidence               = approximate

q_target_min             = 0.35
q_target_max             = 0.65
h_target_min             = 0.45
h_target_max             = 0.75

hydraulic_fail_threshold = -0.10
borderline range         = [-0.10, 0)

reserve_penalty ideal    = [0.10, 0.25]
low_reserve_multiplier   = 2.0

ε_price                  = 0.02

premium_pool_min_tier    = 2
premium_strong_max_delta = 0.60
premium_medium_max_delta = 0.35
premium_weak_max_delta   = 0.15
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

1. `ε_price = 2%`;
2. hydraulic `FAIL/BORDERLINE` threshold `-10%`;
3. Premium price thresholds `60/35/15`;
4. распределение Premium по реальным пользовательским сценариям;
5. необходимость отдельного low-order `brand_factor` для Optimal;
6. будущая стратегия перехода на manufacturer Q-H curves.

---

## 24. Инварианты Ranking v2

- Best Price, Optimal и Premium имеют разные бизнес-смыслы;
- Best Price не зависит от Optimal;
- общий Pareto не содержит brand как координату;
- Premium имеет отдельный candidate pool;
- Premium Pool допускает только PASS и `brand_tier >= 2`;
- Premium не обязан быть мощнее Optimal по `H_max/Q_max`;
- Premium не может оправдывать двойную инженерную деградацию ради бренда;
- BORDERLINE не смешивается с PASS в обычном ranking;
- weighted scoring не является основой выбора Optimal;
- brand tier не является главным сортировщиком Premium;
- approximate hydraulic model всегда явно маркируется как approximate;
- каждый финальный выбор должен быть диагностируемым;
- переход на реальные Q-H кривые не должен требовать смены архитектуры `Gates → Pareto → Roles`.

---

## 25. Состояние реализации

К моменту этой редакции отдельно реализованы и протестированы под PHP 5.6:

```text
Hydraulic layer
Pareto layer
Ranking layer: Best Price / Optimal / Premium engineering eligibility
```

Фактический локальный regression status перед этой редакцией:

```text
Hydraulic: 10 tests / 55 assertions — PASS
Pareto:    14 tests / 24 assertions — PASS
Ranking:   19 tests / 20 assertions — PASS

Total:     43 tests / 99 assertions — PASS
```

Финальный `selectPremium()` должен реализовываться только по правилам разделов 15–20 настоящей спецификации и затем получать отдельный unit/regression slice перед интеграцией в production selector pipeline.
