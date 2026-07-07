# Project Master Summary

Проект: **Framework Standardization**  
Репозиторий: `home-energetika`  
Папка проекта: `framework-standardization`

---

## Назначение проекта

Framework Standardization — это controlled attribute consolidation workflow для безопасной стандартизации характеристик каталога Home Energetika.

Framework Standardization не является fully automatic normalizer.

Основная цель:

* принимать target attribute meaning;
* выполнять DB-readonly attribute name discovery;
* показывать candidate list;
* требовать human canonical selection;
* требовать explicit include/exclude alias decision;
* собирать raw values inventory;
* фиксировать canonical unit / `normalized_value` contract;
* генерировать normalization proposals;
* передавать proposals в standalone review-chain;
* готовить apply-plan только отдельным explicit step после review.

Framework не объединяет похожие `attribute_name` автоматически. Похожие названия могут быть aliases, duplicates, similar-but-different или unsafe/unresolved candidates.

---

## Стартовые документы

Новый ChatGPT-чат / onboarding должен начинать с:

* `docs/START_HERE.md` — входной документ для нового ChatGPT-чата / onboarding.

Основные документы:

| Документ | Назначение |
| --- | --- |
| `docs/START_HERE.md` | Быстрый вход для нового ChatGPT-чата |
| `README.md` | Навигация по проекту |
| `PROJECT_MASTER_SUMMARY.md` | Сводка актуальной архитектурной модели |
| `docs/HANDOFF.md` | Оперативное состояние активной разработки |
| `docs/DECISIONS.md` | Архитектурные решения |
| `docs/RUNTIME_CHECKS.md` | История ручных проверок |
| `TECHNICAL_SPECIFICATION.md` | Исходное техническое задание |

Specialized specs в `docs/` описывают отдельные workflow gates, boundaries и standalone components.

---

## Актуальная workflow-модель

Актуальная цепочка:

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

Каждый переход является gate. Нельзя перескакивать сразу к fixture/source/job, parser implementation, SQL preview или apply plan.

---

## Human canonical selection

User/engineer вручную:

* задаёт target attribute meaning;
* выбирает canonical `attribute_id`;
* явно подтверждает included alias `attribute_ids`;
* явно исключает similar-but-different `attribute_ids`;
* просматривает unresolved/unsafe candidates;
* утверждает canonical unit / `normalized_value` contract;
* reviews proposals в standalone review-chain.

Framework не принимает canonical decision автоматически.

`approved` в review-chain означает только review status.

`approved` не означает SQL apply permission.

No auto-apply.

---

## Отношение к config/jobs

`config/jobs` не является стартовой точкой угадывания характеристики.

`config/jobs` может появляться только после:

* accepted canonical decision;
* completed raw values inventory;
* approved canonical unit / `normalized_value` contract;
* proposal generation model/spec.

Архитектурная модель:

* одна характеристика = один job/contract;
* один тип значений = один parser/normalizer family;
* новая характеристика не обязательно требует новый PHP handler.

Если value semantics уже покрыта существующим parser/normalizer family, отдельный обработчик под конкретный `attribute_id` не нужен.

---

## Standalone review-chain

Уже построена и остаётся полезной вторая половина workflow:

* raw values / proposals;
* review fixture generator;
* writer;
* manual review;
* loader;
* bridge;
* approval flow;
* result reporter.

Standalone review-chain должна получать proposals только после approved canonical unit / `normalized_value` contract.

Review-chain output не является SQL preview input by default и не является apply-ready output.

---

## Legacy / historical context

Ранние документы про `Attribute Job`, `AttributeContext`, `Attribute Pipeline`, `FrameworkResult`, stage contracts и SQL preview остаются historical/legacy context.

Они полезны как источник терминов и некоторых компонентных границ, но не являются актуальным next step.

Актуальная модель больше не начинается с Attribute Pipeline skeleton или `config/jobs`. Она начинается с target attribute meaning, DB-readonly discovery и human canonical selection.

---

## SQL / apply / production safety

Без отдельного explicit step запрещено:

* SQL preview by default;
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
* default dry-run path changes.

Apply-plan возможен только после review и отдельного explicit approval.

Selector/cache-related attributes require explicit canonical unit contract before implementation.

---

## Runtime model

Framework Standardization остаётся инженерным tooling layer внутри `framework-standardization`.

Он не является OpenCart-модулем и не является модулем админки OpenCart.

OpenCart / dump-derived data используются как readonly source для discovery, inventory и diagnostics.

---

## Текущий статус

Зафиксирована актуальная архитектура controlled attribute consolidation workflow.

Уже задокументированы обязательные pre-review/pre-contract/pre-proposal gates:

* attribute name discovery / canonical selection;
* raw values inventory;
* canonical unit / `normalized_value` contract;
* normalization proposals generation;
* standalone review-chain boundary.

Старый next step про минимальный implementation skeleton `StageInterface`, `PipelineEngine`, DTO и 9 пустых stages больше не является актуальным направлением.

---

## Следующий direction

Следующий direction:

implementation spec для первого DB-readonly attribute name discovery command/tool.

Только после отдельного explicit `+`.

Future tool должен показывать candidates:

* `attribute_id`;
* `attribute_name`;
* `usage_count`;
* optional category coverage;
* short raw samples preview;
* warnings;
* reason found;
* possible role:
  * canonical candidate;
  * possible alias / duplicate;
  * similar but different;
  * unsafe / unresolved.

---

## Правило работы

Работа ведётся маленькими шагами.

Порядок:

* analysis/spec;
* architecture decision;
* explicit approval;
* bounded implementation;
* runtime checks only when requested;
* documentation update when needed.

Код, config/jobs, SQL/apply и production/cache actions не выполняются до утверждения соответствующего architecture decision и explicit approval.
