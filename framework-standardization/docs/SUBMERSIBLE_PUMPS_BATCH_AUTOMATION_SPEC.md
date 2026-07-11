# SUBMERSIBLE_PUMPS_BATCH_AUTOMATION_SPEC

Дата: 11-07-2026

Specification status: approved

Approved by: user

Approval date: 11-07-2026

Approval scope: planning and safety boundaries for future batch preparation workflow

Documentation only

Not an implementation authorization

Not a DB authorization

Not an apply authorization

## 1. Назначение

Этот документ фиксирует scope и safety-границы возможной будущей batch-автоматизации для остальных характеристик категории `Скважинные насосы`.

Root category:

```text
11900213
```

Scope mode:

```text
hierarchical_category_path_exists
```

Цель не состоит в полностью автоматической стандартизации и не состоит в автоматическом изменении данных.

Цель будущего batch workflow:

```text
сократить повторяющуюся техническую работу при подготовке остальных характеристик категории Скважинные насосы, сохранив human approval для всех семантических решений
```

`Напряжение` используется как пилотный reference workflow для contracts, normalizer policy, read-only package generation, exception review и human gates. Этот документ не меняет voltage policy, `VoltageNormalizer`, voltage job contracts или voltage exception outcome.

Старый `max_head` path не становится активным apply-направлением. Он не должен использоваться как основание для SQL/apply в этом spec.

## 2. Будущий read-only preparation workflow

Будущая автоматизация может готовить и оркестрировать read-only evidence work для нескольких характеристик. Автоматизировать разрешается только повторяющуюся техническую работу вокруг сбора evidence и отчётности.

Допустимые будущие preparation stages:

1. Общий discovery характеристик в hierarchical category scope.
2. Общий raw-values inventory.
3. Сопоставление найденных характеристик с утверждёнными legacy decisions.
4. Формирование реестра характеристик.
5. Предварительная классификация по типу обработки.
6. Определение, существует ли подходящий generic normalizer.
7. Генерация draft contract proposals.
8. Validation утверждённых job contracts.
9. Batch read-only запуск только для характеристик с approved contract.
10. Единая сводка status, conflicts и review-required cases.
11. Подготовка human review queue.
12. Отдельное хранение evidence и human decisions.

Обязательные границы:

- Discovery candidate не становится alias автоматически.
- Найденный похожий attribute не становится canonical автоматически.
- Тип характеристики не утверждается автоматически.
- Canonical unit не выбирается автоматически.
- Normalizer не назначается автоматически без contract gate.
- Generated proposal не является human approval.
- Approved review outcome не является apply authorization.

## 3. Минимальная единица обработки

Семантические решения принимаются на уровне одной характеристики.

Техническое выполнение может быть batch только после прохождения per-characteristic gates.

Для каждой характеристики должен существовать отдельный explicit contract:

- target meaning;
- canonical attribute;
- approved aliases;
- excluded attributes;
- hierarchical category scope;
- canonical unit или enum;
- normalized value contract;
- normalizer;
- safety policy;
- expected statuses;
- review rules.

Batch orchestration не должна скрывать, объединять или заменять эти per-characteristic contracts.

## 4. Proposed status marker model

Модель ниже не является одним взаимоисключающим enum.

Это набор независимых status markers по нескольким измерениям. Одна характеристика может одновременно иметь markers `contract_approved` и `normalizer_ready`.

Batch orchestration не должна сводить разные измерения в один неоднозначный state.

### Discovery / contract status

| marker | значение |
| ------ | -------- |
| `discovered` | Характеристика найдена, но semantic decisions ещё отсутствуют. |
| `contract_required` | Требуется human semantic decision перед contract drafting или processing. |
| `contract_draft` | Draft contract подготовлен, но не утверждён. |
| `contract_approved` | Contract явно утверждён human decision. |
| `blocked` | Продолжение contract work небезопасно или недостаточно evidence. |

### Normalizer status

| marker | значение |
| ------ | -------- |
| `normalizer_required` | Подходящий normalizer отсутствует для утверждённого contract. |
| `normalizer_ready` | Normalizer существует и проверен против approved contract. |
| `blocked` | Normalizer work заблокирован из-за safety issue, отсутствующего contract или недостаточного evidence. |

### Processing / review status

