# Handoff - Framework Standardization

Дата: 07.07.2026

Проект: HmEnerg_Характеристики / Home Energetika / Framework Standardization  
Репозиторий: `saitkharkov-cyber/home-energetika`  
Рабочая папка: `framework-standardization`  
Локальный путь пользователя: `D:\Git\home-energetika`

## 1. Текущая стабильная точка

`e128e61 Add framework standardization glossary`

Previous onboarding refresh point:

`f2fdaa6 Update framework standardization handoff after onboarding refresh`

Ожидаемое состояние для следующего чата: HEAD/main/origin/main соответствует `e128e61`, working tree clean.

В новом чате сначала сверить `git status --short` и `git log --oneline --decorate -5`. Если HEAD отличается, сверить фактическую точку с `docs/START_HERE.md`, `docs/DECISIONS.md` и `docs/RUNTIME_CHECKS.md`.

## 2. Текущий статус навигации

Onboarding/navigation обновлены:

* `docs/START_HERE.md` создан как входной документ для нового ChatGPT-чата;
* `README.md` ссылается на `docs/START_HERE.md`;
* корневой `PROJECT_MASTER_SUMMARY.md` знает про Framework Standardization;
* `framework-standardization/PROJECT_MASTER_SUMMARY.md` обновлён под controlled attribute consolidation workflow.

`HANDOFF.md` остаётся оперативным состоянием, не changelog и не замена `START_HERE.md`.

## 3. Актуальная архитектура

Framework Standardization = controlled attribute consolidation workflow, not fully automatic normalizer.

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

## 4. Standalone review-chain

Уже построена и остаётся полезной вторая половина workflow:

* raw values / proposals;
* review fixture generator;
* writer;
* manual review;
* loader;
* bridge;
* approval flow;
* result reporter.

Standalone review-chain должна получать proposals только после:

* canonical attribute group selected;
* raw values inventory completed;
* canonical unit / `normalized_value` contract approved;
* normalization proposals generation completed.

`approved` в review-chain означает только review status. `approved` не означает SQL apply permission.

## 5. Paused path

Не продолжать immediate `pump_max_head` fixture/source/job.

`pump_max_head` остаётся useful candidate/example, но перед fixture/config/jobs обязательны:

* discovery;
* canonical selection;
* raw values inventory;
* approved unit/contract;
* proposals generation.

## 6. Safety / boundaries

Короткие обязательные границы:

* no auto-merge;
* no auto-canonical selection;
* no `config/jobs` as starting point;
* no SQL preview/generation/apply by default;
* no apply-plan without separate explicit step;
* no production/cache changes;
* no cache rebuild;
* approved in review-chain does not mean SQL apply permission.

Без отдельного explicit step также запрещены:

* PHP implementation;
* config/jobs changes;
* pipeline wiring;
* runner integration;
* live DB / production DB;
* DB/schema changes;
* write/schema operations;
* runtime artifacts;
* committed runtime artifacts;
* default dry-run path changes.

## 7. Next direction

Следующий direction:

`implementation spec для первого DB-readonly attribute name discovery command/tool`

Только после separate explicit `+`.

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

Это direction, не готовый Codex prompt.

## 8. Не делать следующим шагом

Не делать:

* immediate `pump_max_head` fixture/source/job;
* implementation без отдельного explicit `+`;
* parser/normalizer implementation;
* config/jobs changes;
* pipeline/runners;
* SQL preview/generation/apply;
* live DB;
* production/cache changes;
* cache rebuild;
* DB/schema changes;
* runtime artifacts.
