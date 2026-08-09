# Ranking v3 Working Specification

## 1. Статус документа

Статус: **design specification / not implemented**.

Документ описывает новую целевую архитектуру назначения ролей подборщика: **Sequential Role Assignment / Exclusion Pool**.

- Ranking v2 остаётся отдельной исторически точной спецификацией существующей Pareto-based архитектуры и этим документом не переписывается.
- На момент создания V3 runtime по-прежнему работает по логике V2.
- Этот документ сам по себе не меняет production-поведение, PHP-код, cache, SQL или тесты.

---

## 2. Причина появления V3

Практическое тестирование Ranking v2 показало, что он слишком часто выдаёт меньше трёх карточек даже при большом candidate pool. Пользователь вручную проверил около 15 сценариев; приблизительно в 40–50 % случаев карточек было меньше трёх, хотя cache содержит сотни eligible насосов и несколько брендов.

Причины в архитектуре V2:

- Best Price и Optimal могут совпадать, после чего duplicate roles схлопываются;
- Premium может исчезать после цепочки gates и Pareto;
- Alternative fallback отсутствует;
- количество карточек становится побочным результатом математического отсечения.

V3 меняет сам принцип назначения ролей.

---

## 3. Главная архитектура

```text
CANDIDATE POOL
      ↓
HYDRAULIC EVALUATION
      ↓
1. BEST PRICE
      ↓
add product_id to Exclusion Pool
      ↓
2. OPTIMAL
      ↓
add product_id to Exclusion Pool
      ↓
3. PREMIUM
      ↓
if Premium not found
      ↓
ALTERNATIVE
```

Роли назначаются последовательно, а уже назначенный товар исключается из последующих ролей.

`Exclusion Pool` содержит `product_id`, уже занявшие карточки.

---

## 4. Бизнес-цель количества карточек

При наличии достаточного количества достойных кандидатов selector должен активно формировать три различные осмысленные карточки.

Нормальный invariant при наличии минимум трёх достойных кандидатов:

```text
BestPrice.product_id != Optimal.product_id
BestPrice.product_id != Card3.product_id
Optimal.product_id != Card3.product_id
```

Не следует искусственно ослаблять технические критерии ради третьей карточки или использовать плохой насос только для заполнения UI.

Регулярная выдача 1–2 карточек при большом многобрендовом candidate pool — диагностический сигнал, а не желательное нормальное поведение.

---

## 5. Сохраняемая hydraulic-модель

V3 пока сохраняет approximate hydraulic model из V2. Доступны только предельные характеристики:

```text
H_max
Q_max
```

Расчёты:

```text
q_rel = Q_req / Q_max
h_rel = H_req / H_max
H_est = H_max * (1 - q_rel^2)
reserve_rel = (H_est - H_req) / H_req
```

Hydraulic gate:

```text
reserve_rel < -0.10        FAIL
-0.10 <= reserve_rel < 0   BORDERLINE
reserve_rel >= 0           PASS
```

Формула является approximate-моделью, а не заводской Q-H curve. Hydraulic provider позже может быть заменён на реальные кривые без изменения Sequential Assignment architecture.

---

## 6. Target Box

```text
q_rel ∈ [0.35, 0.65]
h_rel ∈ [0.45, 0.75]
```

Расстояние до зоны:

```text
Δq =
    0.35 - q_rel, если q_rel < 0.35
    q_rel - 0.65, если q_rel > 0.65
    0, иначе

Δh =
    0.45 - h_rel, если h_rel < 0.45
    h_rel - 0.75, если h_rel > 0.75
    0, иначе

d_zone = sqrt(Δq^2 + Δh^2)
```

## 7. Fit Grade

| Grade | Rank | Условие |
| --- | ---: | --- |
| IDEAL | 3 | `d_zone = 0` |
| GOOD | 2 | `0 < d_zone <= 0.10` |
| ACCEPTABLE | 1 | `0.10 < d_zone <= 0.22` |
| POOR | 0 | `d_zone > 0.22` |

## 8. Reserve Grade

| Grade | Rank | reserve_rel |
| --- | ---: | --- |
| IDEAL | 3 | `0.10 <= R <= 0.20` |
| GOOD | 2 | `0.05 <= R < 0.10` или `0.20 < R <= 0.35` |
| ACCEPTABLE | 1 | `0 <= R < 0.05` или `0.35 < R <= 0.50` |
| POOR | 0 | `R > 0.50` |

