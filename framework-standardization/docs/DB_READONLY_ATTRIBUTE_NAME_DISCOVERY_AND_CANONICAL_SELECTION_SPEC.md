# DB-readonly attribute name discovery and canonical selection spec

Дата: 2026-07-07

Статус: spec / decision analysis only.

Этот документ формализует первый недостающий блок controlled attribute consolidation workflow:

```text
target attribute meaning
-> attribute name discovery
-> canonical selection / include-exclude decision
```

Raw values inventory намеренно не формализуется глубоко в этом документе и должен быть следующим отдельным spec step после canonical selection.

## 1. Purpose

Цель spec:

* описать DB-readonly процесс поиска похожих характеристик по целевому смыслу;
* показать candidates пользователю до любых `config/jobs`;
* подготовить human canonical selection;
* не объединять `attribute_id` автоматически;
* отделить discovery / canonical selection от raw values inventory, normalization proposals и SQL/apply.

Framework standardization работает как controlled attribute consolidation workflow, а не как fully automatic normalizer.

Этот блок отвечает только на вопросы:

```text
какие реальные attribute names похожи на целевой смысл?
какой attribute_id человек выбирает canonical?
какие похожие attribute_id человек включает или исключает?
```

Он не отвечает на вопросы:

```text
какие raw values нужно нормализовать?
какой canonical unit применить?
какие normalization proposals создать?
можно ли делать SQL/apply?
```

## 2. Input concept

Входом является целевой смысл характеристики, заданный пользователем.

Примеры:

* `максимальный напор насоса`;
* `диаметр насоса`;
* `производительность насоса`.

Важно:

* target meaning не обязан совпадать с точным `attribute_name`;
* target meaning не является стартовым `config/jobs`;
* target meaning является business/domain meaning, по которому system ищет похожие реальные attribute names;
* system должна искать реальные candidates в DB-readonly / dump-derived данных;
* system не должна считать первое найденное похожее название canonical автоматически.

## 3. Discovery output

System должна показать candidates для human review.

Минимальный output по каждому candidate:

* `attribute_id`;
* `attribute_name`;
* `usage_count`;
* optional category coverage;
* short raw samples, только как preview;
* warnings;
* reason why candidate найден;
* possible role.

Allowed possible roles:

* `canonical candidate`;
* `possible alias / duplicate`;
* `similar but different`;
* `unsafe / needs manual review`.

Raw samples в этом блоке используются только как preview, чтобы помочь отличить alias от different meaning.

Raw samples не должны превращаться в:

* raw values inventory;
* normalization proposal input;
* canonical unit decision;
* SQL/apply input.

Suggested candidate diagnostics:

* exact / partial name match;
* same domain words;
* unit hints in name;
* ambiguous wording;
* possible min/max/nominal conflict;
* possible flow/head/diameter semantic conflict;
* low usage count;
* duplicate-looking name in another attribute group;
* samples unavailable.

## 4. Human decision model

User должен явно принять решения:

* какой `attribute_id` является canonical;
* какие `attribute_id` включить как aliases/duplicates;
* какие похожие `attribute_id` явно исключить;
* какие candidates оставить unresolved для отдельного анализа.

System не должна сама автоматически объединять похожие названия.

Decision output должен быть structured enough для следующего шага:

* `target_meaning`;
* `canonical_attribute_id`;
* `canonical_attribute_name`;
* `included_alias_attribute_ids`;
* `excluded_attribute_ids`;
* `unresolved_attribute_ids`;
* human notes / warnings.

Human canonical selection является обязательной gate перед raw values inventory.

## 5. Examples / risk cases

Пример риска по pump-domain attributes:

* `Максимальный напор`;
* `Минимальный напор`;
* `Номинальный напор`;
* `Max напор, м`;
* `Максимальный напор, м.вод.ст.`.

Эти names похожи, но не обязаны означать одно и то же.

Возможные interpretation risks:

* `Максимальный напор` может быть canonical max head;
* `Минимальный напор` имеет другой смысл и не должен быть merged в max head автоматически;
* `Номинальный напор` может быть отдельной rating/nominal value;
* `Max напор, м` может быть alias, но требует human confirmation;
* `Максимальный напор, м.вод.ст.` может быть alias с unit wording, но требует human confirmation.

Automatic merge запрещён.

Нужна human canonical selection.

## 6. Relationship to config/jobs

`config/jobs` не является стартовой точкой угадывания характеристики.

`config/jobs` должен появляться после accepted canonical decision/contract.

Architecture model:

* одна характеристика = один job/contract;
* один тип значений = один parser/normalizer family;
* новая характеристика не обязательно означает новый уникальный PHP-обработчик;
* если value semantics уже покрыта существующим parser family, отдельный обработчик под конкретный `attribute_id` не нужен.

Следствие:

* discovery сначала показывает реальные candidates;
* user выбирает canonical attribute;
* затем появляется canonical unit/contract;
* только после этого может появиться или обновиться `config/jobs`.

## 7. Relationship to raw values inventory

Raw values inventory начинается только после:

* canonical selection;
* include/exclude decision;
* unresolved candidates отделены от accepted group.

Нельзя собирать normalization proposals до утверждения группы `attribute_id`.

Нельзя начинать parser/normalizer work только на основании похожего name match.

Raw values inventory должен быть следующим отдельным spec step.

Этот будущий spec должен отдельно описать:

* как собирать raw values по accepted canonical + aliases group;
* как показывать samples/frequencies;
* как оценивать грязность значений;
* как перейти к canonical unit contract;
* как не создавать proposals до explicit unit/contract decision.

## 8. Production safety

Характеристики, которые могут попасть в selector/cache, требуют explicit canonical unit contract до implementation.

Production/cache actions требуют отдельного explicit approval.

No production cache rebuild.

Production note по `max_flow_l_min`:

* был временный cache hotfix для Belamos/Pedrollo;
* rebuild восстановил старые flow values в шкале `m/h`;
* это пример риска неправильной unit semantics.

Следствие:

* похожие attributes нельзя объединять автоматически;
* unit semantics нельзя выводить неявно;
* selector/cache-facing implementation нельзя делать до explicit canonical unit contract;
* production/cache changes нельзя делать без отдельного approval.

## 9. Boundaries

Этот spec описывает DB-readonly discovery only.

Запрещено:

* auto-merge;
* auto-canonical selection;
* config/jobs changes;
* PHP implementation;
* parser implementation;
* normalizer implementation;
* raw values inventory implementation in this step;
* normalization proposals in this step;
* SQL preview;
* SQL generation;
* SQL files;
* SQL diff;
* apply plan;
* SQL apply;
* live DB / production DB;
* DB/schema changes;
* write/schema operations;
* production/cache changes;
* cache rebuild;
* runtime artifacts;
* pipeline/runners integration.

Запрещённые operation families:

* `INSERT`;
* `UPDATE`;
* `DELETE`;
* `REPLACE`;
* `ALTER`;
* `DROP`;
* `TRUNCATE`;
* `CREATE`.

`approved` остаётся только review-chain status.

`approved` не означает:

* SQL apply allowed;
* `safe_to_apply = 1`;
* `production_ready = 1`;
* apply-ready output.

## 10. Recommended next step

Следующий отдельный spec step:

```text
DB-readonly raw values inventory after canonical selection
```

Этот следующий step должен начинаться только после accepted canonical selection / include-exclude decision.

Он не должен:

* реализовывать parser;
* создавать normalization proposals;
* менять config/jobs;
* подключаться к pipeline/runners;
* создавать SQL/apply output;
* использовать live DB;
* делать production/cache changes.
