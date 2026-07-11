# HANDOFF — framework-standardization / voltage normalization and exception review

Дата: 10-07-2026
Проект: `saitkharkov-cyber/home-energetika`
Локальный репозиторий: `D:\Git\home-energetika`
Рабочая область: `framework-standardization`

Codex resume:  `codex resume 019f35ab-7752-7251-a297-f16421ea092a`

Актуальная стабильная точка:

```text
896db38 Implement approved voltage normalization policy
```

Ветка:

```text
main
```

На момент последней проверки:

```text
HEAD -> main
origin/main
origin/HEAD
```

## 1. Общий контекст

Работа ведётся над framework для безопасной стандартизации характеристик OpenCart-каталога.

Framework должен обеспечивать:

* явный contract для каждой характеристики;
* иерархический category scope;
* discovery атрибутов;
* inventory исходных значений;
* нормализацию;
* статусы предложений;
* review package;
* human decision gates;
* строгий запрет изменений данных без отдельного apply gate;
* повторное использование generic-компонентов для следующих характеристик.

Текущая активная характеристика:

```text
Напряжение
```

Scope:

```text
root category: 11900213
category: Скважинные насосы
scope mode: hierarchical_category_path_exists
```

Текущий этап — не разработка normalizer и не SQL/apply.

Текущий documentation этап:

```text
documentation-only фиксация human review исключений напряжения завершена локально
```

## 2. Важные правила работы

Вся документация, пояснения, рабочие команды и отчёты — на русском языке.

Английский допускается для:

* PHP array keys;
* классов;
* namespaces;
* CLI options;
* file paths;
* SQL identifiers;
* enum-like значений;
* названий status/warning markers.

Команды для PowerShell 7+ давать одной строкой через:

```text
&&
```

Команда commit должна завершаться:

```powershell
git log --oneline --decorate -5 && git status --short
```

Пользователь редактирует файлы через Notepad++. Не предлагать Windows `notepad`.

Не просить Codex читать:

```text
framework-standardization/docs/RULES.md
```

Все ограничения конкретного шага нужно вставлять непосредственно в Codex prompt.

Не менять:

```text
framework-standardization/docs/HANDOFF.md
```

в середине рабочего этапа. HANDOFF обновляется только при закрытии или переносе сессии.

Не выполнять без отдельного явного gate:

* SQL preview;
* executable apply-plan;
* SQL/apply;
* `--confirm-apply`;
* production mutation;
* изменение product/category data;
* cache rebuild;
* другие cache actions.

Не использовать без крайней необходимости:

```text
git reset
git restore
git checkout --
git stash
```

Не смешивать посторонние файлы в commit.

## 3. Правило временного override

Используется файл:

```text
framework-standardization/docs/CURRENT_OVERRIDE.md
```

Модель:

* файл существует — временный override действует;
* файла нет — override отсутствует;
* не использовать lifecycle-поля:

  * `Status:`
  * `Статус:`
  * `ACTIVE`
  * `RESOLVED`
* файл не коммитится;
* файл удаляется только после того, как решение:

  1. перенесено в постоянную документацию;
  2. проверено;
  3. соответствующий bounded step закрыт.

На момент закрытия voltage exception review решение перенесено в постоянные документы, а `CURRENT_OVERRIDE.md` удалён.

## 4. Последние стабильные коммиты

Последний подтверждённый log:

```text
896db38 Implement approved voltage normalization policy
5d6b410 Import legacy standardization decisions
3be362a Close hierarchical scope override
53b7570 Align pipeline hierarchical category scope
b6de7ad Add generic standardization review pipeline
```

### `896db38`

Завершил:

* approved voltage policy;
* рабочий voltage job contract;
* `VoltageNormalizer`;
* loader validation;
* strict contract enforcement;
* исправленную semantics `unchanged`;
* phase-voltage conflict handling;
* tests.

### `5d6b410`

Перенёс в framework решения из старого проекта:

```text
catalog-standardization
```

Добавлен постоянный слой:

```text
framework-standardization/docs/LEGACY_DECISIONS.md
```

### `53b7570` и `3be362a`

Исправили scope на иерархический через:

```text
oc_category_path
```

и закрыли временный override по hierarchical scope.

## 5. Источники решений и их приоритет

В проекте зафиксирована следующая логика доверия к источникам:

1. explicit human-approved решение по характеристике;
2. более новое explicit framework/user decision;
3. legacy project decisions;
4. live DB facts;
5. generated discovery/inventory/review evidence;
6. implementation defaults.

Live DB facts не должны самостоятельно отменять human-approved contract.

Generated package является evidence, но не apply authorization.

## 6. Legacy project

Старый проект:

```text
catalog-standardization
```

содержит:

```text
catalog-standardization/Catalog_Standardization.xlsx
catalog-standardization/CATALOG_STANDARD.md
catalog-standardization/PROJECT_MASTER_SUMMARY.md
catalog-standardization/HANDOFF.md
catalog-standardization/README.md
```

Legacy decisions были системно перенесены в framework в commit:

```text
5d6b410 Import legacy standardization decisions
```

Legacy files не изменять без отдельной причины.

## 7. Approved contract характеристики «Напряжение»

Canonical contract:

```text
canonical_attribute_id: 15
canonical_name: Напряжение (В)
canonical_unit: В
normalized_value_type: integer_enum
allowed_canonical_values: "220", "380"
```

Approved aliases:

```text
57
79
99
118
170
```

Excluded attribute:

```text
73
```

Причина исключения `73`:

```text
Параметры котла
```

Job:

```text
framework-standardization/config/jobs/submersible_pumps_voltage.php
```

В job используется явная модель:

```php
'target' => array(
    'canonical_attribute_id' => 15,
    'included_alias_attribute_ids' => array(57, 79, 99, 118, 170),
    'excluded_attribute_ids' => array(73),
),
```

Старая модель:

```text
candidate_attribute_ids
```

не используется как замена approved aliases.

## 8. Approved voltage normalization policy

Постоянный документ:

```text
framework-standardization/docs/VOLTAGE_NORMALIZATION_POLICY.md
```

Статус:

```text
Policy status: approved
Approved by: user
Approval date: 10-07-2026
```

### Класс 220

Нормализуются в `"220"`:

```text
220
220V
220 В
230
230 В
1 × 230 В
1x230 В
1~230 В
200–240 В
210–240 В
210..240
220–230 В
220-230 В
220–240 В
220-240 В
```

### Класс 380

Нормализуются в `"380"`:

```text
380
380V
380 В
400
400 В
3 × 400 В
3x400 В
3~400 В
380–400 В
380-400 В
380–420 В
380..420
```

### Фазность

Однофазные markers подтверждают класс `220`:

```text
1 ×
1x
1~
однофазный
однофазное
1 фаза
```

Трёхфазные markers подтверждают класс `380`:

```text
3 ×
3x
3~
трёхфазный
трехфазный
трёхфазное
трехфазное
3 фазы
```

Если phase evidence противоречит voltage class:

```text
status: review_required
canonical_value: null
warning: phase_voltage_class_conflict
ambiguity_reason: phase_voltage_class_conflict
```

Примеры конфликтов:

```text
3 × 230 В
230 В (трёхфазный)
1 × 400 В
400 В (однофазный)
```

### Frequency

Значения:

```text
50 Гц
60 Гц
```

не входят в canonical voltage value.

Они сохраняются в diagnostics, но не используются как voltage evidence.

### Mixed classes

Если строка содержит одновременно классы `220` и `380`:

```text
status: review_required
canonical_value: null
warning: mixed_voltage_classes
ambiguity_reason: mixed_voltage_classes
```

### Outside policy

Значения вроде:

```text
110
127
480
```

не преобразуются в `220` или `380`.

