# DB-readonly Analyze / Preview Next Step Spec

Mini-spec для следующего инженерного перехода после DB-backed `resolve_scope` / `export_attributes`.

Документ определяет, что делать с текущими DB-readonly-compatible adapters:

```text
analyze_names
analyze_values
build_sql_preview
```

Этот документ не разрешает реализацию, production normalization, SQL apply или executable SQL.

## Текущее состояние DB-readonly path

Текущий DB-readonly path:

```text
resolve_canonical       -> DB-backed
resolve_scope           -> DB-backed
export_attributes       -> DB-backed
analyze_names           -> DB-readonly-compatible adapter
analyze_values          -> DB-readonly-compatible adapter
build_sql_preview       -> DB-readonly-compatible blocked preview
build_report            -> dry-run
build_framework_result  -> dry-run
```

Default dry-run path остаётся отдельным no-DB fixture path:

```text
bin/dry-run.php
PipelineFactory::createDefault()
config/jobs/pump_diameter.php
```

DB-readonly path работает только с local dump DB через read-only connection.

## Термины

### DB-backed stage

DB-backed stage читает данные из local dump DB через `ReadOnlyDbConnectionInterface`.

Для текущего проекта DB-backed stages:

```text
resolve_canonical
resolve_scope
export_attributes
```

Они используют real DB IDs и source marker:

```text
local_dump_db_readonly
```

### DB-readonly-compatible adapter

DB-readonly-compatible adapter не обязательно читает DB сам.

Он принимает real DB facts, которые уже появились в `AttributeContext`, и не требует fixture IDs вроде:

```text
product_id = 0
attribute_id = 0
```

Текущие adapters:

```text
DbReadOnlyAttributeNameAnalyzer
DbReadOnlyAttributeValueAnalyzer
DbReadOnlySqlPreviewBuilder
```

Они нужны, чтобы DB-readonly pipeline проходил с real DB facts, но они не являются production implementation.

### Dry-run stage

Dry-run stage работает на fixture facts и/или собирает итоговые структуры без production decisions.

В текущем DB-readonly path dry-run stages:

```text
build_report
build_framework_result
```

Default dry-run path полностью остаётся fixture/no-DB.

## Текущая роль adapters

### DbReadOnlyAttributeNameAnalyzer

Текущая роль:

- проверяет согласованность `target_attribute` и `found_attributes`;
- проверяет, что `canonical.target_attribute_id` присутствует как real DB attribute ID;
- возвращает diagnostic `exact_matches`;
- возвращает пустые synonym decisions.

Он не делает:

- production synonym decisions;
- fuzzy matching;
- approval/rejection synonyms;
- изменение OpenCart data;
- SQL generation.

### DbReadOnlyAttributeValueAnalyzer

Текущая роль:

- принимает real DB raw values из `export_attributes`;
- сохраняет `raw_values`;
- считает базовые diagnostics;
- собирает examples;
- отмечает empty values.

Он не делает:

- production normalization;
- parser-driven normalization;
- unit conversion;
- unknown/invalid semantic classification;
- SQL blockers по normalized values.

Текущий явный marker:

```text
db_readonly_values_not_normalized
```

### DbReadOnlySqlPreviewBuilder

Текущая роль:

- возвращает blocked preview;
- не генерирует SQL statements;
- не считает результат safe-to-apply;
- фиксирует diagnostics по raw/normalized/unknown/invalid/empty values.

Он не делает:

- executable SQL;
- SQL apply;
- production SQL diff;
- real update/create/delete operations;
- `safe_to_apply = 1`.

Текущий blocker:

```text
db_readonly_sql_preview_not_implemented
```

## Почему adapters не production implementation

Adapters сейчас закрывают compatibility boundary после перехода `resolve_scope` и `export_attributes` на real DB facts.

Они не являются production implementation, потому что:

- не принимают irreversible domain decisions;
- не утверждают synonyms;
- не нормализуют values;
- не создают executable SQL;
- не рассчитывают apply plan;
- не переводят pipeline в safe-to-apply mode.

Их задача - сохранить read-only pipeline зелёным и диагностическим.

## Риски ранней production normalization

Если начать production normalization слишком рано, риски будут такими:

- неправильный parser может исказить real DB values;
- mixed units могут быть нормализованы неверно;
- empty/unknown values могут быть ошибочно превращены в valid values;
- raw DB text может потерять важный контекст;
- SQL preview может начать выглядеть применимым без полного safety model;
- downstream report может создать ложное ощущение production-ready результата;
- будущий SQL apply boundary станет неявным.

До отдельного решения normalization должна оставаться read-only analysis/profiling, без apply semantics.

## Данные, доступные после export_attributes

После DB-backed `export_attributes` доступны:

- `canonical`;
- `scope`;
- `raw_data.products`;
- `raw_data.attributes`;
- `raw_data.attribute_groups`;
- `raw_data.product_attributes`;
- `attribute_name_structure.target_attribute`;
- `attribute_name_structure.found_attributes`;
- `attribute_value_structure.raw_values`.

Ключевые real DB facts:

