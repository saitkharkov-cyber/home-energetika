# Analyze Names Stage

Документ описывает контракт `AnalyzeNamesStage` — stage анализа имён атрибутов в Framework Standardization.

`AnalyzeNamesStage` работает после `ExportAttributesStage` и до `AnalyzeValuesStage`.

```text
ExportAttributesStage
→ AnalyzeNamesStage
→ AnalyzeValuesStage
````

---

## Назначение

`AnalyzeNamesStage` анализирует найденные в scope атрибуты OpenCart и формирует кандидатов, которые могут быть синонимами целевого канонического атрибута.

Главная задача stage:

```text
found_attributes
→ proposed / rejected / ambiguous candidates
```

Stage помогает инженеру увидеть, какие атрибуты потенциально относятся к той же характеристике.

---

## Главный принцип

`AnalyzeNamesStage` не утверждает синонимы автоматически.

Она только предлагает кандидатов для ручной проверки.

```text
proposed_synonym_candidates
→ предложения Framework
→ не финальное решение
```

Финальное решение принимает инженер.

---

## Место в pipeline

`AnalyzeNamesStage` вызывается после того, как `AttributeExporter` уже прочитал факты из OpenCart.

К моменту запуска stage уже должны быть заполнены:

```text
AttributeContext.canonical
AttributeContext.scope
AttributeContext.raw_data.attributes
AttributeContext.raw_data.product_attributes
AttributeContext.attribute_name_structure.target_attribute
AttributeContext.attribute_name_structure.found_attributes
```

---

## Техническое имя stage

```text
analyze_names
```

Результат выполнения записывается в:

```text
AttributeContext.stage_results.analyze_names
```

---

## Ответственность AnalyzeNamesStage

Stage отвечает за:

* анализ найденных имён атрибутов;
* сравнение найденных атрибутов с целевым каноническим атрибутом;
* поиск точных совпадений;
* поиск похожих имён;
* анализ групп атрибутов;
* учёт `usage_count`;
* учёт `sample_values`;
* формирование кандидатов:

  * `proposed`;
  * `rejected`;
  * `ambiguous`;
* формирование предупреждений;
* формирование диагностики по именам.

---

## Что AnalyzeNamesStage не делает

`AnalyzeNamesStage` не должна:

* читать БД напрямую;
* изменять БД;
* нормализовать значения;
* выполнять `ValueParser`;
* создавать канонический атрибут;
* утверждать синонимы;
* переносить значения между атрибутами;
* формировать SQL preview;
* изменять импорты;
* выбирать канон автоматически.

---

## Читает из AttributeContext

```text
AttributeContext.canonical
AttributeContext.scope
AttributeContext.raw_data.attributes
AttributeContext.raw_data.product_attributes
AttributeContext.attribute_name_structure.target_attribute
AttributeContext.attribute_name_structure.found_attributes
AttributeContext.job.raw_job.analysis_rules
```

---

## Пишет в AttributeContext

```text
AttributeContext.attribute_name_structure.exact_matches
AttributeContext.attribute_name_structure.similar_name_candidates
AttributeContext.attribute_name_structure.rejected_name_candidates
AttributeContext.attribute_name_structure.diagnostics

AttributeContext.synonym_candidates.proposed
AttributeContext.synonym_candidates.rejected
AttributeContext.synonym_candidates.ambiguous

AttributeContext.warnings
AttributeContext.errors
AttributeContext.stage_results.analyze_names
```

---

## Входная структура found_attributes

Stage работает со списком:

```text
AttributeContext.attribute_name_structure.found_attributes
```

Минимальная структура элемента:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
usage_count
sample_values
```

Пример:

```yaml
attribute_id: 456
attribute_name: Диаметр корпуса
attribute_group_id: 7
attribute_group_name: Насосы
usage_count: 8
sample_values:
  - "96"
  - "101"
```

---

## Целевой атрибут

Целевой канонический атрибут берётся из:

```text
AttributeContext.attribute_name_structure.target_attribute
```

Минимальная структура:

```text
attribute_id
attribute_name
attribute_group_id
attribute_group_name
```

Пример:

```yaml
attribute_id: 123
attribute_name: Диаметр насоса
attribute_group_id: 7
attribute_group_name: Насосы
```

---

## Базовый алгоритм

Рекомендуемый общий порядок анализа:

```text
1. Получить target_attribute
2. Получить found_attributes
3. Исключить технически некорректные элементы
4. Найти точные совпадения
5. Найти похожие имена
6. Проверить группы атрибутов
7. Проверить usage_count
8. Проверить sample_values
9. Разделить кандидатов на proposed / rejected / ambiguous
10. Записать diagnostics
11. Записать warnings
12. Записать stage_result
```

---

## Exact matches

`exact_matches` — атрибуты, которые совпадают с целевым атрибутом по `attribute_id` или по строгим правилам имени.

Пример:

```yaml
exact_matches:
  - attribute_id: 123
    attribute_name: Диаметр насоса
    reason: target_attribute_id_match
```