Для BORDERLINE `reserve_grade = N/A`.

---

## 9. Best Price

Нормативная логика:

```text
1. Если существует PASS:
      выбрать min(price) среди PASS.

2. Если PASS отсутствует:
      выбрать min(price) среди BORDERLINE.

3. FAIL не участвует.

4. При равной цене:
      product_id ASC.
```

После назначения:

```text
Exclusion Pool += BestPrice.product_id
```

Best Price — самый дешёвый насос, который технически способен выполнить задачу. Он не обязан иметь лучший fit или идеальный reserve.

---

## 10. Optimal

Основной pool:

```text
PASS
MINUS Exclusion Pool
```

Eligibility:

```text
reserve_grade >= 1
```

То есть POOR reserve исключается.

Выбор:

```text
1. Найти максимальный fit_grade среди eligible кандидатов.
2. Оставить кандидатов только этого fit_grade.
3. Выбрать min(price).
4. Tie-break: product_id ASC.
```

После назначения:

```text
Exclusion Pool += Optimal.product_id
```

Optimal — самый дешёвый насос среди кандидатов с лучшей доступной рабочей точкой и без плохого запаса.

V3 Optimal **не использует** Pareto, ε-Pareto, `reserve_penalty` как ranking vector, `composite_score`, weighted score, `Optimal <= BestPrice * 1.80` или brand factor.

```text
Optimal BORDERLINE fallback = unresolved
```

---

## 11. Brand Tier

| Tier | Название | Бренды |
| ---: | --- | --- |
| 3 | PREMIUM | Grundfos, Pedrollo |
| 2 | UPPER | Sumoto / Summoto |
| 1 | STANDARD | VINKO, Belamos, DYU |
| 0 | UNKNOWN | остальные |

Это внутренняя коммерческая классификация selector, а не универсальное утверждение о качестве брендов.

Для роли Premium:

```text
brand_tier == 3
```

Tier 2 больше не входит в Premium role pool; он может участвовать в Best Price, Optimal или Alternative.

---

## 12. Premium

Premium ищется только после назначения Best Price и Optimal.

Pool:

```text
PASS
MINUS Exclusion Pool
brand_tier == 3
fit_grade >= 2
reserve_grade >= 2
```

Выбор:

```text
1. min(price)
2. product_id ASC
```

Premium — самый доступный настоящий премиальный насос, который хорошо подходит технически.

Premium не использует Pareto, tier-scoped Pareto, engineering comparison vs Optimal, degradation gate, price multiplier или обязательное improvement относительно Optimal.

```text
if Premium found: Card3 = Premium
if Premium not found: goto Alternative
```

---

## 13. Alternative

Alternative используется **только как fallback третьей карточки**, если Premium не найден.

Pool:

```text
PASS
MINUS Exclusion Pool
brand != Optimal.brand
fit_grade >= 2
reserve_grade >= 1
```

Alternative — насос другого бренда, который максимально похож на Optimal по рабочей точке.

```text
dq = candidate.q_rel - optimal.q_rel
dh = candidate.h_rel - optimal.h_rel
distance_to_optimal = sqrt(dq^2 + dh^2)
```

Selection:

```text
1. min(distance_to_optimal)
2. min(price)
3. product_id ASC
```

`distance_to_optimal` не является composite score: он сравнивает только две hydraulic coordinates. Цена не смешивается с distance, а brand tier не входит в формулу.

```text
same-brand Alternative fallback = unresolved
```

---

## 14. Почему Pareto не используется в V3 assignment

Pareto может быть математически корректным способом сокращения множества, но для текущей бизнес-задачи он оказался слишком сильным скрытым фильтром перед назначением ролей.

- кандидат может исчезнуть до role assignment;
- после этого selector уже не способен сформировать три разные роли;
- небольшие изменения epsilon, grades или порядка сравнения сильно меняют UI-выдачу;
- бизнес-смысл карточек становится трудно объяснить.

Поэтому V3 не использует Pareto для определения состава role candidate pool. Если Pareto когда-либо будет возвращён, он не должен нарушать Sequential Assignment invariants или молча удалять кандидатов, необходимых для ролей.

---

## 15. Реальные design regression scenarios

