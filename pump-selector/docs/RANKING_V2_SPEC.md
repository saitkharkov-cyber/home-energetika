# Ranking v2 Mathematical Specification

## 1. Статус документа

Документ фиксирует утверждённое математическое ядро новой логики ранжирования подборщика насосов.

Статус: **working specification v1**.

Документ является нормативной основой для последующего проектирования DTO, интерфейсов, unit-тестов и реализации ranking v2.

На этом этапе документ **не изменяет** production-код, cache Builder или текущую выдачу подборщика.

---

## 2. Цель

Новая модель должна сохранять три независимых бизнес-роли:

- **Best Price** — самый дешёвый уверенно допустимый вариант;
- **Optimal** — лучший инженерно-экономический баланс;
- **Premium** — оправданный качественный upgrade относительно Optimal.

Модель должна масштабироваться на многобрендовый каталог без жёсткой лестницы `brand_priority`, при которой бренд автоматически побеждает технически более рациональный вариант.

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

Ключевое разделение ответственности:

- **Gates** отвечают за физическую допустимость;
- **ε-Pareto** очищает физику и экономику от доминируемых вариантов;
- **Role Ranking** назначает бизнес-роли;
- **Brand tier** участвует в бизнес-логике Premium, но не загрязняет общий физико-экономический Pareto-front.

---

## 4. Доступные данные и уровень достоверности

На текущем этапе для каждого насоса доступны только предельные характеристики:

- `H_max` — максимальный напор;
- `Q_max` — максимальная производительность.

Реальная заводская Q-H кривая и BEP отсутствуют.

Поэтому применяется приближённая модель:

```text
hydraulic_model = endpoint_parabolic_estimate
confidence = approximate
```

Архитектура должна позволять в будущем заменить этот провайдер на реальные заводские кривые без изменения слоёв `Gates → Pareto → Roles`.

---

## 5. Аппроксимация Q-H характеристики

Используется параболическая оценка:

```text
q_rel = Q_req / Q_max

H_est(Q_req) = H_max * (1 - q_rel^2)
```

Где:

- `Q_req` — требуемый расход;
- `H_req` — требуемый напор;
- `H_est` — оценочный напор насоса при требуемом расходе.

Эта формула используется как ранжирующая аппроксимация, а не как лабораторно точная характеристика конкретной модели.

---

## 6. Hydraulic Gate

Вычисляется относительный гидравлический запас:

```text
reserve_rel = (H_est - H_req) / H_req
```

Default v1:

```text
reserve_rel < -0.10       → FAIL
-0.10 <= reserve_rel < 0  → BORDERLINE
reserve_rel >= 0          → PASS
```

Смысл:

- **FAIL** — модель показывает явную неспособность выполнить задачу;
- **BORDERLINE** — расчётный дефицит находится в пределах допуска на неточность приближённой Q-H модели;
- **PASS** — расчётная точка не имеет дефицита напора.

### 6.1. Правила использования BORDERLINE

- **Best Price:** сначала выбирается только среди PASS. BORDERLINE используется только как fallback, если PASS-кандидатов нет.
- **Optimal:** сначала выбирается только среди PASS. BORDERLINE используется только как отдельный fallback, если PASS-кандидатов нет.
- **Premium:** BORDERLINE запрещён.
- **FAIL:** не участвует ни в одной роли.

Порог `10%` является default v1 и подлежит regression-проверке на полном candidate pool.

---

## 7. Target Box и technical_fit

Для приближённой модели задаётся конфигурируемая целевая рабочая область:

```text
q_rel = Q_req / Q_max
h_rel = H_req / H_max

default target box:
q_rel ∈ [0.35, 0.65]
h_rel ∈ [0.45, 0.75]
```

Это **условная целевая рабочая область для approximate-модели**, а не заявленная заводом POR/BEP конкретного насоса.

### 7.1. Расстояние до целевой зоны

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

Если расчётная точка находится внутри Target Box, `technical_fit = 0`.

---

## 8. Целевой гидравлический запас и reserve_penalty

Сырой `reserve_rel` нельзя минимизировать к нулю: слишком малый положительный запас также нежелателен.

Для Pareto используется непрерывная штрафная функция с комфортным плато:

```text
reserve_penalty(R) =
    2 * (0.10 - R),  если R < 0.10
    0,                если 0.10 <= R <= 0.25
    R - 0.25,         если R > 0.25
```

Где `R = reserve_rel`.

Default v1:

```text
comfort reserve = +10% ... +25%
low-reserve multiplier = 2.0
```

Смысл:

- запас ниже +10% штрафуется быстрее;
- +10...+25% — нулевой штраф;
- выше +25% — мягко растущий штраф переподбора.

