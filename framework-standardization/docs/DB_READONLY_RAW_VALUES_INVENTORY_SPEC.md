# DB-readonly raw values inventory spec

Дата: 2026-07-07

Статус: spec / decision analysis only.

Этот документ формализует следующий блок controlled attribute consolidation workflow после canonical selection:

```text
canonical attribute group
-> raw values inventory
-> подготовка к canonical unit / normalized_value contract
```

Raw values inventory начинается только после explicit human decision:

* canonical `attribute_id` выбран;
* included alias `attribute_ids` подтверждены;
* excluded similar-but-different `attribute_ids` явно исключены;
* unresolved candidates отделены для отдельного анализа.

## 1. Purpose

Цель spec:

* описать DB-readonly процесс сбора реальных raw values по утверждённой группе `attribute_id`;
* показать пользователю полный или ограниченный inventory существующих значений;
* подготовить данные для canonical unit / `normalized_value` contract;
* не создавать normalization proposals на этом шаге;
* не делать parser/normalizer implementation;
* не менять `config/jobs`;
* не создавать SQL/apply output.

Raw values inventory отвечает на вопрос:

```text
какие реальные raw values существуют в выбранной canonical attribute group?
```

Он не отвечает на вопросы:

```text
какая canonical unit утверждена?
какой final normalized_value должен быть создан?
какие proposals approved/rejected?
можно ли применять SQL?
```

## 2. Input

Raw values inventory принимает только уже утверждённый canonical selection context.

Минимальные input fields:

* category scope, например `category_id`;
* `language_id`;
* canonical `attribute_id`;
* canonical `attribute_name`;
* included alias `attribute_ids`;
* explicitly excluded `attribute_ids`;
* unresolved candidates, если есть;
* optional discovery context / target meaning.

Canonical selection required.

Если canonical selection не утверждён, raw values inventory запускать нельзя.

Input должен явно отделять:

* accepted canonical attribute;
* accepted aliases / duplicates;
* excluded similar-but-different attributes;
* unresolved candidates.

Excluded attributes не должны попадать в inventory.

Unresolved candidates не должны попадать в inventory без отдельного human decision.

## 3. Output

System должна показать inventory по raw values.

Минимальные output fields:

* `raw_value`;
* `usage_count`;
* `attribute_ids where value appears`;
* `attribute_names where value appears`;
* sample `product_ids`;
* optional sample product names, если доступны readonly;
* normalized preview fields только как пустые/неутверждённые placeholders, если нужны;
* warnings.

Allowed placeholder fields, если UI/report нуждается в форме:

* `candidate_normalized_value = null`;
* `candidate_unit = null`;
* `normalization_status = not_proposed`;
* `contract_approved = 0`.

Эти placeholders не являются proposals.

Output не должен содержать:

* approved normalized values;
* rejected values;
* SQL statements;
* apply plan;
* production-ready output.

## 4. Sorting / grouping

Inventory должен:

* группировать одинаковые raw values;
* сортировать сначала по `usage_count` descending;
* затем по `raw_value` stable order;
* сохранять связь `raw_value` с конкретными `attribute_id`;
* не смешивать values из excluded attributes;
* отдельно показывать values из included aliases, если они отличаются по naming/source.

Если один raw value встречается в нескольких included attributes, output должен показать:

* все `attribute_ids`;
* все `attribute_names`;
* aggregate `usage_count`;
* per-attribute counts, если доступны;
* representative product samples.

Если alias source имеет values, которые конфликтуют с canonical meaning, это должно быть warning, а не automatic exclusion.

## 5. Warnings / dirtiness signals

Inventory должен предусмотреть warnings:

* empty values;
* duplicate spelling variants;
* units mixed in raw strings;
* multiple units;
* text + number combined;
* ranges;
* HTML entities;
* fractions;
* comma/point decimals;
* suspicious suffixes;
* values that look like another semantic;
* values from included alias that конфликтуют с canonical meaning;
* too many unique values;
* low coverage;
* uncertain unit.

Warnings являются diagnostics only.

Warnings не означают:

* reject;
* approve;
* normalization proposal;
* SQL/apply permission.

## 6. Relationship to canonical unit contract

Raw values inventory не утверждает unit.

Raw values inventory не создаёт final `normalized_value`.

Raw values inventory только показывает данные для human decision.

Canonical unit и `normalized_value` contract должны быть отдельным следующим step.

Для selector/cache-related attributes canonical unit contract обязателен до implementation.

До explicit canonical unit / `normalized_value` contract запрещено:

* создавать normalization proposals;
* запускать parser/normalizer implementation;
* менять `config/jobs`;
* делать production/cache changes;
* делать SQL/apply.

## 7. Relationship to review-chain

Standalone review-chain получает proposals только после:

* raw values inventory completed;
* canonical unit approved;
* `normalized_value` contract approved;
* proposal generation spec/step completed.

Review-chain не должен получать raw inventory напрямую как proposals.

`approved` в review-chain не означает SQL apply permission.

Apply plan возможен только отдельным explicit step после review.

## 8. Example context

Illustrative example only, without implementation:

```text
canonical attribute_id = 12
attribute_name = Максимальный напор
canonical key = pump_max_head
```

Raw samples:

* `46.5м.`;
* `68м.`;
* `93м.`;
* `133м.`.

Inventory должен показать, что values имеют метры в raw string.

Но unit `m` должен быть утверждён отдельным canonical unit / contract step.

Inventory не должен:

* создавать fixture/source для `pump_max_head`;
* создавать normalized proposals;
* передавать raw строку `68м.` в selector/cache;
* делать SQL/apply.

## 9. Production safety

Production/cache changes запрещены.

No cache rebuild.

Характеристики, которые могут попасть в selector/cache, требуют explicit canonical unit contract before implementation.

Production incident with `max_flow_l_min` remains warning example:

* был временный cache hotfix для Belamos/Pedrollo;
* rebuild восстановил старые flow values in `m/h`;
* therefore unit semantics must not be guessed.

Следствие:

* raw values inventory не должен guessing unit semantics;
* raw values inventory не должен создавать production output;
* selector/cache-facing implementation нельзя делать до explicit canonical unit contract;
* production/cache actions требуют separate explicit approval.

## 10. Boundaries

Этот spec описывает DB-readonly inventory only.

Запрещено:

* canonical selection in this step;
* auto-merge;
* normalization proposals in this step;
* parser implementation;
* normalizer implementation;
* PHP implementation;
* config/jobs changes;
* pipeline wiring;
* runner integration;
* SQL preview;
* SQL generation;
* SQL files;
* SQL diff;
* apply plan;
* SQL apply;
* live DB / production DB;
* DB/schema changes;
* write/schema operations;
* production output;
* production/cache changes;
* cache rebuild;
* runtime artifacts;
* committed runtime artifacts;
* default dry-run path changes.

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

## 11. Recommended next step

Следующий отдельный spec step:

```text
canonical unit and normalized_value contract after raw values inventory
```

Этот следующий step должен использовать inventory как evidence для human decision.

Он не должен:

* создавать parser/normalizer implementation без separate explicit decision;
* создавать proposals до approved contract;
* менять `config/jobs`;
* подключаться к pipeline/runners;
* создавать SQL/apply output;
* использовать live DB;
* делать production/cache changes.
