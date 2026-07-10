# LEGACY_DECISIONS — перенесённые решения legacy-проекта

Документ хранит durable provenance и утверждённые предметные решения старого проекта `catalog-standardization`, которые должны учитываться в `framework-standardization`.

Этот документ не является:

* handoff;
* runtime report;
* generated inventory;
* current override;
* implementation specification;
* доказательством выполненного SQL/apply.

Он различает:

* утверждённый legacy contract;
* evidence о выполненной миграции;
* newly discovered candidate;
* текущее implementation behavior;
* текущее состояние данных в live DB.

## 1. Authoritative legacy sources

| source | purpose | authority type | last commit | commit date | Git blob SHA | notes |
| ------ | ------- | -------------- | ----------- | ----------- | ------------ | ----- |
| `catalog-standardization/Catalog_Standardization.xlsx` | реестр внедрения стандарта | human-approved source для характеристик | `0043d72562c9232ed38dfeee429d87f2fa26a87c` | `2026-06-30T16:47:02+03:00` | `61d93ed7dce3de044cecd98159d57457149a8e1e` | главный источник characteristic-level contract facts |
| `catalog-standardization/CATALOG_STANDARD.md` | нормативная модель стандарта каталога | human-approved normative source | `9ccd645c2b7d9714ded6c57b9de7caf3c86eecb1` | `2026-06-30T11:30:14+03:00` | `fe901e413fcff6a6a32fc2c91161f4b8847c12b9` | задаёт общие правила canonical values |
| `catalog-standardization/PROJECT_MASTER_SUMMARY.md` | summary архитектуры legacy-проекта | supporting decision context | `9ccd645c2b7d9714ded6c57b9de7caf3c86eecb1` | `2026-06-30T11:30:14+03:00` | `961de881307177cec0e0e50d3b6cabc747d289a4` | описывает процесс и назначение источников |
| `catalog-standardization/HANDOFF.md` | оперативный статус legacy-проекта | operational evidence | `9ccd645c2b7d9714ded6c57b9de7caf3c86eecb1` | `2026-06-30T11:30:14+03:00` | `21f02e96c50745aecd06efd72492efb3cb74875e` | может подтверждать implementation/apply state, но не заменяет contract |
| `catalog-standardization/README.md` | порядок чтения legacy-проекта | supporting context | `9ccd645c2b7d9714ded6c57b9de7caf3c86eecb1` | `2026-06-30T11:30:14+03:00` | `6a5ded04fe321896d01182bc99f5efe7ce5482d6` | объясняет роль `Catalog_Standardization.xlsx` |

Generated CSV, SQL, reports и scripts из `catalog-standardization` являются evidence или implementation artifacts. Они не являются самостоятельным contract authority и не должны заменять human-approved decisions.

## 2. Source hierarchy

Приоритет источников:

1. Явное human-approved решение для конкретной характеристики.
2. Более новое явное решение пользователя или framework decision.
3. Нормативные общие правила legacy-проекта.
4. Текущие live DB facts.
5. Generated discovery, inventory и review packages.
6. Implementation defaults и текущее поведение normalizer.

Уточнения:

* implementation behavior никогда не отменяет approved contract;
* новый discovery candidate не становится alias автоматически;
* live DB показывает текущее состояние данных, но не переопределяет утверждённый стандарт;
* generated SQL, CSV и reports являются evidence, а не самостоятельным источником решения;
* при реальном конфликте двух human-approved источников решение не выбирается автоматически;
* более конкретное решение по характеристике обычно имеет больший вес, чем общее правило, но явный конфликт всё равно должен быть показан человеку.

## 3. Legacy contract register

| characteristic | legacy contract status | canonical attribute | canonical name | approved aliases/duplicates | unit | value type | allowed canonical values/rules | migration direction | implementation/apply evidence | unresolved |
| -------------- | ---------------------- | ------------------- | -------------- | --------------------------- | ---- | ---------- | ------------------------------ | ------------------- | ----------------------------- | ---------- |
| Напряжение | `2 / Готово` | `15` | `Напряжение (В)` | `57` | `В` | `Целое число` | `220`, `380` | `57 -> 15` | legacy handoff lists voltage as standardized; this does not by itself prove every current live DB row is migrated | normalization mapping policy for non-canonical raw forms must be approved separately |
| Максимальный напор | `2 / Готово` | `12` | `Максимальный напор (м)` | not specified in Excel | `м` | `Целое число / дробное число` | `Любое число > 0`; дробные паспортные значения допускаются | not specified | legacy handoff lists max head as standardized | framework-discovered IDs are new discovery candidates, not legacy-approved aliases |
| Максимальная производительность | `2 / Готово` | `13` | `Максимальная производительность (л/мин)` | `76`, `100`, `107`, `121` | `л/мин` | `Целое число` | `Любое целое число > 0` | unresolved in Excel | legacy handoff lists max flow as standardized; generated SQL is evidence only | migration direction must not be inferred automatically from duplicate IDs |
| Диаметр насоса | `2 / Готово` | `44` | `Диаметр насоса (мм)` | not specified in Excel | `мм` | `Целое число / дробное число` | `Любое число > 0` | not specified | legacy handoff may indicate implementation or verification was still a next step | implementation/apply state requires separate verification |