Эти сценарии использовались для ручного проектирования новой бизнес-логики. Они являются design regression examples, а не доказательством корректности на всём каталоге.

### Scenario 1

```text
H_req = 65
Q_req = 30
Voltage = 220
Casing = unknown

Best Price: Belamos 3TF-90/6, price = 16380
Optimal: Belamos TF-100/5, price = 22370
Premium: Pedrollo 4SR 1,5m/25 PD, price = 54440
```

Best Price — cheapest PASS; Optimal выигрывает за счёт лучшего доступного `fit_grade`; Premium — самый дешёвый tier-3 кандидат, прошедший `fit >= 2` и `reserve >= 2`.

### Scenario 2

```text
H_req = 73
Q_req = 40
Voltage = 220
Casing = 125

Best Price: Belamos 3TF-90/6, price = 16380
Optimal: Belamos TF-100/5, price = 22370
Premium: Grundfos SQ 3-80, price = 103000
```

Более дешёвые premium-кандидаты не проходят необходимые engineering grades.

### Scenario 3

```text
H_req = 78
Q_req = 65
Voltage = 220
Casing = 125

Best Price: SUMOTO OPC 4SRM4/26, price = 87900
Optimal: SUMOTO 4SRM6/20, price = 95500
Premium: not found
Alternative: VINKO 4STM6/20, price = 95500
```

VINKO выбран как Alternative, потому что его `q_rel/h_rel` практически совпадают с Optimal SUMOTO, при этом это другой бренд.

---

## 16. Edge cases

```text
>= 3 достойных кандидатов
  целевой результат = 3 разных product_id

2 достойных кандидата
  максимум 2 карточки

1 достойный кандидат
  1 карточка; точное название роли пока unresolved

0 кандидатов
  0 карточек / стандартное сообщение selector
```

Quality gates не ослабляются только для заполнения UI.

---

## 17. Diagnostics

Нормативные diagnostic fields V3:

```text
product_id
hydraulic_gate
q_rel
h_rel
reserve_rel
reserve_grade
technical_fit
fit_grade
brand_tier

assignment_stage
excluded_by_role
role_candidate_status
role_rejection_reason

distance_to_optimal

final_role
```

Для rejected кандидата по возможности фиксируется понятная причина, например:

```text
already_assigned
not_pass
poor_reserve
fit_below_premium_min
reserve_below_premium_min
not_tier3
same_brand_as_optimal
```

---

## 18. Нормативные invariants V3

1. Role assignment выполняется последовательно.
2. Назначенный `product_id` попадает в Exclusion Pool.
3. Один `product_id` не занимает две карточки при наличии альтернатив.
4. Best Price сначала использует PASS.
5. Optimal выбирает лучший available `fit_grade`, затем price.
6. Optimal требует `reserve_grade >= 1`.
7. Premium допускает только `brand_tier == 3`.
8. Premium требует `fit_grade >= 2`.
9. Premium требует `reserve_grade >= 2`.
10. Premium выбирается по price.
11. Alternative используется только при отсутствии Premium.
12. Alternative должен быть другого бренда относительно Optimal.
13. Alternative выбирается по hydraulic distance к Optimal.
14. Pareto не участвует в role assignment V3.
15. Weighted/composite scoring не участвует в role assignment V3.

---

## 19. Состояние реализации

### Existing runtime

На момент создания документа production/runtime остаётся на Ranking v2 architecture.

### V3 specification

Этот документ описывает новую целевую Sequential Assignment / Exclusion Pool architecture.

### Not implemented yet

- Sequential Assignment Manager;
- Exclusion Pool;
- новый Best Price/Optimal orchestration;
- новый Optimal selection;
- tier-3-only Premium;
- Alternative fallback;
- `distance_to_optimal`;
- V3 diagnostics;
- V3 unit/integration tests;
- unresolved fallback policies.

---

## 20. Open questions

Не решаются этим документом без отдельного подтверждения:

1. Optimal BORDERLINE fallback.
2. Alternative fallback, если нет другого бренда.
3. Точное поведение при одном кандидате.
4. Нужно ли сохранять `reserve_penalty` в runtime diagnostics V3.
5. Нужны ли дополнительные regression scenarios перед реализацией.

---

Last updated: 2026-08-09 16:34:00 Europe/Kyiv