| marker | значение |
| ------ | -------- |
| `read_only_ready` | Read-only batch run допустим для этой характеристики после выполнения Gate 3. |
| `review_required` | Generated package содержит exceptions, conflicts или incomplete evidence, требующие human review. |
| `blocked` | Processing или review продолжать небезопасно. |

`read_only_ready` допустим только при одновременном наличии markers:

```text
contract_approved
normalizer_ready
```

`blocked` может относиться к конкретному измерению или блокировать всю характеристику.

После read-only run `review_required` может сосуществовать с сохранёнными markers утверждённого contract и готового normalizer.

Ни один marker в этой модели не означает SQL/apply, production readiness, cache readiness или safe-to-apply.

## 5. Human gates

### Gate 1 - Characteristic registry review

Human проверяет:

- смысл характеристики;
- актуальность;
- дубли;
- исключённые или нерелевантные характеристики.

### Gate 2 - Contract approval

Human утверждает:

- canonical attribute;
- aliases;
- exclusions;
- unit или enum;
- normalized value contract;
- normalizer class или необходимость его разработки.

### Gate 3 - Read-only batch authorization

Read-only batch authorization разрешён только при одновременном наличии markers:

```text
contract_approved
normalizer_ready
```

После разрешённого run характеристика может получить:

```text
read_only_ready
```

### Gate 4 - Exception review

Human рассматривает:

- `review_required`;
- `invalid`;
- `unsupported`;
- conflicts;
- incomplete evidence.

### Gate 5 - Apply planning

Apply planning является отдельным будущим explicit decision.

Этот spec не разрешает Gate 5.

## 6. Proposed future outputs

Будущая реализация может концептуально создавать:

- characteristic registry;
- per-characteristic state summary;
- draft contract proposals;
- approved contract manifest;
- batch run manifest;
- aggregated status report;
- conflict registry;
- human review queue;
- provenance links на legacy decisions и generated evidence.

Этот spec не утверждает финальный machine-readable format.

Этот spec не создаёт config, PHP schema, JSON fixtures или executable manifests.

## 7. Safety invariants

Обязательные safety invariants:

- Target scope — hierarchical category path от root category `11900213`.
- Никакого auto-canonical selection.
- Никакого auto-alias approval.
- Никакого автоматического изменения approved legacy decisions.
- Никакого угадывания единиц измерения.
- Никаких product-specific решений внутри generic normalizers.
- Никаких hardcoded product IDs в generic code.
- Unresolved cases не преобразуются автоматически.
- Batch failure одной характеристики не должен разрешать continuation apply для остальных характеристик.
- Generated reports являются evidence.
- Каждый apply gate остаётся per-characteristic или отдельно явно утверждённым batch apply gate.
- Product/category data не меняются.
- Production/cache не затрагиваются.
- Cache rebuild запрещён.
- SQL generation и SQL files не входят в первую версию.
- `--confirm-apply` не входит в первую версию.

## 8. Scope первой будущей реализации

Первая реализация после утверждения этого spec должна быть ещё одним отдельным bounded step.

Её scope должен быть только:

```text
read-only characteristic registry builder
```

Будущая задача этой первой реализации:

- собрать список характеристик в hierarchical scope;
- сопоставить характеристики с legacy decisions;
- показать текущий proposed state;
- ничего не нормализовать;
- не создавать job contracts автоматически как approved;
- не запускать per-characteristic pipelines;
- не создавать SQL/apply artifacts.

Этот spec не проектирует PHP classes, CLI options или структуру config-файлов детально.

## 9. Out of scope

Этот spec не разрешает:

- изменение voltage policy;
- изменение `VoltageNormalizer`;
- применение manual resolutions `8231`, `8233`, `8277`;
- обработку unresolved voltage products;
- исправление product `8243`;
- возврат к `max_head` apply;
- DB connection;
- pipeline execution;
- создание review packages;
- SQL preview;
- executable apply-plan;
- SQL/apply;
- production/cache actions;
- cache rebuild;
- OpenCart runtime integration;
- automatic generation и commit runtime artifacts.

## 10. Boundary summary

Это documentation-only planning/spec step.

Документ определяет, что будущий batch preparation workflow может делать и что он не должен делать.

Он не меняет существующие decisions, contracts, normalizers, jobs, runtime configs, data, generated reports или cache.