## 4. Напряжение

### Утверждено legacy

* characteristic: `Напряжение`;
* legacy status: `2 / Готово`;
* canonical attribute: `15`;
* canonical name: `Напряжение (В)`;
* unit: `В`;
* value type: `Целое число`;
* allowed canonical values: `220`, `380`;
* approved duplicate attribute: `57`;
* migration direction: `57 -> 15`.

Итоговое canonical значение может быть только `220` или `380`.

Canonical attribute `15` и duplicate/migration source `57` не должны заново выбираться через discovery.

### Не следует автоматически из Excel

Excel фиксирует allowed canonical values и migration direction, но не доказывает сами правила преобразования raw values.

Пока не считать утверждёнными правила:

* `230 -> 220`;
* `400 -> 380`;
* `220240 -> 220`;
* `380420 -> 380`.

Это отдельная normalization policy. Она должна быть зафиксирована explicit human decision перед изменением `VoltageNormalizer` или machine-readable contract.

### Framework discovery classification

| attribute_id | classification |
| ------------ | -------------- |
| `15` | approved legacy canonical |
| `57` | approved legacy duplicate / migration source |
| `73` | framework-excluded discovered candidate; не legacy alias |
| `79` | newly discovered candidate; не approved legacy alias |
| `99` | newly discovered candidate; не approved legacy alias |
| `118` | newly discovered candidate; не approved legacy alias |
| `170` | newly discovered candidate; не approved legacy alias |

`79`, `99`, `118` и `170` нельзя считать aliases только потому, что они найдены текущим framework discovery или включены в exploratory job.

## 5. Максимальный напор

Подтверждённые legacy facts:

* legacy status: `2 / Готово`;
* canonical attribute: `12`;
* canonical name: `Максимальный напор (м)`;
* unit: `м`;
* value type: `Целое число / дробное число`;
* allowed value rule: `Любое число > 0`;
* note: допускаются дробные паспортные значения, например `46.5 м`.

IDs, найденные framework в текущем workflow, но отсутствующие в Excel, должны классифицироваться как:

`new discovery candidates, not legacy-approved aliases`

Их нельзя утверждать aliases в этом документе.

## 6. Максимальная производительность

Подтверждённые legacy facts:

* legacy status: `2 / Готово`;
* canonical attribute: `13`;
* canonical name: `Максимальная производительность (л/мин)`;
* unit: `л/мин`;
* value type: `Целое число`;
* allowed value rule: `Любое целое число > 0`;
* duplicate IDs: `76`, `100`, `107`, `121`.

Migration direction в Excel не заполнен.

Нельзя автоматически выводить migration direction из списка duplicate IDs.

Generated SQL не является заменой human-approved migration rule.

## 7. Диаметр насоса

Excel подтверждает contract decision state:

* legacy status: `2 / Готово`;
* canonical attribute: `44`;
* canonical name: `Диаметр насоса (мм)`;
* unit: `мм`;
* value type: `Целое число / дробное число`;
* allowed value rule: `Любое число > 0`.

Legacy handoff может означать, что implementation / migration / verification state ещё не завершён или был следующим этапом.

Это не отменяет legacy canonical contract автоматически.

Implementation/apply state требует отдельной проверки и не должен выводиться только из status `Готово` в Excel.

## 8. Transfer rules for framework-standardization

Перед discovery, contract drafting или normalizer work по характеристике, присутствующей в этом документе, нужно:

1. проверить approved legacy contract;
2. отделить approved aliases от newly discovered candidates;
3. проверить текущую live DB только read-only;
4. не расширять contract автоматически по discovery;
5. не считать normalizer behavior contract source;
6. не разрешать SQL/apply без отдельного explicit gate.