Точное совпадение по `attribute_id` означает, что это сам целевой атрибут.

Точное совпадение по имени не означает автоматическое утверждение, если `attribute_id` другой.

---

## Similar name candidates

`similar_name_candidates` — атрибуты, похожие по названию на целевую характеристику.

Примеры возможных похожих имён:

```text
Диаметр насоса
Диаметр корпуса
Диаметр, мм
Диаметр
Ø насоса
```

Похожесть имени является диагностикой.

Она не доказывает, что атрибут является синонимом.

---

## Rejected name candidates

`rejected_name_candidates` — атрибуты, которые были рассмотрены, но отклонены stage.

Причины отклонения могут быть такими:

```text
unrelated_name
wrong_attribute_group
incompatible_samples
too_generic_name
technical_attribute
low_confidence
```

Пример:

```yaml
attribute_id: 789
attribute_name: Диаметр патрубка
reason: different_meaning
```

---

## Ambiguous candidates

`ambiguous` — кандидаты, по которым Framework не может дать уверенное предложение.

Примеры причин:

```text
name_is_similar_but_samples_are_unclear
name_is_too_generic
same_name_different_group
high_usage_but_unclear_meaning
conflicting_sample_values
```

Такие кандидаты требуют ручной проверки инженером.

---

## Proposed synonym candidates

`proposed` — кандидаты, которые Framework предлагает инженеру рассмотреть как возможные синонимы.

Пример:

```yaml
attribute_id: 456
attribute_name: Диаметр корпуса
attribute_group_id: 7
attribute_group_name: Насосы
usage_count: 8
sample_values:
  - "96"
  - "101"
reason: similar_name_and_compatible_samples
confidence: medium
```

Важно:

```text
proposed
→ не утверждено
→ не применяется автоматически
→ требует ручного решения
```

---

## Confidence

`confidence` — диагностический уровень уверенности Framework.

Допустимые значения первого этапа:

```text
low
medium
high
```

`confidence` не является автоматическим разрешением на применение.

Даже `high` требует ручного подтверждения.

---

## Reason

Каждый кандидат должен иметь объяснение в поле `reason`.

Примеры:

```text
target_attribute_id_match
exact_name_match
similar_name
similar_name_and_same_group
similar_name_and_compatible_samples
same_group_but_generic_name
wrong_group
incompatible_samples
ambiguous_name
different_meaning
```

`reason` нужен, чтобы инженер понимал, почему кандидат попал в конкретный список.

---

## Usage count

`usage_count` — количество товаров в scope, у которых есть значение по конкретному `attribute_id`.

Принцип:

```text
usage_count
→ диагностический сигнал
→ не автоматический выбор канона
```

Самый частотный атрибут не обязан быть правильным каноном.

Примеры:

```text
Целевой атрибут usage_count = 3
Другой похожий атрибут usage_count = 40
```

Это повод для предупреждения и ручной проверки, но не повод автоматически заменить канон.

---

## Sample values

`sample_values` используются как дополнительный диагностический сигнал.

Примеры:

```text
96 мм
101 мм
4"
```

Если имя похоже и sample values выглядят совместимыми с ожидаемой характеристикой, кандидат может попасть в `proposed`.

Если имя похоже, но sample values противоречивые, кандидат должен попасть в `ambiguous` или `rejected`.

`AnalyzeNamesStage` не нормализует sample values.

Нормализация выполняется позже в `AnalyzeValuesStage`.

---

## Attribute group

Группа атрибутов используется как дополнительный сигнал.

Пример:

```text
attribute_group_name: Насосы
```

Совпадение группы повышает уверенность.

Несовпадение группы не всегда автоматически отклоняет кандидата, но должно снижать уверенность или переводить кандидата в `ambiguous`.

---

## Диагностика

Stage должна заполнить:

```text
AttributeContext.attribute_name_structure.diagnostics
```

Минимальная структура:

```text
total_found
target_usage_count
most_frequent_attribute
similar_candidates_count
proposed_count
rejected_count
ambiguous_count
ambiguous_names
```

Пример:

```yaml
diagnostics:
  total_found: 12
  target_usage_count: 3
  most_frequent_attribute:
    attribute_id: 456
    attribute_name: Диаметр корпуса
    usage_count: 40
  similar_candidates_count: 4
  proposed_count: 2
  rejected_count: 6
  ambiguous_count: 1
  ambiguous_names:
    - Диаметр
```

---

## Предупреждения

Stage может добавить предупреждения в:

```text
AttributeContext.warnings
```

Возможные предупреждения:

```text
similar_attributes_found
ambiguous_name_candidates_found
target_attribute_has_low_usage_count
most_frequent_attribute_is_not_target
same_name_different_attribute_id
same_attribute_name_in_different_groups
generic_attribute_name_found
```

---

## Ошибки

Обычно `AnalyzeNamesStage` не должна создавать критические ошибки, если входные данные доступны.

Возможные критические ошибки:

```text
target_attribute_missing
found_attributes_missing
name_analysis_failed
```