Результат:

```text
status: review_required
canonical_value: null
warning: voltage_outside_allowed_classes
ambiguity_reason: voltage_outside_allowed_classes
```

### Empty / unsupported

Пустое значение:

```text
status: invalid
```

Текст без voltage evidence:

```text
status: unsupported
```

## 9. Status semantics

`unchanged` допустим только если одновременно:

```text
source_attribute_id = 15
trim(raw_value) = "220" или "380"
canonical_value совпадает с raw
```

Примеры:

```text
attribute 15 + raw 220 → unchanged
attribute 15 + raw 380 → unchanged
attribute 15 + raw " 220 " → unchanged
```

Все другие успешно преобразованные значения:

```text
status: normalized
```

Alias row никогда не получает `unchanged`, даже если raw уже равен `220` или `380`.

Примеры:

```text
attribute 15 + 220V → normalized / 220
attribute 15 + 230 → normalized / 220
attribute 15 + 400 → normalized / 380
attribute 57 + 220 → normalized / 220
attribute 79 + 380 → normalized / 380
```

## 10. Contract type consistency и safety

Canonical enum хранится строками:

```php
'allowed_canonical_values' => array('220', '380'),
```

`StandardizationJobContractLoader`:

* приводит enum к строкам;
* удаляет внешние пробелы;
* блокирует пустые значения;
* блокирует дубли после type normalization;
* не содержит hardcode под `220/380`;
* проверяет пересечения canonical/aliases/excluded.

Pipeline строго проверяет каждый ненулевой normalizer output против:

```text
allowed_canonical_values
```

через strict comparison.

Если normalizer возвращает значение вне contract:

```text
status: review_required
canonical_value: null
warning: canonical_value_outside_contract
ambiguity_reason: canonical_value_outside_contract
```

Главный инвариант:

```text
canonical_value === null
или canonical_value === "220"
или canonical_value === "380"
```

## 11. Реализация

Основные файлы:

```text
framework-standardization/config/jobs/submersible_pumps_voltage.php
framework-standardization/src/Normalizer/VoltageNormalizer.php
framework-standardization/src/Pipeline/StandardizationJobContractLoader.php
framework-standardization/src/Pipeline/StandardizationPipeline.php
framework-standardization/tests/standardization_pipeline_static_checks.php
framework-standardization/docs/VOLTAGE_NORMALIZATION_POLICY.md
framework-standardization/docs/DECISIONS.md
```

Реализация находится в commit:

```text
896db38 Implement approved voltage normalization policy
```

Не нужно повторно проектировать или переписывать voltage normalizer без нового evidence.

Не добавлять product-specific exceptions в `VoltageNormalizer`.

Не hardcode product IDs в коде.

## 12. Tests

Доступный no-DB framework test suite:

```text
framework-standardization/tests/standardization_pipeline_static_checks.php
```

Запуск через PHP 5.6:

```powershell
C:\php56\php.exe framework-standardization/tests/standardization_pipeline_static_checks.php
```

Результат последней проверки:

```text
static_checks_completed: ok
```

Проверены:

* approved aliases;
* duplicate aliases;
* canonical ID inside aliases;
* alias/excluded overlap;
* string enum normalization;
* duplicate enum после type normalization;
* empty enum;
* все основные формы `220`;
* все основные формы `380`;
* mixed values;
* outside-policy values;
* phase conflicts;
* valid phase combinations;
* frequency parsing;
* arbitrary model numbers;
* `unchanged` semantics;
* strict output contract;
* global canonical invariant.

Для syntax использовать:

```text
C:\php56\php.exe
```

Bare `php` может ссылаться на `C:\php84\php.exe` и завершаться с `Access is denied`.

## 13. Последний успешный live DB read-only rerun

Команда:

```powershell
C:\php56\php.exe framework-standardization\bin\standardization-pipeline.php framework-standardization\config\jobs\submersible_pumps_voltage.php --format=markdown
```

