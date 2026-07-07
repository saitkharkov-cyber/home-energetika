# Handoff — Framework Standardization

Дата: 07.07.2026

Проект: HmEnerg_Характеристики / Home Energetika / Framework Standardization  
Репозиторий: `saitkharkov-cyber/home-energetika`  
Рабочая папка: `framework-standardization`  
Локальный путь пользователя: `D:\Git\home-energetika`

## 1. Текущая стабильная точка

Текущая стабильная точка:

`a8396a3 Document normalization proposals review-chain bridge decision`

Ожидаемое состояние для следующего чата:

* HEAD/main/origin/main соответствует `a8396a3`;
* working tree clean.

В новом чате сначала проверить:

```text
git status --short
git log --oneline --decorate -5
```

Если HEAD отличается от `a8396a3`, сначала сверить фактическую рабочую точку с `docs/DECISIONS.md` и `docs/RUNTIME_CHECKS.md`.

## 2. Главный архитектурный разворот

Framework standardization теперь зафиксирован как:

```text
controlled attribute consolidation workflow
```

а не как:

```text
fully automatic normalizer
```

Framework не должен сам автоматически объединять похожие характеристики только по названию.

Human canonical selection обязательна.

`config/jobs` не является стартовой точкой угадывания характеристики. `config/jobs` должен быть результатом accepted canonical decision/contract.

## 3. Актуальная workflow-модель

Актуальная модель:

```text
target attribute meaning
-> DB-readonly attribute name discovery
-> candidate list
-> human canonical selection
-> explicit include/exclude alias decision
-> raw values inventory
-> canonical unit / normalized_value contract
-> normalization proposals generation
-> standalone review-chain
-> отдельный explicit apply-plan
```

Каждый переход является gate. Нельзя перескакивать сразу к fixture/source/job, parser implementation, SQL preview или apply plan.

## 4. Уже реализованная вторая половина workflow

Ранее уже построена и остаётся полезной standalone review-chain:

```text
raw values / proposals
-> review fixture generator
-> writer
-> manual review
-> loader
-> bridge
-> approval flow
-> result reporter
```

Эта chain остаётся standalone-only и должна получать proposals только после:

* canonical attribute group selected;
* raw values inventory completed;
* canonical unit / `normalized_value` contract approved;
* normalization proposals generation completed.

`approved` в review-chain означает только review status.

`approved` не означает SQL apply permission.

Apply plan возможен только отдельным explicit step после review.

## 5. Что зафиксировано сегодня

Сегодняшний блок документов/decisions:

* `c185c76` — controlled attribute consolidation workflow decision;
* `c51dc21` — spec для attribute name discovery / canonical selection;
* `a11f123` — decision: attribute discovery/canonical selection as first pre-review gate;
* `3809b47` — spec для raw values inventory;
* `56c5967` — decision: raw values inventory as pre-contract gate;
* `9828fb1` — spec для canonical unit / normalized_value contract;
* `befef6d` — decision: canonical unit contract as pre-proposal gate;
* `b9a78de` — spec для normalization proposals generation;
* `a8396a3` — decision: normalization proposals generation as bridge to standalone review-chain.

Смысл блока:

* сначала target meaning и DB-readonly discovery;
* затем human canonical selection и explicit include/exclude aliases;
* затем raw values inventory;
* затем approved canonical unit / `normalized_value` contract;
* затем deterministic diagnostic-only proposals;
* только после этого standalone review-chain.

## 6. Важный rejected/paused path

Не продолжать прежнюю ветку как немедленное создание fixture/source/job для `pump_max_head`.

`pump_max_head` остаётся полезным candidate/example:

* `attribute_id = 12`;
* `attribute_name = Максимальный напор`;
* canonical key: `pump_max_head`;
* canonical unit example: `m`;
* `normalized_value` example: decimal meters.

Но implementation для `pump_max_head` сейчас не следующий шаг.

Перед любыми fixture/config/jobs нужна первая половина workflow:

* discovery;
* canonical selection;
* raw values inventory;
* approved unit/contract;
* proposals generation.

## 7. Production safety note

На production был временный cache hotfix для Belamos/Pedrollo `max_flow_l_min`.

Production rebuild восстановил старые flow values в шкале `m/h`.

Следствия:

* flow/performance attributes не трогать без permanent flow normalization;
* не запускать cache rebuild без отдельного explicit approval;
* любые selector/cache-related attributes требуют explicit canonical unit contract before implementation;
* unit semantics нельзя угадывать автоматически;
* approved proposals не должны автоматически менять DB/cache.

## 8. Главные правила и границы

Обязательные правила:

* `config/jobs` не стартовая точка угадывания характеристики;
* `config/jobs` должен быть результатом accepted canonical decision/contract;
* одна характеристика = один job/contract;
* один тип значений = один parser/normalizer family;
* новая характеристика не обязательно требует новый PHP handler;
* human canonical selection обязательна;
* explicit include/exclude alias decision обязателен;
* raw values inventory обязателен перед unit/contract;
* canonical unit / `normalized_value` contract обязателен перед proposals;
* proposal generation является bridge к review-chain;
* `approved` в review-chain не означает SQL apply permission;
* no auto-apply;
* apply-plan только отдельным explicit step;
* no production/cache changes;
* no cache rebuild.

Запрещено без отдельного explicit step:

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
* runtime artifacts;
* committed runtime artifacts;
* default dry-run path changes.

Запрещённые operation families:

* `INSERT`
* `UPDATE`
* `DELETE`
* `REPLACE`
* `ALTER`
* `DROP`
* `TRUNCATE`
* `CREATE`

## 9. Рекомендованный следующий шаг

Следующий шаг в новом чате должен быть не implementation, а выбор следующего маленького spec/decision/implementation в новой архитектуре.

Рекомендуемое направление:

```text
implementation spec для первого DB-readonly attribute name discovery command/tool
```

Только после отдельного explicit `+`.

Будущий tool должен принимать target meaning / controlled scope и показывать candidates:

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

В HANDOFF это только направление. Готовый implementation prompt здесь намеренно не фиксируется.

## 10. Не делать следующим шагом

Не делать:

* сразу fixture/source/job для `pump_max_head`;
* implementation без отдельного explicit `+`;
* parser/normalizer implementation;
* config/jobs changes;
* pipeline wiring;
* runner integration;
* SQL preview integration;
* SQL generation/apply;
* live DB;
* production/cache changes;
* cache rebuild;
* DB/schema changes;
* обновлять другие документы без отдельного задания.
