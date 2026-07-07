# Start Here — Framework Standardization

Дата: 2026-07-07

## 1. Purpose

Этот документ — короткий вход для нового чистого ChatGPT-чата по проекту `HmEnerg_Характеристики / framework-standardization`.

Источник истины остаётся в repo-документации. Master summary из чата не должен заменять GitHub/repo documents.

`START_HERE.md` не является changelog и не заменяет `HANDOFF.md`.

## 2. Current stable point

Текущая стабильная точка:

`f18d173 Update framework standardization handoff`

## 3. Read order for a new ChatGPT chat

Новый ChatGPT должен читать документы в таком порядке:

1. `docs/START_HERE.md`
2. `docs/HANDOFF.md`
3. `docs/DECISIONS.md`
4. `docs/RUNTIME_CHECKS.md`
5. актуальные specs из `docs/`, если они нужны для конкретного шага

`docs/RULES.md` предназначен для ChatGPT-процесса. ChatGPT должен соблюдать rules.

Codex не должен получать задачу вида "соблюдай RULES.md". Если ограничение важно для Codex, ChatGPT должен явно включить это ограничение в prompt.

## 4. Current architecture

Framework standardization = controlled attribute consolidation workflow, not fully automatic normalizer.

Актуальная цепочка:

* target attribute meaning
* DB-readonly attribute name discovery
* candidate list
* human canonical selection
* explicit include/exclude alias decision
* raw values inventory
* canonical unit / `normalized_value` contract
* normalization proposals generation
* standalone review-chain
* separate explicit apply-plan

Framework не должен автоматически объединять похожие характеристики только по названию.

## 5. What is already useful

Уже построена и остаётся полезной вторая половина workflow:

* raw values / proposals
* review fixture generator
* writer
* manual review
* loader
* bridge
* approval flow
* result reporter

Standalone review-chain должна получать proposals только после approved canonical unit / `normalized_value` contract.

`approved` в review-chain не означает SQL apply permission.

## 6. Do not continue the paused path

Не продолжать immediate `pump_max_head` fixture/job.

`pump_max_head` остаётся useful candidate/example, но перед fixture/config/jobs обязательны:

* discovery;
* canonical selection;
* raw values inventory;
* approved unit/contract;
* proposals generation.

## 7. Production safety

На production был cache hotfix для Belamos/Pedrollo `max_flow_l_min`.

Cache rebuild восстановил старые flow values в `m/h`.

Правила:

* no cache rebuild without separate explicit approval;
* flow/performance attributes не трогать без permanent flow normalization;
* selector/cache-related attributes require explicit canonical unit contract before implementation;
* unit semantics нельзя угадывать автоматически.

## 8. Operating rules for the new ChatGPT chat

ChatGPT выбирает следующий маленький step. Не спрашивать Codex "что дальше?".

Implementation только после explicit `+`.

Codex получает конкретный bounded prompt: какие файлы читать, какой один файл менять/создавать, что запрещено, какие проверки выполнять или не выполнять.

Codex обычно не делает commit. После отчёта Codex ChatGPT проверяет scope и даёт commit command отдельно.

`HANDOFF.md` обновлять только при закрытии, паузе, переносе или существенной смене stable point.

PowerShell commands давать одной строкой.

PHP checks выполнять через `C:\php56\php.exe`.

## 9. Current next direction

Следующий direction:

implementation spec для первого DB-readonly attribute name discovery command/tool.

Только после отдельного explicit `+`.

Future tool должен показывать candidates:

* `attribute_id`
* `attribute_name`
* `usage_count`
* optional category coverage
* short raw samples preview
* warnings
* reason found
* possible role:
  * canonical candidate
  * possible alias / duplicate
  * similar but different
  * unsafe / unresolved

## 10. Boundaries for this START_HERE step

Этот документ не содержит готовый Codex prompt.

Этот документ не является полным changelog.

Этот документ не дублирует полностью `HANDOFF.md`.

Этот документ не добавляет runtime checks.

Этот документ не добавляет PHP implementation instructions.