Runtime:

```text
runtime_mode: live_db_readonly
database_name: he_framework_prod_snapshot_20260710
```

Package:

```text
framework-standardization/runtime/reports/submersible_pumps_voltage/20260710190844_6a51433c8f43
```

Package ID:

```text
20260710190844_6a51433c8f43
```

Scope:

```text
root category: 11900213
scope mode: hierarchical_category_path_exists
```

Volumes:

```text
source rows: 618
source products: 618
source attributes: 6
proposals total: 618
```

Counts по source attributes:

```text
15: 400
57: 117
79: 88
99: 5
118: 1
170: 7
```

Attribute `73` в package не вошёл.

Другие source attributes не вошли.

Scope diagnostics:

```text
hierarchical_scope_rows: 618
direct_parent_rows: 595
rows_without_direct_parent: 23
products_without_direct_parent: 23
```

Иерархические 23 товара без direct parent корректно включены.

## 14. Результаты последнего package

Status counts:

```text
unchanged: 98
normalized: 511
review_required: 8
invalid: 1
unsupported: 0
```

Safety invariants:

```text
non-null canonical values outside contract: 0
mixed values with non-null canonical value: 0
outside-policy values with non-null canonical value: 0
phase-conflict values with non-null canonical value: 0
alias rows marked unchanged: 0
```

Package не содержит:

* SQL apply;
* executed SQL;
* apply result;
* mutation result;
* production write;
* cache operation;
* `--confirm-apply`;
* UPDATE/INSERT/DELETE/ALTER/DROP/TRUNCATE.

SQL preview и apply-plan для напряжения не создавались.

## 15. Полный список исключений package

### `review_required`

```text
8192
8218
8219
8226
8227
8231
8233
8277
```

### `invalid`

```text
8243
```

### Причины

`8192`:

```text
1~230 В, 50 Гц / 1~220 В, 60 Гц / 1~110 В, 60 Гц* (номинальное)
```

Причина:

```text
voltage_outside_allowed_classes
```

`8218`, `8219`, `8226`, `8227`, `8231`, `8233`, `8277` содержат mixed `220/380` evidence.

Причина:

```text
mixed_voltage_classes
```

`8243` имеет пустое voltage value.

Причина:

```text
empty_value
```

## 16. Выполненный evidence review исключений

Был проведён отдельный read-only review девяти товаров.

Использовались:

* новый review package;
* read-only snapshot DB;
* product names;
* model/SKU;
* категории;
* атрибуты напряжения;
* phase/frequency evidence;
* короткие релевантные description snippets;
* внутренний поиск legacy/import evidence.

Интернет не использовался как источник решений.

Pipeline повторно не запускался.

DB не изменялась.

Созданы постоянные документы:

```text
framework-standardization/docs/VOLTAGE_EXCEPTION_REVIEW.md
framework-standardization/docs/VOLTAGE_MANUAL_RESOLUTION_PLAN.md
```

`VOLTAGE_EXCEPTION_REVIEW.md` переведён из draft/evidence state в approved review outcome.

## 17. Итог evidence review по товарам

### `8192`

Evidence:

* карточка DAVIS;
* перечислено несколько voltage/frequency executions;
* присутствуют `230`, `220` и `110`;
* точная версия SKU не доказана.

Решение:

```text
canonical: null
confidence: insufficient
keep_review_required
```

### `8218`

Evidence:

* описание содержит трёхфазную и однофазную версии;
* SKU-to-phase mapping не доказан.

Решение:

```text
canonical: null
confidence: insufficient
keep_review_required
```

### `8219`

Evidence:

* card name не подтверждает точную однофазную версию;
* описание перечисляет оба исполнения.

Решение:

```text
canonical: null
confidence: insufficient
keep_review_required
```

### `8226`

Evidence:

* обозначение модели указывает на вероятное однофазное исполнение;
* независимого подтверждения в description/import evidence нет.

