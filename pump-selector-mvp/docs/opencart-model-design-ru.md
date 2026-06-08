# Архитектура модели OpenCart 2.3 для MVP подбора скважинного насоса

Документ описывает backend-логику модели OpenCart 2.3 для предварительного подбора скважинного насоса. Это проектирование модели, а не production-код. В документе не описываются frontend, AJAX, контроллеры и шаблоны.

Все пользовательские формулировки результата должны оставаться осторожными: `предварительный подбор`, `обычно подходит`, `требует подтверждения специалистом`. Модель не должна формировать гарантирующие выводы о совместимости насоса со скважиной.

## 1. Назначение модели

Модель отвечает за три backend-задачи:

- рассчитать требования к насосу из ответов пользователя: требуемый напор `H`, требуемый расход `Q`, напряжение и диаметр обсадной трубы;
- получить из нормализованной таблицы `oc_pump_selector_product` три рекомендованные карточки: `best_price`, `optimal_choice`, `premium`;
- отдельно пересобирать нормализованный кеш товаров для подборщика.

Таблица `oc_pump_selector_product` считается нормализованным кешем данных для подборщика. Модель не должна парсить исходные текстовые атрибуты OpenCart при каждом пользовательском подборе.

```text
oc_pump_selector_product
```

Источник правды для пересборки кеша:

```text
oc_product
oc_product_attribute
oc_attribute_description
oc_manufacturer
oc_product_to_category
```

Цена, остаток и статус товара должны проверяться в актуальной таблице OpenCart:

```text
oc_product
```

## 2. Предполагаемый файл

```text
catalog/model/extension/module/pump_selector.php
```

Предполагаемое имя класса для OpenCart 2.3:

```text
ModelExtensionModulePumpSelector
```

Это только архитектурное соглашение. Production-код в рамках текущего этапа не создается.

## 3. Методы модели

Предлагаемый набор методов:

```text
calculateRequirements(input)
validateInput(input)
getRecommendedProducts(requirements)
getBestPriceProduct(requirements)
getOptimalProduct(requirements)
getPremiumProduct(requirements)
buildWarnings(input, requirements)
rebuildSelectorProducts()
```

### calculateRequirements(input)

Главный метод расчетного ядра. Принимает пользовательский ввод, вызывает валидацию, рассчитывает `H` и `Q`, нормализует `voltage` и `casing_diameter_mm`, собирает warnings и assumptions.

### validateInput(input)

Проверяет корректность входных данных до расчета. Если есть ошибки, расчет не выполняется, а вызывающий слой получает список ошибок по полям.

### getRecommendedProducts(requirements)

Главный метод получения товаров. Использует результаты `calculateRequirements`, вызывает три независимых метода выбора:

- `getBestPriceProduct(requirements)`;
- `getOptimalProduct(requirements)`;
- `getPremiumProduct(requirements)`.

После этого метод обрабатывает дубли и возвращает финальный список карточек.

### getBestPriceProduct(requirements)

Возвращает самый дешевый подходящий товар. Брендовый приоритет не должен влиять на этот выбор.

### getOptimalProduct(requirements)

Возвращает товар с минимальным общим запасом:

```text
total_reserve = head_reserve + flow_reserve
```

Сортировка:

```text
total_reserve ASC
price ASC
product_id ASC
```

Брендовый приоритет не должен влиять на `optimal_choice`.

### getPremiumProduct(requirements)

Возвращает лучший брендовый вариант среди подходящих товаров. Использует `brand_priority > 0`.

Сортировка:

```text
brand_priority DESC
head_reserve ASC
flow_reserve ASC
price ASC
product_id ASC
```

### buildWarnings(input, requirements)

Формирует предупреждения по расчетным допущениям и неизвестным входным данным:

- неизвестный уровень воды;
- неизвестный диаметр обсадной трубы;
- неизвестное напряжение;
- другие осторожные сообщения, если они понадобятся в MVP.

### rebuildSelectorProducts()

Служебный backend-метод для пересборки нормализованного кеша `oc_pump_selector_product`. Он не вызывается при каждом пользовательском подборе.

Метод должен:

