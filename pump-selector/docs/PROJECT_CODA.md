# Pump Selector Handoff

## Текущий статус


Pump Selector MVP по категории скважинных насосов доведён до рабочего состояния.


Завершено:

- внедрение `PumpSelectorCacheBuilder`;
- построение cache-таблицы `pump_selector_product`;
- отказ от display-only полей в cache;
- подключение Sumoto через `brand_priority`;
- стабилизация ролей `Best Price`, `Optimal`, `Premium`;
- удаление временного debug/trace-кода;
- документация вынесена в `docs/`;
- история решений вынесена в `docs/history/`.

Этап завершён: 02.07.2026 22:09

## Ключевые файлы

```text
pump-selector/module/catalog/model/extension/module/pump_selector.php
pump-selector/module/catalog/model/extension/module/pump_selector_cache_builder.php
pump-selector/module/catalog/controller/extension/module/pump_selector.php
pump-selector/sql/install.sql
````

## Главные документы

```text
pump-selector/README.md
pump-selector/PROJECT_MASTER_SUMMARY.md
pump-selector/docs/ARCHITECTURE.md
pump-selector/docs/SELECTION_STRATEGY.md
pump-selector/docs/CACHE_BUILDER.md
pump-selector/docs/SELECTOR_CONTRACT.md
pump-selector/docs/DEPLOYMENT.md
pump-selector/docs/CHANGELOG.md
```

## Текущая логика рекомендаций

### Best Price

Самый дешёвый насос, полностью удовлетворяющий требованиям пользователя.

### Optimal

Более сбалансированный насос.

Особенности:

* не обязан быть самым дешёвым;
* ограничен price guard относительно Best Price;
* не должен становиться чрезмерно дорогим fallback-вариантом.

### Premium

Показывается только для брендов с высоким приоритетом.

Текущее MVP-правило:

```php
private $premium_brand_min_priority = 8;
```

Это означает:

* Pedrollo = 10 → может быть Premium;
* Sumoto = 8 → может быть Premium;
* Belamos = 5 → не может быть Premium.

Если Premium-кандидата нет, Premium не показывается. Это корректное поведение, а не ошибка.

## Важные архитектурные решения

* Source of Truth для отображаемых данных — стандартный каталог OpenCart.
* Cache не является копией каталога.
* Display-данные (`name`, `manufacturer`, `image`) не должны использоваться из cache.
* `product_price` хранится в cache только как техническое поле ранжирования.
* Builder не нормализует данные, а читает уже подготовленные атрибуты.
* Selector не исправляет данные каталога.
* Builder и Selector используют `attribute_id`, а не названия атрибутов.

## Attribute contract

Используются канонические атрибуты:

| Attribute ID | Значение           | Cache field        |
| -----------: | ------------------ | ------------------ |
|           12 | Максимальный напор | `max_head_m`       |
|           13 | Производительность | `max_flow_l_min`   |
|           15 | Напряжение         | `voltage`          |
|           44 | Диаметр насоса     | `pump_diameter_mm` |

## Cache

Таблица:

```text
oc_pump_selector_product
```

Ключевые поля:

```text
product_id
max_head_m
max_flow_l_min
pump_diameter_mm
voltage
brand_priority
is_eligible
product_price
quantity
status
date_modified
```

## Когда пересобирать cache

Пересобирать cache нужно, если изменились:

* данные каталога;
* нормализованные характеристики;
* `brand_priority`;
* Builder;
* структура `pump_selector_product`;
* правила eligibility.

Пересобирать cache не обязательно, если меняется только UI или отображение.

Для тестов после правок логики подбора желательно делать rebuild, чтобы исключить влияние устаревших данных.

## Тестирование

Есть браузерный скрипт с 15 сценариями автозаполнения формы.

Последний прогон после изменения Premium подтвердил:

* Best Price — минимальная цена;
* Optimal — разумный компромисс;
* Premium — только сильные бренды;
* Belamos больше не попадает в Premium;
* отсутствие Premium считается корректным результатом.

## Не трогать без причины

Не стоит сейчас менять:

* `total_reserve`;
* scoring;
* нормализацию head/flow;
* Premium price guard;
* Brand Tier;
* структуру cache.

Эти темы относятся к следующему этапу, а не к MVP.

## Возможные будущие улучшения

### Brand Tier

При росте количества брендов до 10–12 желательно отвязать Premium от числового `brand_priority`.

Целевое решение:

```text
brand_priority — порядок ранжирования
brand_tier / is_premium_brand — принадлежность к Premium
```

Пока для MVP используется:

```php
private $premium_brand_min_priority = 8;
```

### Улучшение Optimal

`total_reserve = head_reserve + flow_reserve` смешивает метры и л/мин.

Для MVP оставлено как рабочая эвристика.

Если появятся плохие сценарии, рассмотреть:

* отдельные ограничения по `head_reserve`;
* отдельные ограничения по `flow_reserve`;
* нормализацию запасов;
* отказ от суммирования разных единиц.

### UI

Если Premium отсутствует, желательно показывать понятное сообщение:

```text
Для заданных параметров более качественного премиального варианта не найдено.
Optimal уже является наиболее рациональным выбором.
```

## Последнее устойчивое состояние

На момент handoff:

* debug/trace удалён;
* Sumoto участвует в Premium;
* Belamos исключён из Premium;
* Optimal защищён price guard;
* 15 сценариев прошли успешно;
* алгоритм подбора можно считать MVP-ready.

## Следующий безопасный шаг через месяц

1. Открыть `PROJECT_MASTER_SUMMARY.md`.
2. Открыть `docs/SELECTION_STRATEGY.md`.
3. Проверить `docs/CHANGELOG.md`.
4. Прогнать 15 тестовых сценариев.
5. Только после этого принимать решение о новых изменениях.

Не начинать с переписывания алгоритма.
