# Attribute Exporter

Документ описывает контракт `AttributeExporter` — read-only слоя чтения фактов из OpenCart для Framework Standardization.

`AttributeExporter` используется pipeline stage:

```text
ExportAttributesStage
````

и заполняет сырые данные в `AttributeContext`.

---

## Назначение

`AttributeExporter` нужен для безопасного чтения фактов из базы OpenCart.

Он отвечает только за экспорт данных:

```text
OpenCart DB
→ AttributeExporter
→ AttributeContext.raw_data
```

`AttributeExporter` не анализирует смысл характеристик и не принимает архитектурных решений.

---

## Главный принцип

`AttributeExporter` является read-only компонентом.

Он может выполнять только SELECT-запросы.

Он не должен выполнять:

```text
INSERT
UPDATE
DELETE
ALTER
CREATE
DROP
```

---

## Ответственность AttributeExporter

`AttributeExporter` отвечает за получение фактических данных из OpenCart:

* товары в рамках scope;
* атрибуты товаров;
* группы атрибутов;
* значения атрибутов;
* usage count;
* sample values;
* наличие целевого атрибута в scope;
* фактические имена атрибутов и групп на нужном языке.

---

## Что AttributeExporter не делает

`AttributeExporter` не должен:

* создавать атрибуты;
* изменять атрибуты;
* удалять атрибуты;
* переносить значения;
* нормализовать значения;
* решать, что является синонимом;
* утверждать кандидатов;
* выбирать канон;
* применять SQL preview;
* менять импорты;
* читать правила обработки значений;
* выполнять `ValueParser`.

---

## Место в pipeline

`AttributeExporter` вызывается внутри:

```text
ExportAttributesStage
```

Порядок:

```text
ValidateJobStage
→ ResolveCanonicalStage
→ ResolveScopeStage
→ ExportAttributesStage
→ AnalyzeNamesStage
→ AnalyzeValuesStage
```

К моменту вызова `AttributeExporter` уже должны быть известны:

* разрешённый канон;
* scope;
* product_ids;
* language_id;
* db_prefix.

---

## Входные данные

Минимальные входные данные:

```text
canonical
scope
source
```

---

## canonical

Блок `canonical` содержит целевой канонический атрибут.

```text
canonical
├─ canonical_id
├─ canonical_code
├─ target_attribute_id
├─ target_attribute_name
├─ target_attribute_group_id
└─ target_attribute_group_name
```

---

## scope

Блок `scope` содержит область анализа.

```text
scope
├─ type
├─ category_id
├─ category_name
├─ include_subcategories
├─ product_ids
└─ product_count
```

`AttributeExporter` не должен сам решать, какие товары входят в scope.

Он получает уже подготовленный список:

```text
scope.product_ids
```

---

## source

Блок `source` описывает источник данных.

```text
source
├─ type
├─ database
├─ language_id
└─ db_prefix
```

На первом этапе поддерживается источник:

```text
opencart_db
```

---

## Выходные данные

`AttributeExporter` заполняет следующие разделы `AttributeContext`:

```text
raw_data.products
raw_data.attributes
raw_data.attribute_groups
raw_data.product_attributes
attribute_name_structure.target_attribute
attribute_name_structure.found_attributes
attribute_value_structure.raw_values
```

---

## raw_data.products

Список товаров, попавших в scope.

Минимальная структура элемента:

```text
product_id
model
name
category_ids
```

Пример:

```yaml
product_id: 1001
model: SUMOTO 4SDM
name: Скважинный насос Sumoto 4SDM
category_ids:
  - 11900213
```

---

## raw_data.attributes

Список атрибутов OpenCart, найденных у товаров scope.

Минимальная структура элемента:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
usage_count
```

Пример:

```yaml
attribute_id: 123
attribute_name: Диаметр насоса
attribute_group_id: 7
attribute_group_name: Насосы
usage_count: 12
```

---

## raw_data.attribute_groups

Список групп атрибутов, связанных с найденными атрибутами.

Минимальная структура элемента:

```text
attribute_group_id
attribute_group_name
```

---

## raw_data.product_attributes

Сырые значения атрибутов товаров.

Минимальная структура элемента:

```text
product_id
attribute_id
language_id
text
```

Пример:

```yaml
product_id: 1001
attribute_id: 123
language_id: 3
text: "96 мм"
```

---

## attribute_name_structure.target_attribute

Целевой канонический атрибут.

Заполняется на основе `canonical.target_attribute_id` и текущих данных OpenCart.

```text
target_attribute
├─ attribute_id
├─ attribute_name
├─ attribute_group_id
└─ attribute_group_name
```

Если целевой атрибут не найден в OpenCart, это критическая ошибка.

---

## attribute_name_structure.found_attributes

Все найденные атрибуты в scope, которые могут быть использованы последующими stages.

