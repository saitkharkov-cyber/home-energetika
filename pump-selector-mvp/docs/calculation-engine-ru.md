# Расчетное ядро MVP подбора скважинного насоса

Документ описывает расчетное ядро MVP для предварительного подбора скважинного насоса. Это не профессиональный инженерный расчет, а упрощенная модель для первичного подбора. Результаты должны формулироваться осторожно: `предварительный подбор`, `обычно подходит`, `требует подтверждения специалистом`.

## 1. Назначение расчетного ядра

Расчетное ядро принимает ответы пользователя по скважине, дому и точкам водоразбора, после чего рассчитывает:

- требуемый напор `required_head_m`;
- требуемую подачу `required_flow_l_min`;
- выбранное напряжение `selected_voltage`;
- диаметр обсадной трубы для фильтрации или `null`;
- предупреждения `warnings`;
- допущения `assumptions`.

Полученные значения используются SQL-подбором насосов:

```text
max_head_m >= required_head_m
max_flow_l_min >= required_flow_l_min
pump_diameter_mm <= casing_diameter_mm, если диаметр известен
voltage = selected_voltage
is_eligible = 1
```

## 2. Входные параметры

```text
total_well_depth_m
water_level_mode: known / unknown
water_level_m
distance_to_house_m
highest_water_point_floor: 1 / 2 / 3 / custom
custom_vertical_lift_m
water_points:
  - sink
  - shower
  - toilet
  - washing_machine
  - dishwasher
  - irrigation
casing_diameter_mode: known / unknown
casing_diameter_mm
voltage_mode: 220 / 380 / unknown
```

## 3. Правила расчета H

Требуемый напор рассчитывается по формуле:

```text
H = water_level + required_pressure + vertical_lift + pipe_losses
```

Где:

- `required_pressure = 30 м`;
- `water_level` зависит от режима уровня воды;
- `vertical_lift` зависит от самой высокой точки водоразбора;
- `pipe_losses` зависят от расстояния от скважины до дома.

### Уровень воды

Если `water_level_mode = known`, используется значение:

```text
water_level = water_level_m
```

Если `water_level_mode = unknown`, используется оценочное значение:

```text
water_level = total_well_depth_m * 0.7
```

В этом случае обязательно добавляется предупреждение:

```text
Уровень воды неизвестен, поэтому расчет выполнен в оценочном режиме: рабочий уровень принят как 70% от общей глубины скважины. Результат является предварительным и требует подтверждения специалистом.
```

### Требуемое давление

Для MVP используется фиксированное значение:

```text
required_pressure = 30 м
```

Это упрощенное значение для предварительного подбора, без дополнительных инженерных коэффициентов.

### Вертикальный подъем

```text
highest_water_point_floor = 1      -> vertical_lift = 3 м
highest_water_point_floor = 2      -> vertical_lift = 6 м
highest_water_point_floor = 3      -> vertical_lift = 9 м
highest_water_point_floor = custom -> vertical_lift = custom_vertical_lift_m
```

### Потери в трубе

Для MVP используется упрощенная оценка:

```text
pipe_losses = max(distance_to_house_m * 0.1, 2)
```

Минимальное значение потерь принимается равным `2 м`.

## 4. Правила расчета Q

Требуемая подача рассчитывается как сумма выбранных точек водоразбора.

| Точка водоразбора | Расход |
|---|---:|
| `sink` | 8 л/мин |
| `shower` | 12 л/мин |
| `toilet` | 6 л/мин |
| `washing_machine` | 10 л/мин |
| `dishwasher` | 8 л/мин |
| `irrigation` | 20 л/мин |

Формула:

```text
Q = сумма расходов выбранных water_points
```

Для передачи в подбор допускается округлять итоговый расход вверх до ближайших `5 л/мин`. Это не инженерный коэффициент, а практическое округление для предварительного подбора и тестовых сценариев. Например, расчетная сумма `44 л/мин` может передаваться как `45 л/мин`.

Для MVP не применяются коэффициенты одновременности. Это сделано намеренно, чтобы расчет оставался простым и осторожным.

## 5. Правила обработки unknown

### Неизвестный уровень воды

Если `water_level_mode = unknown`:

- использовать `70%` от `total_well_depth_m`;
- добавить warning;
- добавить assumption о расчетном уровне воды.

Пример assumption:

```text
Рабочий уровень воды принят как 70% от общей глубины скважины.
```

### Неизвестный диаметр обсадной трубы

Если `casing_diameter_mode = unknown`:

- вернуть `casing_diameter_mm = null`;
- не применять фильтр `pump_diameter_mm <= casing_diameter_mm`;
- добавить warning.

Пример warning:

```text
Диаметр обсадной трубы неизвестен, поэтому фильтр по диаметру насоса не применен. Совместимость по диаметру нужно подтвердить перед покупкой.
```

### Неизвестное напряжение

Если `voltage_mode = unknown`:

- использовать `selected_voltage = 220`;
- добавить warning;
- добавить assumption о выборе 220В по умолчанию.