Направление оптимизации:

```text
reserve_penalty → MIN
```

---

## 9. General ε-Pareto

Общий Pareto-вектор строится только по физике и экономике:

```text
technical_fit     → MIN
reserve_penalty   → MIN
price             → MIN
```

**Brand / brand tier в общий Pareto-вектор не входит.**

### 9.1. ε-доминирование по цене

Default v1:

```text
ε_price = 0.02   // 2%
```

Кандидат `A` ε-доминирует `B`, если:

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

Дополнительное правило для полностью одинаковой инженерии:

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

Таким образом, допуск +2% по цене разрешён только ради объективного инженерного улучшения.

Инженерные epsilon в v1 не вводятся.

`ε_price = 2%` является default v1 и подлежит regression-проверке на полном candidate pool.

---

## 10. Непрерывные метрики и дискретные grades

Принципиальное разделение:

- `technical_fit`, `reserve_penalty`, `price` — непрерывные величины для Pareto;
- `reserve_grade`, `fit_grade` — дискретные ранги для Role Ranking.

Pareto и Role Ranking не обязаны использовать одинаковые границы.

---

## 11. Reserve Grade

Grade применяется только к **PASS-кандидатам**.

| Grade | Rank | reserve_rel |
|---|---:|---|
| IDEAL | 3 | `0.10 <= R <= 0.20` |
| GOOD | 2 | `0.05 <= R < 0.10` или `0.20 < R <= 0.35` |
| ACCEPTABLE | 1 | `0 <= R < 0.05` или `0.35 < R <= 0.50` |
| POOR | 0 | `R > 0.50` |

Для BORDERLINE `reserve_grade` не присваивается (`N/A`).

Важно: в Pareto диапазон `+10...+25%` имеет `reserve_penalty = 0`, тогда как Role Ranking сознательно различает:

- `+10...+20%` → IDEAL;
- `+20...+25%` → GOOD.

---

## 12. Technical Fit Grade

| Grade | Rank | technical_fit / d_zone |
|---|---:|---|
| IDEAL | 3 | `d_zone = 0` |
| GOOD | 2 | `0 < d_zone <= 0.10` |
| ACCEPTABLE | 1 | `0.10 < d_zone <= 0.22` |
| POOR | 0 | `d_zone > 0.22` |

`IDEAL` означает только, что расчётная точка находится внутри условной Target Box approximate-модели.

---

## 13. Best Price

Best Price независим от Optimal и Premium.

Правило v1:

```text
1. взять PASS-кандидатов;
2. выбрать min(price);
3. если PASS нет — отдельный fallback среди BORDERLINE по min(price);
4. FAIL никогда не участвует.
```

Бренд, Pareto-front и Premium-tier не участвуют в выборе Best Price.

Бизнес-смысл:

> минимальная стоимость среди уверенно допустимых решений; BORDERLINE — только аварийный fallback из-за приближённости гидравлической модели.

---

## 14. Optimal

Основной Optimal выбирается только среди PASS-кандидатов.

Последовательность:

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
product_id как стабильный последний tie-break
```

Общий weighted score не используется.

Сумма `Rank_R + Rank_F` также не используется, чтобы не допускать скрытой компенсации между разными видами инженерной деградации.

Если PASS-кандидатов нет, допускается отдельный BORDERLINE fallback для Optimal. Он должен быть явно диагностируемым и не смешиваться с обычным PASS-ranking.

---

## 15. Premium Pool

Premium не строится из общего Pareto-front, иначе дешёвый standard-бренд может уничтожить качественный premium-кандидат только ценой ещё до Role Ranking.

Premium имеет отдельный candidate pool:

```text
PASS
  ↓
разрешённые brand tiers
  ↓
PREMIUM ε-PARETO
  ↓