- real `product_id`;
- real `attribute_id`;
- real `attribute_group_id`;
- real `language_id`;
- raw OpenCart product attribute text;
- attribute usage counts;
- sample values;
- target attribute from canonical resolver.

DB-readonly export facts не должны содержать fixture placeholders:

```text
product_id = 0
attribute_id = 0
```

## Что нужно для полноценного analyze_names

Полноценный DB-readonly `analyze_names` потребует отдельного mini-spec.

Нужные данные:

- target canonical attribute;
- all found attributes in scope;
- attribute names by language;
- attribute group names;
- usage counts by attribute;
- product coverage per attribute;
- sample values per attribute;
- rules for exact name match;
- rules for similar-name diagnostics;
- explicit boundary between diagnostic candidates and approved synonym decisions.

Нужно заранее решить:

- какие comparisons являются только diagnostics;
- какие candidates нельзя считать approved synonyms;
- как обрабатывать multilingual names;
- как фиксировать ambiguity;
- какие warnings должны блокировать future SQL preview.

Текущий вывод:

```text
analyze_names оставить adapter/stub на ближайший шаг.
```

Причина: полноценный name analysis быстро приближается к production synonym decisions, а этот boundary пока не специфицирован.

## Что нужно для полноценного analyze_values

Полноценный DB-readonly `analyze_values` тоже требует отдельного mini-spec, но его можно развивать безопаснее как read-only value profiling.

Нужные данные:

- `raw_values` по target attribute;
- `product_id`;
- `attribute_id`;
- `language_id`;
- raw text;
- parser name from job, например `diameter_mm`;
- value rules from job;
- category/scope facts;
- examples and frequencies.

Безопасный следующий уровень может включать:

- подсчёт уникальных raw values;
- частоты raw values;
- пустые значения;
- suspicious values как diagnostics;
- examples по самым частым значениям;
- value length / format diagnostics;
- grouping по raw text без normalization.

На этом уровне нельзя делать:

- production normalization;
- unit conversion;
- запись `normalized_values` как apply-ready data;
- SQL blocker decisions, которые выглядят как production approval;
- executable SQL preview.

Рекомендуемый следующий безопасный engineering step:

```text
развить analyze_values в read-only value profiling stage без normalization
```

Рабочее имя будущего шага:

```text
DB-readonly value profiling mini-spec
```

## build_sql_preview в DB-readonly режиме

`build_sql_preview` в DB-readonly режиме должен оставаться blocked preview, пока нет отдельной production SQL/apply architecture.

Он может:

- отображать diagnostics;
- показывать, почему SQL preview blocked;
- считать counts из upstream diagnostics;
- фиксировать `apply_changes = 0`;
- фиксировать `safe_to_apply = 0`;
- возвращать empty `statements`.

Он не должен:

- генерировать executable SQL;
- добавлять SQL files;
- делать `safe_to_apply = 1`;
- создавать apply plan;
- использовать write/schema operations;
- превращать read-only profiling в SQL diff.

## Проверки перед будущей реализацией

Перед будущей реализацией value profiling нужно проверить:

1. Текущий DB-readonly runner остаётся зелёным.
2. Default dry-run остаётся зелёным.
3. `raw_values` после `export_attributes` содержат real `product_id`.
4. `raw_values` после `export_attributes` содержат real `attribute_id`.
5. `raw_values` не содержат `product_id = 0`.
6. `raw_values` не содержат `attribute_id = 0`.
7. `value_rules.value_parser = diameter_mm` присутствует в job.
8. Profiling output не заполняет production `normalized_values`.
9. `DbReadOnlySqlPreviewBuilder` остаётся blocked preview.
10. Runtime config и dump files не попадают в git.

После будущей реализации обязательно:

```text
C:\php56\php.exe -l <changed php files>
C:\php56\php.exe framework-standardization\bin\dry-run.php framework-standardization\config\jobs\pump_diameter.php
C:\php56\php.exe framework-standardization\bin\db-readonly-run.php framework-standardization\config\jobs\pump_diameter.db_readonly.php framework-standardization\config\runtime\local.dump.php
git status
```

## Out of Scope

Вне scope текущего шага:

- PHP implementation;
- pipeline wiring changes;
- runner changes;
- default dry-run path changes;
- runtime config changes;
- production normalization;
- parser registry implementation;
- synonym approval;
- executable SQL;
- SQL apply;
- SQL files;
- live DB;
- write/schema operations;
- OpenCart module paths;
- expansion beyond `pump_diameter`;
- expansion beyond `category_id = 11900213`;
- expansion beyond `language_id = 1`.

Запрещённые operation families:

```text
INSERT
UPDATE
DELETE
REPLACE
ALTER
DROP
TRUNCATE
CREATE
```

## Рекомендуемое решение

На следующем engineering step:

```text
оставить analyze_names adapter/stub
оставить build_sql_preview blocked preview
разработать mini-spec для analyze_values как read-only value profiling stage без normalization
```

Это даёт полезную инженерную видимость по real DB values, но не пересекает границы production normalization и SQL apply.
