# DB-readonly attribute discovery command spec

Дата: 2026-07-08

## 1. Назначение

Этот документ фиксирует specification-only границы первой команды/утилиты для поиска названий характеристик в БД в режиме только чтения.

Команда нужна как первый engineering step в актуальной workflow-модели controlled attribute consolidation:

* target attribute meaning;
* DB-readonly attribute name discovery;
* candidate list;
* human canonical selection;
* explicit include/exclude alias decision;
* raw values inventory;
* canonical unit / `normalized_value` contract;
* normalization proposals generation;
* standalone review-chain;
* separate explicit apply-plan.

Этот spec не является implementation prompt и не добавляет PHP-код.

## 2. Что должна решить будущая команда

Будущая DB-readonly discovery command должна помочь человеку увидеть реальные кандидаты в OpenCart DB перед выбором canonical attribute.

Команда отвечает на вопрос:

какие реальные `attribute_id` и `attribute_name` в базе могут относиться к заданному смыслу характеристики?

Команда не отвечает на вопросы:

* какой `attribute_id` точно является canonical;
* какие aliases нужно включить;
* какие значения нужно нормализовать;
* какую единицу измерения использовать;
* можно ли применять SQL.

Эти решения остаются отдельными human gates.

## 3. Входные данные будущей команды

Минимальные входы:

* target attribute meaning — человек описывает смысл целевой характеристики простым текстом;
* optional category/scope — категория или область анализа, если нужно сузить поиск;
* runtime config для readonly local dump DB.

Будущая команда может принимать дополнительные безопасные параметры:

* language hints для поиска по русским/украинским/английским названиям;
* optional search terms;
* limit candidates;
* limit raw samples;
* include/exclude inactive products, если это уже безопасно определено readonly contract.

Параметры не должны превращать команду в arbitrary SQL runner.

## 4. Readonly источники данных

Будущая команда может читать только local dump DB в readonly режиме.

Ожидаемые OpenCart facts:

* `attribute_id`;
* `attribute_name`;
* attribute group, если доступна;
* usage count по product attributes;
* category coverage, если scope/category безопасно доступны readonly;
* short raw samples preview по найденному candidate;
* sample `product_id`, если нужен preview;
* optional sample product names, если доступны readonly и не расширяют scope.

Команда не должна читать live DB или production DB.

Команда не должна выполнять write/schema operations.

## 5. Candidate output

Будущая команда должна выводить candidate list.

Для каждого candidate желательно показывать:

* `attribute_id`;
* `attribute_name`;
* `usage_count`;
* optional category coverage;
* short raw samples preview;
* warnings;
* reason found;
* possible role.

Возможные роли:

* canonical candidate;
* possible alias / duplicate;
* similar but different;
* unsafe / unresolved.

`possible role` является подсказкой для review, а не автоматическим решением.

## 6. Reason found

Команда должна объяснять, почему candidate попал в список.

Примеры reasons:

* exact name match;
* normalized text match;
* keyword overlap;
* unit hint overlap;
* category/scope usage overlap;
* raw sample hint;
* manually supplied search term matched.

Reason found не должен подменять human canonical selection.

## 7. Warnings

Команда должна подсвечивать риски.

Примеры warnings:

* no usage in selected scope;
* candidate has usage outside selected scope;
* name looks similar but may mean another characteristic;
* raw samples contain mixed units;
* raw samples contain no unit;
* raw samples contain multiple numbers/ranges;
* candidate belongs to unexpected attribute group;
* too few samples for confident review;
* candidate is unresolved / unsafe.

Warnings не являются reject/approve decision.

## 8. Human gate после discovery

После вывода candidate list человек должен вручную принять решения:

* выбрать canonical `attribute_id`;
* подтвердить included alias `attribute_ids`;
* исключить similar-but-different `attribute_ids`;
* оставить unresolved/unsafe candidates как unresolved, если нужно.

Без этого нельзя переходить к raw values inventory.

## 9. Relationship to raw values inventory

Raw values inventory начинается только после accepted canonical selection and explicit include/exclude alias decision.

Discovery command может показывать short raw samples preview, но это не заменяет full raw values inventory.

Preview нужен только для ориентации при выборе candidate list.

Inventory должен быть отдельным step.

## 10. Relationship to unit contract and proposals

Discovery command не утверждает canonical unit.

Discovery command не создаёт `normalized_value` contract.

Discovery command не генерирует normalization proposals.

Canonical unit / `normalized_value` contract должен быть отдельным human-approved step после raw values inventory.

Proposals generation возможна только после approved contract.

## 11. Relationship to config/jobs

`config/jobs` не является стартовой точкой угадывания характеристики.

Discovery command не должна создавать или менять `config/jobs`.

Будущий config/job может появляться только после:

* accepted canonical decision;
* completed raw values inventory;
* approved canonical unit / `normalized_value` contract;
* proposal generation model/spec, если нужен переход к proposals.

## 12. Relationship to standalone review-chain

Standalone review-chain уже остаётся полезной второй частью workflow, но она не должна получать discovery output напрямую.

Review-chain получает proposals только после:

* canonical attribute group selected;
* raw values inventory completed;
* canonical unit / `normalized_value` contract approved;
* normalization proposals generation completed.

`approved` в review-chain означает только review status.

`approved` не означает SQL apply permission.

## 13. Production/cache safety

Production/cache changes запрещены.

No cache rebuild.

Selector/cache-related attributes require explicit canonical unit contract before implementation.

Unit semantics нельзя угадывать автоматически.

Production incident with `max_flow_l_min` remains warning example:

* temporary cache hotfix for Belamos/Pedrollo;
* rebuild restored old flow values in `m/h`;
* therefore unit semantics must not be guessed.

## 14. Границы этого spec

Этот документ является documentation/spec only.

Разрешено этим документом:

* описать назначение будущей discovery command;
* описать expected inputs;
* описать expected readonly facts;
* описать expected candidate output;
* описать safety boundaries;
* зафиксировать relationship to next gates.

Запрещено этим документом:

* PHP implementation;
* parser implementation;
* normalizer implementation;
* config/jobs changes;
* pipeline wiring;
* runner integration;
* SQL preview;
* SQL generation/files/diff;
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

## 15. Future implementation direction

Следующий implementation step, если пользователь отдельно подтвердит `+`, может быть bounded PHP command/tool для DB-readonly attribute name discovery.

Будущий bounded prompt должен явно указать:

* какие файлы читать;
* какой один файл или ограниченный набор файлов менять/создавать;
* что команда readonly-only;
* что нельзя менять config/jobs, pipeline/runners, SQL/apply, runtime artifacts;
* какие PHP syntax checks выполнить через `C:\php56\php.exe`;
* какие manual readonly checks выполнить, если будет создан runnable command.

Этот документ сам не является таким prompt.