- очистить `oc_pump_selector_product`;
- заново собрать товары из категорий `11900308` и `11900309`;
- использовать источники правды OpenCart: `oc_product`, `oc_product_attribute`, `oc_attribute_description`, `oc_manufacturer`, `oc_product_to_category`;
- взять только товары с обязательными характеристиками: `Максимальный напор`, `Максимальная производительность`, `Диаметр насоса`;
- распарсить `max_head_m`;
- распарсить `max_flow_l_min` именно в `л/мин`, то есть из значения внутри скобок перед `л/мин`;
- распарсить `pump_diameter_mm`;
- нормализовать `voltage` в `220` или `380`;
- выставить `brand_priority`: `Pedrollo% = 10`, `Belamos% = 5`, остальные `0`;
- записать только товары с `price > 0`, `quantity > 0`, `status = 1`;
- вернуть статистику выполнения.

Статистика:

```text
total_scanned
eligible_inserted
skipped_no_attributes
skipped_no_price
skipped_no_stock
skipped_disabled
```

Для MVP достаточно текущих категорий:

```text
11900308
11900309
```

Архитектура должна оставаться расширяемой: в будущем список категорий и правила приоритета брендов лучше вынести в настройки модуля или отдельную конфигурацию, а не зашивать глубоко в SQL.

## 4. Входные поля calculateRequirements

Метод `calculateRequirements(input)` принимает структуру с полями:

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

Поле `water_level_m` обязательно только при `water_level_mode = known`.

Поле `custom_vertical_lift_m` обязательно только при `highest_water_point_floor = custom`.

Поле `casing_diameter_mm` обязательно только при `casing_diameter_mode = known`.

## 5. Результат calculateRequirements

Метод возвращает расчетный объект:

```text
required_head_m
required_flow_l_min
selected_voltage
casing_diameter_mm или null
warnings
assumptions
```

### Расчет H

```text
H = water_level + required_pressure + vertical_lift + pipe_losses
```

Где:

```text
required_pressure = 30 м
pipe_losses = max(distance_to_house_m * 0.1, 2)
```

Если `water_level_mode = known`:

```text
water_level = water_level_m
```

Если `water_level_mode = unknown`:

```text
water_level = total_well_depth_m * 0.7
```

В этом случае добавляется warning и assumption.

Вертикальный подъем:

```text
1 этаж = 3 м
2 этаж = 6 м
3 этаж = 9 м
custom = custom_vertical_lift_m
```

### Расчет Q

Расходы точек:

| Точка | Расход |
|---|---:|
| `sink` | 8 л/мин |
| `shower` | 12 л/мин |
| `toilet` | 6 л/мин |
| `washing_machine` | 10 л/мин |
| `dishwasher` | 8 л/мин |
| `irrigation` | 20 л/мин |

```text
Q = сумма выбранных точек
```

Для передачи в подбор допустимо округлять итоговый расход вверх до ближайших `5 л/мин`, если это правило закреплено в расчетном ядре MVP.

### Напряжение

Если `voltage_mode = 220`:

```text
selected_voltage = 220
```

Если `voltage_mode = 380`:

```text
selected_voltage = 380
```

Если `voltage_mode = unknown`:

```text
selected_voltage = 220
```

В этом случае обязательно добавляются:

```text
assumptions = ["voltage_default_220"]
voltage_was_assumed = true
```

Warning для пользователя:

```text
Напряжение не указано, поэтому предварительный подбор выполнен для сети 220В. Если на объекте доступно 380В, сообщите это специалисту.
```

Если `voltage_mode = 220` или `voltage_mode = 380`, значение считается указанным пользователем:

```text
voltage_was_assumed = false
```

### Диаметр обсадной трубы

Если `casing_diameter_mode = known`:

```text
casing_diameter_mm = входное значение casing_diameter_mm
```

Если `casing_diameter_mode = unknown`:

```text
casing_diameter_mm = null
```

В этом случае фильтр по диаметру в подборе не применяется, а пользователю нужно показать warning.

## 6. Логика getRecommendedProducts

Метод `getRecommendedProducts(requirements)` использует нормализованную таблицу:

```text
oc_pump_selector_product psp
```

и актуальные данные товара:

```text
oc_product p
```

