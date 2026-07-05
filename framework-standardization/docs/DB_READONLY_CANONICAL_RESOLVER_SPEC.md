# DB Read-only Canonical Resolver Spec

Короткий mini-spec для первого DB-backed canonical lookup в Framework Standardization.

## 1. Назначение

`DbReadOnlyCanonicalAttributeResolver` - первый DB-backed компонент для `ResolveCanonicalStage`.

Он должен:

- читать только локальный OpenCart dump/local DB;
- работать только через read-only DB connection;
- возвращать тот же result shape, что `DryRunCanonicalAttributeResolver`;
- не применять SQL;
- не подключать DB к `PipelineFactory` / CLI.

Компонент не является OpenCart-модулем и не создаёт OpenCart runtime paths.

## 2. Вход

Интерфейс не менять:

```php
resolve($canonicalCode)
```

Первый поддерживаемый canonical:

```text
canonical_code = pump_diameter
```

Для первого MVP resolver должен учитывать scope и language из job/runtime context. Mapping не должен быть глобальным.

## 3. Выход / result shape

Result shape должен совпадать с `DryRunCanonicalAttributeResolver`:

```php
array(
    'found' => 1,
    'canonical' => array(
        'canonical_id' => 1,
        'canonical_code' => 'pump_diameter',
        'target_attribute_id' => 44,
        'target_attribute_name' => 'Диаметр насоса',
        'target_attribute_group_id' => 8,
        'target_attribute_group_name' => 'Прочие',
        'status' => 'active',
        'locked' => 1,
        'source' => 'local_dump_db_readonly',
    ),
    'errors' => array(),
    'warnings' => array(),
    'source' => 'local_dump_db_readonly',
)
```

При ошибке:

```php
array(
    'found' => 0,
    'canonical' => array(),
    'errors' => array('canonical_code_not_found'),
    'warnings' => array(),
    'source' => 'local_dump_db_readonly',
)
```

## 4. Scope-aware mapping для первого MVP

Первый MVP mapping:

```text
canonical_code = pump_diameter
scope.category_id = 11900213
scope.category_name = Скважинные насосы
language_id = 1
target_attribute_id = 44
target_attribute_name = Диаметр насоса
target_attribute_group_id = 8
target_attribute_group_name = Прочие
source = local_dump_db_readonly
```

Ключевое решение:

```text
pump_diameter + category_id 11900213 + language_id 1 -> attribute_id 44
```

Не фиксировать mapping как глобальный:

```text
pump_diameter -> 44
```

Проверенный факт локального dump:

```text
attribute_id = 44 используется в category_id = 11900213 на 385 товарах
```

## 5. Какие таблицы читать

Разрешены только read-only reads из локального dump:

```text
oc_language
oc_attribute
oc_attribute_description
oc_attribute_group
oc_attribute_group_description
oc_product
oc_product_to_category
oc_product_attribute
```

Имена таблиц должны строиться через `db_prefix`, не через hardcoded `oc_` в коде.

## 6. Концептуальные SELECT-проверки

Все запросы должны быть single-statement `SELECT` через `ReadOnlyDbConnectionInterface`.

### language exists

Проверить, что `language_id = 1` существует и активен.

Концептуально:

```sql
SELECT language_id, name, code, status
FROM oc_language
WHERE language_id = :language_id
```

### target attribute exists by ID/name/group/language

Проверить, что `attribute_id = 44` существует, имеет имя `Диаметр насоса`, группу `8`, имя группы `Прочие`, и все описания соответствуют `language_id = 1`.

Концептуально:

```sql
SELECT
  a.attribute_id,
  a.attribute_group_id,
  ad.name AS attribute_name,
  agd.name AS attribute_group_name
FROM oc_attribute a
JOIN oc_attribute_description ad
  ON ad.attribute_id = a.attribute_id
JOIN oc_attribute_group ag
  ON ag.attribute_group_id = a.attribute_group_id
JOIN oc_attribute_group_description agd
  ON agd.attribute_group_id = ag.attribute_group_id
WHERE a.attribute_id = :target_attribute_id
  AND a.attribute_group_id = :target_attribute_group_id
  AND ad.language_id = :language_id
  AND ad.name = :target_attribute_name
  AND agd.language_id = :language_id
  AND agd.name = :target_attribute_group_name
```

### target attribute is used in scope category

Проверить, что target attribute реально используется товарами в `category_id = 11900213`.

Концептуально:

```sql
SELECT COUNT(DISTINCT p.product_id) AS product_count
FROM oc_product p
JOIN oc_product_to_category p2c
  ON p2c.product_id = p.product_id
JOIN oc_product_attribute pa
  ON pa.product_id = p.product_id
WHERE p2c.category_id = :category_id
  AND pa.attribute_id = :target_attribute_id
  AND pa.language_id = :language_id
```

### products count in scope for this attribute

Для первого MVP ожидаемый verified count:

```text
385
```

Resolver не обязан блокировать работу только из-за изменения count, если это будет утверждено отдельно. Для первого safe implementation допустимо вернуть warning, если count отличается от ожидаемого.

## 7. Ошибки

Минимальный набор ошибок:

```text
canonical_code_not_found
scope_category_not_supported
language_id_not_found
target_attribute_id_not_found
target_attribute_not_used_in_scope
canonical_lookup_failed
```

Рекомендации:

- неизвестный `canonical_code` -> `canonical_code_not_found`;
- `category_id` не `11900213` для первого MVP -> `scope_category_not_supported`;
- `language_id` не найден -> `language_id_not_found`;
- target attribute не найден или не совпал по name/group/language -> `target_attribute_id_not_found`;
- usage count равен `0` -> `target_attribute_not_used_in_scope`;
- DB/read-only exception -> `canonical_lookup_failed`.

## 8. Совместимость с DryRunCanonicalAttributeResolver

Сохранить:

- `CanonicalAttributeResolverInterface`;
- метод `resolve($canonicalCode)`;
- result shape;
- error style как array of string codes;
- `canonical_code_not_found` для неизвестного canonical.

`ResolveCanonicalStage` не должен знать, какой resolver используется:

```text
DryRunCanonicalAttributeResolver
DbReadOnlyCanonicalAttributeResolver
```

Stage должен продолжать читать только result array resolver-а и писать `canonical`, `errors`, `stage_results.resolve_canonical` в `AttributeContext`.

## 9. Что НЕ делать

- Не подключать `DbReadOnlyCanonicalAttributeResolver` к `PipelineFactory` / CLI.
- Не менять dry-run поведение.
- Не менять `ResolveCanonicalStage`.
- Не менять stage order.
- Не создавать canonical registry table.
- Не создавать SQL apply.
- Не использовать live DB.
- Не читать production DB напрямую.
- Не выполнять `INSERT`, `UPDATE`, `DELETE`, `REPLACE`, `ALTER`, `DROP`, `TRUNCATE`, `CREATE`.
- Не начинать DB-backed `ResolveScopeStage`.
- Не начинать DB-backed `ExportAttributesStage`.
- Не создавать OpenCart module paths.
- Не менять `AttributeContext` shape.

## 10. Следующий шаг после spec

Только после отдельного утверждения:

- реализовать `DbReadOnlyCanonicalAttributeResolver`;
- покрыть только `pump_diameter + category_id 11900213 + language_id 1`;
- оставить `PipelineFactory` / CLI на dry-run resolver-е;
- проверить PHP 5.6 compatibility.