Решение:

```text
canonical: null
confidence: medium
keep_review_required
```

Не утверждать `220` только на основании medium evidence.

### `8227`

Evidence:

* обозначение модели указывает на вероятное однофазное исполнение;
* независимого подтверждения нет.

Решение:

```text
canonical: null
confidence: medium
keep_review_required
```

### `8231`

Evidence:

* точная модель;
* description evidence подтверждает однофазное исполнение `220 В`.

Human-approved manual resolution:

```text
8231 → 220
confidence: high
```

### `8233`

Evidence:

* description evidence явно указывает однофазное исполнение `220 В`.

Human-approved manual resolution:

```text
8233 → 220
confidence: high
```

### `8277`

Evidence:

* название модели;
* raw value;
* description evidence подтверждают трёхфазное исполнение `380–415 В`;
* однофазная версия указана только как альтернативно доступная.

Human-approved manual resolution:

```text
8277 → 380
confidence: high
```

### `8243`

Evidence:

* voltage attribute пуст;
* найденное упоминание `380 В` является сравнительным;
* оно не доказывает напряжение точной версии товара.

Решение:

```text
canonical: null
confidence: insufficient
keep invalid
```

## 18. Явное human decision по исключениям

Пользователь утвердил консервативный evidence gate:

Автоматически или вручную разрешается планировать только high-confidence cases.

Approved manual resolutions:

```text
8231 → 220
8233 → 220
8277 → 380
```

Остаются unresolved:

```text
8192
8218
8219
8226
8227
```

Остаётся invalid:

```text
8243
```

Medium-confidence товары:

```text
8226
8227
```

не разрешены к изменению данных.

Это решение не является:

* SQL preview authorization;
* apply-plan authorization;
* SQL/apply authorization;
* production authorization.

## 19. Текущее незакоммиченное состояние

Последний фактически показанный `git status --short`:

```text
 M framework-standardization/config/runtime/local.dump.example.php
?? framework-standardization/docs/CURRENT_OVERRIDE.md
?? framework-standardization/docs/VOLTAGE_EXCEPTION_REVIEW.md
```

Перед любым продолжением нужно заново проверить:

```powershell
git status --short
```

Не считать приведённый выше status актуальным без свежего вывода.

### Отдельное пользовательское изменение

Файл:

```text
framework-standardization/config/runtime/local.dump.example.php
```

не относится к текущему шагу.

Его:

* не менять;
* не индексировать;
* не включать в commit;
* не использовать как повод для очистки working tree.

## 20. Закрытый documentation outcome

Постоянная документационная фиксация final exception decision выполнена локально.

Зафиксировано:

1. `VOLTAGE_EXCEPTION_REVIEW.md` переведён в approved review outcome.
2. В `DECISIONS.md` добавлено решение `10-07-2026 - Voltage exception review outcome`.
3. Создан:

   ```text
   framework-standardization/docs/VOLTAGE_MANUAL_RESOLUTION_PLAN.md
   ```
4. `CURRENT_OVERRIDE.md` удалён после переноса outcome.
5. SQL preview не создавался.
6. Apply-plan не создавался.
7. SQL/apply не выполнялся.
8. Product data не менялись.
9. Category assignments не менялись.
10. Production/cache не затрагивались.

## 21. Следующий рекомендуемый bounded step

Следующий шаг после проверки текущего diff должен быть отдельным bounded decision/planning step:

```text
определить следующий safe gate после voltage exception documentation outcome
```

Не выбирать SQL preview/apply-plan без отдельного explicit gate.

Не возвращать основной фокус к `max_head` без отдельного решения пользователя.

## 22. Запрещённые изменения следующего шага

Не менять:

```text
framework-standardization/config/*
framework-standardization/src/*
framework-standardization/tests/*
framework-standardization/docs/VOLTAGE_NORMALIZATION_POLICY.md
framework-standardization/docs/LEGACY_DECISIONS.md
framework-standardization/docs/HANDOFF.md
framework-standardization/docs/RUNTIME_CHECKS.md
framework-standardization/docs/RULES.md
framework-standardization/docs/START_HERE.md
framework-standardization/docs/DOCUMENTATION_BOUNDARIES.md
catalog-standardization/*
generated reports
```

Не подключаться к DB.

Не запускать pipeline.

Не создавать новый review package.

Не создавать SQL preview.

Не создавать executable apply-plan.

Не выполнять SQL/apply.

Не менять product/category data.

Не менять production/cache.

Не выполнять cache rebuild.

Не делать commit/push до проверки diff.

## 23. Ранее реализованный max_head path

В репозитории также существует более ранняя работа по:

```text
максимальный напор
```

Исторический contract:

```text
canonical attribute: 12
aliases: 101, 119, 81
normalizer: simple_meters
scope: 11900213
```

Ранее на controlled local dump были выполнены:

```text
hardcoded prototype canonical apply:
updated: 400
inserted: 81
```

И alias cleanup:

```text
deleted safely removable aliases: 81
remaining unresolved aliases: 14
```

Generic canonical write-path был реализован, но generic:

```text
--confirm-apply
```

не запускался.

Текущий local dump уже находится после prototype apply и alias cleanup, поэтому не подходит для source-based proof generic apply.

Этот path сейчас не является активным следующим шагом.

Не возвращаться к max_head apply, пока не закрыта текущая documentation-only фиксация voltage exceptions и не принято новое явное решение.

## 24. Более широкое направление после закрытия напряжения

Не нужно повторять всю voltage-процедуру вручную для каждой характеристики.

Напряжение было пилотной характеристикой, на которой одновременно были построены и проверены:

* hierarchical scope;
* legacy decision import;
* explicit contract;
* aliases/excluded model;
* enum validation;
* generic proposal statuses;
* strict output contract;
* review package;
* exception evidence review;
* human gate.

После закрытия документации по напряжению рекомендуется отдельный planning step:

```text
batch-автоматизация остальных характеристик категории Скважинные насосы
```

Целевой будущий workflow:

1. общий discovery всех характеристик;
2. общий raw-values inventory;
3. импорт legacy decisions;
4. классификация характеристик по типу;
5. привязка generic normalizers;
6. генерация и validation job contracts;
7. batch read-only runs;
8. единая сводка конфликтов;
9. human review только исключений;
10. отдельные apply gates.

Не начинать эту автоматизацию до аккуратного закрытия текущих voltage exception documents.

## 25. Начальный prompt для нового чата

```text
Подключись к GitHub repo `saitkharkov-cyber/home-energetika`.

Рабочая область: `framework-standardization`.

Сначала прочитай:

1. `framework-standardization/docs/START_HERE.md`;
2. документы далее в порядке, указанном в START_HERE;
3. актуальный HANDOFF;
4. `framework-standardization/docs/DECISIONS.md`;
5. `framework-standardization/docs/VOLTAGE_NORMALIZATION_POLICY.md`;
6. если существуют:
   - `framework-standardization/docs/CURRENT_OVERRIDE.md`;
   - `framework-standardization/docs/VOLTAGE_EXCEPTION_REVIEW.md`.

`docs/RULES.md` не читай. Ограничения рабочего шага будут даны отдельно.

Сначала не изменяй файлы.

Попроси меня выполнить:

`git status --short`

После свежего статуса подтверди:

1. текущий stable point;
2. текущую активную характеристику;
3. что уже завершено по voltage policy и implementation;
4. последний успешный live DB read-only package;
5. текущие исключения;
6. утверждённые manual resolutions;
7. какие документы ещё не зафиксированы;
8. какое отдельное пользовательское изменение нельзя трогать;
9. следующий один маленький documentation-only bounded step.

Пока не создавай SQL preview, apply-plan или implementation prompt.
```