Пример элемента:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
usage_count
sample_values
```

Важно:

`AttributeExporter` не решает, какие из этих атрибутов являются синонимами.

Он только экспортирует факты.

---

## attribute_value_structure.raw_values

Сырые значения атрибутов.

Пример элемента:

```text
product_id
attribute_id
raw_text
language_id
```

`raw_text` не должен нормализоваться внутри `AttributeExporter`.

---

## Usage count

`usage_count` — количество товаров в scope, у которых есть значение по конкретному `attribute_id`.

Принцип:

```text
usage_count
→ диагностический факт
→ не автоматический выбор канона
```

`AttributeExporter` только считает частотность.

Интерпретация частотности выполняется позже в `AnalyzeNamesStage`.

---

## Sample values

`sample_values` — примеры сырых значений атрибута.

Они нужны инженеру и последующим stages для диагностики.

Принципы:

* значения берутся как есть;
* порядок может быть стабильным по `product_id`;
* количество ограничивается `analysis_rules.max_sample_values`;
* значения не нормализуются;
* пустые значения могут попадать в выборку, если это важно для диагностики.

---

## Язык данных

Все имена атрибутов, групп и значения читаются по:

```text
source.language_id
```

Если язык не найден, это критическая ошибка.

Если часть данных отсутствует на нужном языке, это предупреждение или ошибка в зависимости от контекста.

---

## Основные таблицы OpenCart

`AttributeExporter` может читать:

```text
{DB_PREFIX}product
{DB_PREFIX}product_description
{DB_PREFIX}product_to_category
{DB_PREFIX}product_attribute
{DB_PREFIX}attribute
{DB_PREFIX}attribute_description
{DB_PREFIX}attribute_group
{DB_PREFIX}attribute_group_description
```

На текущем этапе `AttributeExporter` не читает таблицу registry канонов напрямую.

Канон уже должен быть разрешён в `ResolveCanonicalStage`.

---

## Минимальные проверки

Перед экспортом `AttributeExporter` должен проверить:

* `scope.product_ids` не пустой;
* `source.language_id` задан;
* `source.db_prefix` задан;
* `canonical.target_attribute_id` задан;
* целевой атрибут существует в OpenCart;
* целевая группа атрибутов существует в OpenCart.

---

## Ошибки

Критические ошибки:

```text
scope_products_empty
language_id_empty
db_prefix_empty
target_attribute_id_empty
target_attribute_not_found
target_attribute_group_not_found
attribute_export_failed
product_attributes_export_failed
```

Ошибки записываются в:

```text
AttributeContext.errors
AttributeContext.stage_results.export_attributes.errors
```

---

## Предупреждения

Возможные предупреждения:

```text
target_attribute_not_used_in_scope
no_product_attributes_found
attribute_has_empty_values
attribute_name_missing_for_language
attribute_group_name_missing_for_language
sample_values_limited
```

Предупреждения записываются в:

```text
AttributeContext.warnings
AttributeContext.stage_results.export_attributes.warnings
```

---

## SQL safety

`AttributeExporter` не формирует SQL preview.

Он не должен возвращать SQL для изменения данных.

Разрешены только SELECT-запросы.

---

## Пример результата

```yaml
raw_data:
  products:
    - product_id: 1001
      model: SUMOTO 4SDM
      name: Скважинный насос Sumoto 4SDM
      category_ids:
        - 11900213

  attributes:
    - attribute_id: 123
      attribute_name: Диаметр насоса
      attribute_group_id: 7
      attribute_group_name: Насосы
      usage_count: 12

    - attribute_id: 456
      attribute_name: Диаметр корпуса
      attribute_group_id: 7
      attribute_group_name: Насосы
      usage_count: 8

  attribute_groups:
    - attribute_group_id: 7
      attribute_group_name: Насосы

  product_attributes:
    - product_id: 1001
      attribute_id: 123
      language_id: 3
      text: "96 мм"

attribute_name_structure:
  target_attribute:
    attribute_id: 123
    attribute_name: Диаметр насоса
    attribute_group_id: 7
    attribute_group_name: Насосы

  found_attributes:
    - attribute_id: 123
      attribute_name: Диаметр насоса
      attribute_group_id: 7
      attribute_group_name: Насосы
      usage_count: 12
      sample_values:
        - "96 мм"
        - "101 мм"

    - attribute_id: 456
      attribute_name: Диаметр корпуса
      attribute_group_id: 7
      attribute_group_name: Насосы
      usage_count: 8
      sample_values:
        - "96"
        - "101"

attribute_value_structure:
  raw_values:
    - product_id: 1001
      attribute_id: 123
      raw_text: "96 мм"
      language_id: 3
```

---

## Граница с AnalyzeNamesStage

`AttributeExporter` может заполнить:

```text
found_attributes
usage_count
sample_values
```

Но он не должен заполнять:

```text
synonym_candidates.proposed
synonym_candidates.rejected
synonym_candidates.ambiguous
```

Эти блоки относятся к `AnalyzeNamesStage`.

---

## Граница с AnalyzeValuesStage

`AttributeExporter` может заполнить:

```text
attribute_value_structure.raw_values
```

Но он не должен заполнять:

```text
normalized_values
unknown_values
invalid_values
empty_values
value_report
```

Эти блоки относятся к `AnalyzeValuesStage`.

---

## Граница с CanonicalAttributeRegistration

`AttributeExporter` не регистрирует канон.

Регистрация канона выполняется отдельной операцией, описанной в:

```text
docs/CANONICAL_ATTRIBUTE_REGISTRATION.md
```

`AttributeExporter` только использует уже разрешённый целевой атрибут.

---

## Граница с импортами

`AttributeExporter` не изменяет существующие импорты.

Он не вмешивается в ExcelPort, Suppler или другие импортные потоки.

Импорты рассматриваются как будущие потребители canonical layer, но не входят в ответственность `AttributeExporter`.

---

## Статус документа

Документ является архитектурным контрактом `AttributeExporter`.

Код реализации должен следовать этому контракту.