Общие условия для всех трех выборок:

```text
psp.is_eligible = 1
psp.max_head_m >= required_head_m
psp.max_flow_l_min >= required_flow_l_min
psp.voltage = selected_voltage
p.price > 0
p.quantity > 0
p.status = 1
```

Если `casing_diameter_mm` не равен `null`, добавляется условие:

```text
psp.pump_diameter_mm <= casing_diameter_mm
```

Если `casing_diameter_mm = null`, фильтр по диаметру не применяется.

`selected_voltage` всегда должен быть нормализован в `220` или `380` до выполнения SQL. Если пользователь указал `unknown`, расчетное ядро использует `220` и добавляет warning.

Важно: `getRecommendedProducts(requirements)` не должен запускать `rebuildSelectorProducts()`. Пересборка кеша является отдельной служебной операцией, потому что она читает атрибуты товаров, очищает таблицу и может быть тяжелой для выполнения на каждом пользовательском запросе.

### best_price

Назначение: самый дешевый подходящий насос.

Сортировка:

```text
price ASC
head_reserve ASC
flow_reserve ASC
product_id ASC
```

Возвращаемый `result_type`:

```text
best_price
```

### optimal_choice

Назначение: наиболее сбалансированный вариант по минимальному общему запасу.

Расчеты:

```text
head_reserve = max_head_m - required_head_m
flow_reserve = max_flow_l_min - required_flow_l_min
total_reserve = head_reserve + flow_reserve
```

Сортировка:

```text
total_reserve ASC
price ASC
product_id ASC
```

Возвращаемый `result_type`:

```text
optimal_choice
```

### premium

Назначение: брендовый вариант среди подходящих насосов.

Дополнительное условие:

```text
brand_priority > 0
```

Сортировка:

```text
brand_priority DESC
head_reserve ASC
flow_reserve ASC
price ASC
product_id ASC
```

Возвращаемый `result_type`:

```text
premium
```

## 7. Логика исключения дублей

Один и тот же товар может быть выбран в нескольких категориях результата. Например, самый дешевый насос может одновременно оказаться оптимальным.

Не нужно выводить две одинаковые карточки товара.

Безопасный вариант для MVP:

- объединять карточки по `product_id`;
- хранить у карточки список ролей или бейджей;
- показывать одну карточку с несколькими бейджами.

Пример:

```text
product_id = 123
badges = ["best_price", "optimal_choice"]
```

Альтернативный вариант:

- объединять подписи в одну строку;
- например: `Лучшая цена / Оптимальный выбор`.

Предпочтительный вариант для модели: возвращать структурированный список `result_types`, а решение о текстовой подписи оставить слою представления.

Пример структуры после удаления дублей:

```text
products = [
  {
    product_id: 123,
    result_types: ["best_price", "optimal_choice"],
    max_head_m: 106,
    max_flow_l_min: 45,
    head_reserve: 6,
    flow_reserve: 0,
    total_reserve: 6,
    price: 10000
  },
  {
    product_id: 456,
    result_types: ["premium"],
    max_head_m: 110,
    max_flow_l_min: 60,
    head_reserve: 10,
    flow_reserve: 15,
    total_reserve: 25,
    price: 15000
  }
]
```

## 8. Структура результата модели

Итоговый метод модели может возвращать массив `result`:

```text
result = {
  requirements: {
    required_head_m,
    required_flow_l_min,
    selected_voltage,
    voltage_was_assumed,
    casing_diameter_mm
  },
  products: [
    {
      product_id,
      result_types,
      max_head_m,
      max_flow_l_min,
      pump_diameter_mm,
      voltage,
      brand_priority,
      head_reserve,
      flow_reserve,
      total_reserve,
      price
    }
  ],
  warnings: [],
  assumptions: [],
  debug: {
    calculation_summary: {
      water_level,
      required_pressure,
      vertical_lift,
      pipe_losses,
      selected_water_points,
      raw_flow_l_min,
      required_flow_l_min,
      voltage_was_assumed
    }
  }
}
```

Поле `debug.calculation_summary` полезно на этапе MVP и тестирования. В публичном выводе его можно не показывать или показывать только в админском/отладочном режиме.