Ошибки записываются в:

```text
AttributeContext.errors
AttributeContext.stage_results.analyze_names.errors
```

---

## Preconditions

Stage может выполняться только если:

```text
AttributeContext.canonical заполнен
AttributeContext.attribute_name_structure.target_attribute заполнен
AttributeContext.attribute_name_structure.found_attributes заполнен
AttributeContext.errors не содержит критических ошибок предыдущих stages
```

Если preconditions не выполнены, stage должна получить статус:

```text
skipped
```

или:

```text
blocked
```

в зависимости от причины.

---

## Stage result

Результат stage записывается в:

```text
AttributeContext.stage_results.analyze_names
```

Минимальная структура:

```text
status
started_at
finished_at
errors
warnings
summary
```

Пример:

```yaml
status: ok_with_warnings
started_at: 2026-07-03 12:10:00
finished_at: 2026-07-03 12:10:03
errors: []
warnings:
  - most_frequent_attribute_is_not_target
summary:
  found_attributes: 12
  proposed: 2
  rejected: 6
  ambiguous: 1
```

---

## Правила классификации кандидатов

### proposed

Кандидат может попасть в `proposed`, если:

```text
имя похоже на целевую характеристику
и группа не противоречит смыслу
и sample_values выглядят совместимыми
```

---

### ambiguous

Кандидат должен попасть в `ambiguous`, если:

```text
имя похоже, но слишком общее
или группа отличается и смысл неясен
или sample_values противоречивые
или данных недостаточно
```

---

### rejected

Кандидат должен попасть в `rejected`, если:

```text
имя явно относится к другой характеристике
или группа и sample_values противоречат смыслу
или это технический/служебный атрибут
```

---

## Ручное утверждение

`AnalyzeNamesStage` не создаёт списка утверждённых синонимов.

На текущем этапе результат stage — это только предложения.

Будущий ручной или полуавтоматический approval может быть отдельной stage:

```text
ManualApprovalStage
```

или отдельным инженерным процессом.

---

## Влияние на SQL preview

Наличие `proposed_synonym_candidates` должно блокировать безопасный SQL preview до ручного утверждения.

Причина:

```text
Framework не имеет права автоматически решить,
что найденный атрибут является синонимом.
```

`BuildSqlPreviewStage` должен видеть эту ситуацию и добавить блокировку:

```text
manual_approval_required
```

---

## Граница с AttributeExporter

`AttributeExporter` только подготавливает факты:

```text
found_attributes
usage_count
sample_values
```

`AnalyzeNamesStage` интерпретирует эти факты и раскладывает кандидатов по группам.

---

## Граница с AnalyzeValuesStage

`AnalyzeNamesStage` не нормализует значения.

Она может смотреть на `sample_values` как на сырой диагностический сигнал, но не должна делать итоговый вывод о нормализованных значениях.

Нормализация выполняется в:

```text
AnalyzeValuesStage
```

---

## Граница с FrameworkResult

`FrameworkResult` должен получить из `AnalyzeNamesStage`:

```text
found_attributes
proposed_synonym_candidates
rejected_candidates
ambiguous_candidates
warnings
diagnostics
```

---

## Пример результата

```yaml
attribute_name_structure:
  exact_matches:
    - attribute_id: 123
      attribute_name: Диаметр насоса
      reason: target_attribute_id_match

  similar_name_candidates:
    - attribute_id: 456
      attribute_name: Диаметр корпуса
      reason: similar_name

  rejected_name_candidates:
    - attribute_id: 789
      attribute_name: Диаметр патрубка
      reason: different_meaning

  diagnostics:
    total_found: 3
    target_usage_count: 12
    most_frequent_attribute:
      attribute_id: 123
      attribute_name: Диаметр насоса
      usage_count: 12
    similar_candidates_count: 1
    proposed_count: 1
    rejected_count: 1
    ambiguous_count: 0
    ambiguous_names: []

synonym_candidates:
  proposed:
    - attribute_id: 456
      attribute_name: Диаметр корпуса
      attribute_group_id: 7
      attribute_group_name: Насосы
      usage_count: 8
      sample_values:
        - "96"
        - "101"
      reason: similar_name_and_compatible_samples
      confidence: medium

  rejected:
    - attribute_id: 789
      attribute_name: Диаметр патрубка
      reason: different_meaning

  ambiguous: []

warnings:
  - similar_attributes_found

stage_results:
  analyze_names:
    status: ok_with_warnings
    errors: []
    warnings:
      - similar_attributes_found
    summary:
      proposed: 1
      rejected: 1
      ambiguous: 0
```

---

## Требования к реализации

Реализация `AnalyzeNamesStage` должна быть:

* детерминированной;
* объяснимой;
* безопасной;
* без записи в БД;
* без автоматического утверждения синонимов;
* расширяемой правилами;
* пригодной для ручной проверки результата.

---

## Статус документа

Документ является архитектурным контрактом `AnalyzeNamesStage`.

Код реализации должен следовать этому контракту.