Пример warning:

```text
Напряжение не указано, поэтому для предварительного подбора использовано 220В. Перед покупкой нужно подтвердить доступное питание.
```

## 6. Валидация

Перед расчетом нужно проверить входные данные.

| Поле | Правило |
|---|---|
| `total_well_depth_m` | больше `0` |
| `water_level_m`, если `water_level_mode = known` | больше `0` |
| `water_level_m`, если `water_level_mode = known` | не больше `total_well_depth_m` |
| `distance_to_house_m` | `0` или больше |
| `water_points` | выбрана минимум одна точка |
| `custom_vertical_lift_m`, если `highest_water_point_floor = custom` | больше `0` |
| `casing_diameter_mm`, если `casing_diameter_mode = known` | больше `0` |
| `voltage_mode` | `220`, `380` или `unknown` |

Если валидация не пройдена, расчет не выполняется. Пользователю нужно вернуть понятные ошибки по конкретным полям.

## 7. Выходной объект

Расчетное ядро возвращает объект:

```text
required_head_m
required_flow_l_min
selected_voltage
casing_diameter_mm или null
warnings
assumptions
```

Пример:

```text
required_head_m = 85
required_flow_l_min = 45
selected_voltage = 220
casing_diameter_mm = null
warnings = [
  "Диаметр обсадной трубы неизвестен, поэтому фильтр по диаметру насоса не применен. Совместимость по диаметру нужно подтвердить перед покупкой."
]
assumptions = []
```

## 8. Примеры расчета

### Пример 1. H = 60 / Q = 45

Входные данные:

```text
total_well_depth_m = 50
water_level_mode = known
water_level_m = 25
distance_to_house_m = 20
highest_water_point_floor = 1
water_points = sink + shower + toilet + washing_machine + dishwasher
casing_diameter_mode = known
casing_diameter_mm = 100
voltage_mode = 220
```

Расчет:

```text
water_level = 25
required_pressure = 30
vertical_lift = 3
pipe_losses = max(20 * 0.1, 2) = 2
H = 25 + 30 + 3 + 2 = 60 м

Q = 8 + 12 + 6 + 10 + 8 = 44 л/мин
required_flow_l_min = 45 л/мин
```

Для подбора используется:

```text
required_head_m = 60
required_flow_l_min = 45
selected_voltage = 220
casing_diameter_mm = 100
```

### Пример 2. H = 85 / Q = 45

Входные данные:

```text
total_well_depth_m = 90
water_level_mode = known
water_level_m = 45
distance_to_house_m = 40
highest_water_point_floor = 2
water_points = sink + shower + toilet + washing_machine + dishwasher
casing_diameter_mode = unknown
voltage_mode = 220
```

Расчет:

```text
water_level = 45
required_pressure = 30
vertical_lift = 6
pipe_losses = max(40 * 0.1, 2) = 4
H = 45 + 30 + 6 + 4 = 85 м

Q = 8 + 12 + 6 + 10 + 8 = 44 л/мин
required_flow_l_min = 45 л/мин
```

Для подбора используется:

```text
required_head_m = 85
required_flow_l_min = 45
selected_voltage = 220
casing_diameter_mm = null
```

Предупреждение:

```text
Диаметр обсадной трубы неизвестен, поэтому фильтр по диаметру насоса не применен. Совместимость по диаметру нужно подтвердить перед покупкой.
```

### Пример 3. H = 100 / Q = 45

Входные данные:

```text
total_well_depth_m = 80
water_level_mode = unknown
distance_to_house_m = 50
highest_water_point_floor = 3
water_points = sink + shower + toilet + washing_machine + dishwasher
casing_diameter_mode = unknown
voltage_mode = unknown
```

Расчет:

```text
water_level = 80 * 0.7 = 56
required_pressure = 30
vertical_lift = 9
pipe_losses = max(50 * 0.1, 2) = 5
H = 56 + 30 + 9 + 5 = 100 м

Q = 8 + 12 + 6 + 10 + 8 = 44 л/мин
required_flow_l_min = 45 л/мин
```

Для подбора используется:

```text
required_head_m = 100
required_flow_l_min = 45
selected_voltage = 220
casing_diameter_mm = null
```

Предупреждения:

```text
Уровень воды неизвестен, поэтому расчет выполнен в оценочном режиме: рабочий уровень принят как 70% от общей глубины скважины. Результат является предварительным и требует подтверждения специалистом.
Диаметр обсадной трубы неизвестен, поэтому фильтр по диаметру насоса не применен. Совместимость по диаметру нужно подтвердить перед покупкой.
Напряжение не указано, поэтому для предварительного подбора использовано 220В. Перед покупкой нужно подтвердить доступное питание.
```

## Итоговый принцип

Расчетное ядро должно давать простые, объяснимые значения `H` и `Q`, достаточные для предварительного подбора. Оно не должно обещать гарантированную совместимость насоса со скважиной. Финальный выбор обычно требует подтверждения специалистом.
