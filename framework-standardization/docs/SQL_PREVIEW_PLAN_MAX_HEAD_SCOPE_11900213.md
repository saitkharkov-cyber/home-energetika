# План SQL preview / apply-plan — максимальный напор, scope 11900213

Дата: 2026-07-09

## Контекст

Целевой смысл характеристики:

`максимальный напор`

Область категории:

`11900213`

Связанные решения:

- `docs/HUMAN_DECISION_MAX_HEAD_SCOPE_11900213.md`
- `docs/MAX_HEAD_UNIT_CONTRACT_SCOPE_11900213.md`
- `docs/MAX_HEAD_RANGE_POLICY_SCOPE_11900213.md`
- `docs/HUMAN_REVIEW_MAX_HEAD_PROPOSALS_SCOPE_11900213.md`

Связанные проверки:

- `docs/RUNTIME_CHECKS.md`

Связанные команды:

- `framework-standardization/bin/db-readonly-normalization-proposals.php`
- `framework-standardization/bin/db-readonly-normalization-review-chain.php`
- `framework-standardization/bin/db-readonly-normalization-review-sample.php`

## Цель следующего gate

Следующий gate должен подготовить только:

`SQL preview / apply-plan`

Это означает:

- показать, какие изменения потенциально будут нужны;
- показать affected rows;
- показать source attribute / canonical attribute;
- показать raw value / normalized value / canonical unit;
- показать unresolved exclusions;
- показать safety markers.

Это не означает выполнение SQL.

## Входные данные

Разрешённые входные данные для будущего SQL preview:

- category_scope: `11900213`
- canonical_attribute_id: `12`
- included attribute_ids:
  - `12`
  - `101`
  - `119`
  - `81`
- canonical_unit: `m`
- review-approved rows:
  - `481` accepted simple proposals
- unresolved rows:
  - `14` values remain unresolved and must be excluded

## Что можно включать в SQL preview

В SQL preview можно включать только строки, которые соответствуют всем условиям:

- product находится внутри category_scope `11900213`;
- attribute_id один из `12`, `101`, `119`, `81`;
- строка прошла review-chain как `pending_review`;
- строка получила human review decision: `review-approved`;
- raw value соответствует accepted simple value rules;
- proposed normalized value является decimal meters;
- canonical_unit: `m`;
- значение не относится к unresolved patterns.

## Что нельзя включать в SQL preview

Нельзя включать:

- unresolved values;
- ranges;
- upper-bound textual values;
- mixed-text values;
- ambiguous multi-number values;
- attributes outside `12`, `101`, `119`, `81`;
- products outside category_scope `11900213`;
- any values not covered by human review decision.

Примеры excluded unresolved values:

- `100-104`
- `104–118`
- `50–51,5`
- `до 51 м`

## Что должен показать будущий SQL preview

Будущий SQL preview должен быть человекочитаемым и должен показать:

- product_id;
- source attribute_id;
- source attribute_name;
- canonical_attribute_id;
- canonical_attribute_name;
- raw_value;
- proposed_normalized_value;
- canonical_unit;
- planned action;
- reason;
- review status source.

Planned action должен быть только описательным, например:

`preview_update_normalized_value`

или:

`preview_insert_canonical_value`

Точный тип действия должен быть выбран только после проверки текущей схемы хранения нормализованных значений.

## Важное ограничение по схеме хранения

Этот документ не утверждает конкретный SQL.

Перед генерацией SQL preview нужно отдельно проверить текущую схему хранения:

- где хранится normalized_value;
- есть ли уже canonical attribute row;
- как представлена unit;
- нужно ли update существующей строки;
- нужен ли insert новой canonical row;
- нужно ли сохранять source row;
- какие uniqueness constraints есть в таблицах;
- как избежать дублей.

Без этой проверки нельзя переходить к SQL generation.

## Разрешённый следующий технический шаг

Следующий технический шаг может быть только DB-readonly command/spec для анализа apply-plan.

Разрешено:

- читать local dump;
- проверять наличие текущих строк;
- показывать preview действий;
- считать affected rows;
- показывать конфликтные/дублирующиеся случаи;
- показывать unresolved exclusions.

Запрещено:

- выполнять SQL;
- создавать SQL files/diff;
- менять product data;
- менять config/jobs;
- менять pipeline/runners;
- трогать production/cache;
- rebuild cache.

## Требования к safety markers будущего SQL preview

Будущий SQL preview должен явно печатать safety markers:

```text
sql_preview_generated: 1
sql_files_created: 0
sql_apply_allowed: 0
sql_applied: 0
apply_plan_created: 0
product_data_changed: 0
production_ready: 0
cache_rebuild_required: 0
unresolved_values_excluded: 1
```

Если будет отдельный apply-plan preview, он должен также явно печатать:

```text
apply_plan_preview_generated: 1
apply_plan_executable: 0
```

## Граница между SQL preview и apply-plan

SQL preview:

- показывает потенциальные SQL-действия;
- не создаёт исполняемый план;
- не создаёт SQL-файл;
- не разрешает apply.

Apply-plan:

- должен быть отдельным gate;
- должен быть создан только после review SQL preview;
- не должен автоматически выполнять SQL;
- должен иметь отдельное human decision.

## Подтверждение безопасности

Этот документ является только планом следующего gate.

Он не:

- генерирует SQL;
- создаёт SQL preview;
- создаёт SQL files/diff;
- создаёт apply-plan;
- выполняет SQL;
- меняет product data;
- трогает production/cache;
- разрешает cache rebuild.

## Результат

Зафиксировано:

- `481` review-approved simple proposals могут быть источником для будущего SQL preview;
- `14` unresolved values должны быть исключены;
- перед SQL preview нужна DB-readonly проверка текущей схемы хранения;
- SQL/apply по-прежнему запрещены.

## Следующий gate

Следующий gate:

`DB-readonly анализ текущей схемы хранения для SQL preview / apply-plan`

Без SQL generation и без apply.