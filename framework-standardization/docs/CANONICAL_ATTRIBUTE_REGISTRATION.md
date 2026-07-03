# Canonical Attribute Registration

Документ описывает контракт регистрации канонического атрибута в таблице:

```text
{DB_PREFIX}canonical_attributes
````

Это не инструкция по автоматическому применению SQL на продуктиве.

Операция регистрации должна подготовить проверенный SQL preview и отчёт для инженера.

---

## Назначение операции

Регистрация канонического атрибута фиксирует, какой реальный атрибут OpenCart считается глобальным каноном для определённого смысла характеристики.

Пример:

```text
canonical_code: pump_diameter
target_attribute_id: 123
target_attribute_name: Диаметр насоса
target_attribute_group_id: 7
target_attribute_group_name: Насосы
```

После регистрации Framework может использовать этот канон как целевой атрибут при анализе и подготовке стандартизации.

---

## Важный принцип

Канонический атрибут является глобальной сущностью OpenCart.

Категория не участвует в идентичности канона.

Категория может использоваться только как scope анализа и применения.

```text
canonical_code
+ target_attribute_id
→ глобальный канон

category_id
→ область текущей обработки
```

---

## Входные данные

Минимальные входные данные для регистрации канона:

```text
canonical_code
target_attribute_id
target_attribute_name
target_attribute_group_id
target_attribute_group_name
status
locked
comment
```

---

## Поля

### canonical_code

Стабильный технический код смысла характеристики.

Примеры:

```text
pump_diameter
voltage
max_head
flow_rate
power
```

Требования:

* не пустой;
* уникальный;
* пишется латиницей;
* используется как стабильный идентификатор смысла;
* не зависит от текущего названия атрибута в OpenCart.

---

### target_attribute_id

Реальный `attribute_id` из OpenCart.

Требования:

* должен существовать в таблице `{DB_PREFIX}attribute`;
* не должен уже использоваться в `{DB_PREFIX}canonical_attributes`;
* является целевым атрибутом, к которому будут приводиться варианты характеристики.

---

### target_attribute_name

Контрольный снимок текущего названия атрибута OpenCart.

Используется для проверки, что инженер регистрирует именно тот атрибут, который ожидает.

Требования:

* не пустой;
* должен совпадать с текущим названием атрибута в OpenCart для основного языка проекта.

---

### target_attribute_group_id

Реальный `attribute_group_id` из OpenCart.

Требования:

* должен существовать в таблице `{DB_PREFIX}attribute_group`;
* должен соответствовать группе, к которой относится `target_attribute_id`.

---

### target_attribute_group_name

Контрольный снимок текущего названия группы атрибутов OpenCart.

Требования:

* не пустой;
* должен совпадать с текущим названием группы атрибутов в OpenCart для основного языка проекта.

---

### status

Статус канона.

Допустимые значения:

```text
draft
active
```

Значение по умолчанию:

```text
draft
```

`draft` означает, что канон создан, но ещё не утверждён для полноценного использования.

`active` означает, что канон утверждён и может использоваться Framework.

---

### locked

Флаг блокировки канона.

Допустимые значения:

```text
0
1
```

Значение по умолчанию:

```text
0
```

`locked = 1` означает, что канон утверждён и не должен автоматически изменяться.

Изменение locked-канона допускается только вручную как отдельный инженерный процесс.

---

### comment

Свободный комментарий инженера.

Может содержать:

* причину выбора канона;
* источник решения;
* замечания по спорным вариантам;
* ссылки на ручную проверку.

Поле необязательное.

---

## Проверки перед INSERT

Перед созданием SQL preview должны быть выполнены проверки.

### 1. Проверка canonical_code

```text
canonical_code не пустой
canonical_code соответствует техническому формату
canonical_code ещё не существует в canonical_attributes
```

Рекомендуемый формат:

```text
^[a-z][a-z0-9_]*$
```

---

### 2. Проверка target_attribute_id

```text
target_attribute_id существует в {DB_PREFIX}attribute
target_attribute_id ещё не используется в {DB_PREFIX}canonical_attributes
```

Один реальный атрибут OpenCart не может быть каноном для двух разных смыслов.

---

### 3. Проверка target_attribute_group_id

```text
target_attribute_group_id существует в {DB_PREFIX}attribute_group
target_attribute_id относится к target_attribute_group_id
```

---

### 4. Проверка имени атрибута

Framework должен получить текущее имя атрибута из OpenCart и сравнить его с входным значением:

```text
input.target_attribute_name
==
current OpenCart attribute name
```

Если имена не совпадают, регистрация не должна считаться безопасной.

---

### 5. Проверка имени группы

Framework должен получить текущее имя группы атрибутов из OpenCart и сравнить его с входным значением:

```text
input.target_attribute_group_name
==
current OpenCart attribute group name
```

Если имена не совпадают, регистрация не должна считаться безопасной.

---

### 6. Проверка status

```text
status ∈ draft / active
```

---

### 7. Проверка locked

```text
locked ∈ 0 / 1
```

---

## Ошибки

Ошибки блокируют создание SQL preview.

К ошибкам относятся:

* пустой `canonical_code`;
* неверный формат `canonical_code`;
* уже существующий `canonical_code`;
* несуществующий `target_attribute_id`;
* `target_attribute_id` уже используется как канон;
* несуществующий `target_attribute_group_id`;
* атрибут не относится к указанной группе;
* несовпадение `target_attribute_name`;
* несовпадение `target_attribute_group_name`;
* недопустимый `status`;
* недопустимый `locked`.

---

## Предупреждения

Предупреждения не обязательно блокируют SQL preview, но должны быть показаны инженеру.

К предупреждениям относятся:

* `status = active` при `locked = 0`;
* `locked = 1` при `status = draft`;
* пустой `comment` для активного канона;
* очень похожий `canonical_code` уже существует;
* похожее имя атрибута уже зарегистрировано как другой канон.

---

## SQL preview

Операция регистрации не должна автоматически выполнять INSERT.

Она должна подготовить SQL preview.

Пример:

```sql
INSERT INTO `{DB_PREFIX}canonical_attributes` (
  `canonical_code`,
  `target_attribute_id`,
  `target_attribute_name`,
  `target_attribute_group_id`,
  `target_attribute_group_name`,
  `status`,
  `locked`,
  `comment`,
  `created_at`,
  `updated_at`
) VALUES (
  'pump_diameter',
  123,
  'Диаметр насоса',
  7,
  'Насосы',
  'draft',
  0,
  'Initial canonical attribute for pump diameter standardization.',
  NOW(),
  NOW()
);
```

Перед применением `{DB_PREFIX}` должен быть заменён на реальный префикс базы OpenCart.

---

## Результат операции

Операция должна вернуть инженеру:

```text
validation_status
errors[]
warnings[]
resolved_target_attribute
resolved_target_attribute_group
insert_sql_preview
```

---

## Пример успешного результата

```text
validation_status: ok

resolved_target_attribute:
  attribute_id: 123
  name: Диаметр насоса
  group_id: 7
  group_name: Насосы

warnings:
  - comment is empty

insert_sql_preview:
  INSERT INTO ...
```

---

## Пример ошибки

```text
validation_status: failed

errors:
  - target_attribute_id does not exist
  - target_attribute_name does not match current OpenCart attribute name

insert_sql_preview:
  not generated
```

---

## Граница ответственности

Эта операция отвечает только за регистрацию канона.

Она не:

* ищет синонимы;
* анализирует значения товаров;
* нормализует значения;
* переносит данные между атрибутами;
* удаляет старые атрибуты;
* изменяет импорты;
* публикует результат на продуктиве.

---

## Связь с Attribute Pipeline

После регистрации канона его можно использовать в `Attribute Job`.

Пример:

```text
Attribute Job
├─ canonical_code: pump_diameter
├─ target_attribute_id: 123
├─ scope: category_id
└─ rules/config
```

`Attribute Pipeline` использует зарегистрированный канон как целевой атрибут, но не создаёт его автоматически без отдельной операции регистрации.

---

## Статус документа

Документ является архитектурным контрактом операции регистрации канонического атрибута.

Код реализации должен следовать этому контракту.