сравнение с выбранным Optimal
```

Конкретное распределение брендов по `brand_tier` фиксируется отдельно и не является частью данной математической спецификации.

Premium не обязан иметь большие абсолютные `H_max` или `Q_max`, чем Optimal.

Premium должен быть качественным и экономически оправданным upgrade, а не «самым мощным» или «самым дорогим» насосом.

---

## 16. Допустимая инженерная деградация Premium относительно Optimal

Пусть:

```text
O = Optimal
P = Premium candidate
```

Определяем только ухудшение, не штрафуя Premium за улучшение:

```text
D_R = max(0, Rank_R(O) - Rank_R(P))
D_F = max(0, Rank_F(O) - Rank_F(P))
```

Premium инженерно допустим относительно Optimal, если одновременно:

```text
D_R <= 1
AND
D_F <= 1
AND
D_R + D_F <= 1
```

Смысл:

> Premium может уступить Optimal максимум по одному инженерному измерению и максимум на одну ступень.

Примеры:

| Optimal Reserve/Fit | Premium Reserve/Fit | D_R | D_F | Result |
|---|---|---:|---:|---|
| IDEAL / IDEAL | GOOD / IDEAL | 1 | 0 | PASS |
| IDEAL / IDEAL | IDEAL / GOOD | 0 | 1 | PASS |
| IDEAL / IDEAL | GOOD / GOOD | 1 | 1 | FAIL |
| GOOD / GOOD | IDEAL / IDEAL | 0 | 0 | PASS |
| GOOD / IDEAL | IDEAL / GOOD | 0 | 1 | PASS |

BORDERLINE-кандидаты в Premium запрещены.

---

## 17. Premium Price / Upgrade Strength

Переплата относительно Optimal определяется как:

```text
price_delta = (Price_premium - Price_optimal) / Price_optimal
```

Архитектурно Premium должен проверяться на разумность переплаты относительно силы upgrade.

Рабочие defaults, обсуждавшиеся при проектировании:

```text
WEAK upgrade   → ориентир до +15%
MEDIUM upgrade → ориентир до +35%
STRONG upgrade → ориентир до +60%
```

**Эти границы пока не считаются окончательно откалиброванными нормативными порогами.**

До implementation freeze требуется отдельная проверка на полном многобрендовом candidate pool.

---

## 18. Brand logic

Старая линейная модель вида:

```text
Pedrollo = 10
Sumoto = 8
Belamos = 5
...
```

не должна использоваться как главный сортировщик Premium.

Новая архитектура предполагает разделение как минимум на:

```text
brand_tier
brand_factor / brand_score (только low-order tie-break при необходимости)
```

`brand_tier` отвечает за допустимость участия в Premium Pool.

Brand factor не должен автоматически вытеснять существенно лучший инженерный вариант.

Конкретная классификация Pedrollo / Sumoto / Belamos / VINKO / Grundfos / DYU фиксируется отдельным бизнес-решением после regression-тестов.

---

## 19. Диагностируемость

Ranking v2 должен позволять объяснить результат для любого товара и сценария.

Минимально полезная диагностическая запись:

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
pareto_status
pareto_dominator_product_id (если есть)
role_candidate_status
role_rejection_reason
final_role
```

Пример логической цепочки:

```text
Gate: PASS
→ Hydraulic model: endpoint_parabolic_estimate / approximate
→ ε-Pareto: RETAINED
→ Reserve grade: IDEAL
→ Fit grade: GOOD
→ Optimal ranking: candidate
→ Final: lost by price to product X
```

Диагностика является частью архитектуры, а не временным debug-механизмом.

---

## 20. Зафиксированные defaults v1

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

---

## 21. Что ещё не зафиксировано окончательно

До implementation freeze остаются отдельные решения:

1. точная классификация брендов по `brand_tier`;
2. точный low-order brand tie-break для Optimal, если он вообще понадобится;
3. окончательная калибровка Premium price thresholds / upgrade strength;
4. regression-проверка `ε_price = 2%` на полном candidate pool;
5. regression-проверка hydraulic `FAIL/BORDERLINE` порога `-10%` на полном candidate pool;
6. стратегия перехода от approximate Q-H к manufacturer curves в будущем.

Эти открытые параметры не меняют архитектуру математического ядра.

---

## 22. Инварианты Ranking v2

Независимо от конкретной реализации должны сохраняться следующие правила:

- Best Price, Optimal и Premium имеют разные бизнес-смыслы;
- Best Price не зависит от Optimal;
- общий Pareto не содержит brand как координату;
- Premium имеет отдельный premium candidate pool;
- Premium не обязан быть мощнее Optimal по `H_max/Q_max`;
- Premium не может оправдывать двойную инженерную деградацию ради бренда;
- BORDERLINE не смешивается с PASS в обычном ranking;
- weighted scoring не является основой выбора Optimal;
- approximate hydraulic model всегда явно маркируется как approximate;
- переход на реальные Q-H кривые не должен требовать смены архитектуры `Gates → Pareto → Roles`;
- каждый финальный выбор должен быть диагностируемым и объяснимым.

---

## 23. Следующий этап

После фиксации этого документа следующий этап — **не изменение production**, а проектирование реализации ranking v2:

- DTO / структуры расчётных данных;
- функции hydraulic model;
- Gates;
- ε-Pareto;
- grade calculators;
- role selectors;
- diagnostic trace;
- unit-тесты и regression-сценарии.

Только после прохождения тестов на полном многобрендовом candidate pool допускается замена текущего production ranking.