## 9. Ошибки и fallback

### Нет товаров

Если после общего фильтра нет ни одной карточки:

- вернуть пустой `products`;
- сохранить рассчитанные `requirements`;
- добавить нейтральное сообщение для пользователя.

Формулировка:

```text
По введенным условиям не найдено подходящих насосов для предварительного подбора. Проверьте введенные данные или обратитесь к специалисту.
```

### Нет товаров из-за диаметра

Если при известном диаметре товаров нет, модели полезно выполнить диагностическую проверку без фильтра по диаметру.

Если без фильтра по диаметру товары есть, значит ограничение связано с диаметром обсадной трубы.

Fallback:

- вернуть пустой список или диагностический статус;
- добавить warning.

Формулировка:

```text
По указанному диаметру обсадной трубы подходящие насосы не найдены. Совместимость по диаметру нужно проверить отдельно.
```

### Нет товаров из-за напряжения

Если товаров нет при выбранном напряжении, модели полезно выполнить диагностическую проверку без фильтра по напряжению.

Если без фильтра по напряжению товары есть, значит ограничение связано с напряжением.

Формулировка:

```text
По выбранному напряжению подходящие насосы не найдены. Проверьте доступное питание: 220В или 380В.
```

### Неизвестный диаметр

Если `casing_diameter_mode = unknown`:

- подбор можно выполнить;
- фильтр по диаметру не применяется;
- обязательно вернуть warning.

Формулировка:

```text
Диаметр обсадной трубы неизвестен, поэтому фильтр по диаметру насоса не применен. Совместимость по диаметру нужно подтвердить перед покупкой.
```

### Неизвестный уровень воды

Если `water_level_mode = unknown`:

- расчет можно выполнить;
- использовать `70%` от `total_well_depth_m`;
- обязательно вернуть warning и assumption.

Формулировка:

```text
Уровень воды неизвестен, поэтому расчет выполнен в оценочном режиме: рабочий уровень принят как 70% от общей глубины скважины. Результат является предварительным и требует подтверждения специалистом.
```

## 10. Важные ограничения

MVP-подборщик должен явно оставаться инструментом предварительного подбора.

Нельзя использовать гарантирующие формулировки:

```text
точно подходит
гарантированно подходит
идеальный насос
полностью совместим
```

Допустимые формулировки:

```text
предварительный подбор
обычно подходит для введенных условий
требует подтверждения специалистом
совместимость по диаметру нужно проверить
```

Модель не заменяет инженерный расчет. Она использует упрощенные правила MVP:

- фиксированное требуемое давление `30 м`;
- потери в трубе `max(distance_to_house_m * 0.1, 2)`;
- уровень воды `70%` от глубины при неизвестном уровне;
- простая сумма точек водоразбора без сложных коэффициентов.

Если в будущем появятся профессиональные режимы расчета, их нужно проектировать отдельно и не смешивать с текущим MVP.

## 11. Автоматизация обновления кеша

Ручной запуск seed SQL допустим только для тестирования и первичной проверки данных. Для production-режима таблица `oc_pump_selector_product` должна обновляться через backend-метод `rebuildSelectorProducts()`.

Рекомендуемые способы запуска:

- кнопка в админке `Обновить данные подборщика`;
- protected cron URL с секретным `token`.

Админская кнопка нужна для ручного обновления после изменения карточек товаров, характеристик, брендов или категорий.

Cron URL нужен для автоматической периодической пересборки, например ночью или после регулярной синхронизации каталога.

Protected cron URL должен проверять секретный `token`. Без корректного token пересборка не должна запускаться.

Принцип:

```text
пользовательский подбор -> только расчет H/Q и чтение oc_pump_selector_product
служебное обновление -> rebuildSelectorProducts()
```

Нельзя пересобирать `oc_pump_selector_product` при каждом пользовательском подборе. Это увеличит время ответа, создаст лишнюю нагрузку на базу и может временно оставить подборщик без данных во время `TRUNCATE`.

Для MVP используется фиксированный набор категорий:

```text
11900308
11900309
```

В дальнейшем список категорий и приоритеты брендов должны быть расширяемыми без изменения основной логики подбора.
