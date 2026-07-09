# HANDOFF — framework-standardization

Дата: 09.07.2026

## Текущая стабильная точка

Текущий stable point:

`88761da Document generic controlled attribute apply direction`

Рабочее дерево после коммита должно быть чистым.

Последняя цепочка коммитов:

```text
88761da Document generic controlled attribute apply direction
e69f191 Document transactional max head local apply check
3e2e0e8 Enable transactional max head local apply
9213a24 Add bounded max head apply command shell
81b9961 Document bounded max head apply command spec
e984a0c SESSION END HANDOFF WRITE
```

## Контекст проекта

Репозиторий:

`saitkharkov-cyber/home-energetika`

Рабочий проект:

`framework-standardization`

Текущий workflow строится вокруг безопасной стандартизации характеристик через последовательные gate.

Главная модель процесса:

```text
target attribute meaning
→ DB-readonly attribute name discovery
→ human canonical selection
→ explicit include/exclude alias decision
→ raw values inventory
→ canonical unit / normalized_value contract
→ range policy
→ normalization proposals
→ review-chain
→ sample review
→ human review decision
→ SQL preview
→ SQL preview review
→ apply-plan preview
→ apply-plan preview review
→ apply readiness decision
→ bounded/generic apply command with explicit confirmation
→ post-apply verification
```

## Важные правила взаимодействия

- Работаем маленькими bounded steps.
- После каждого шага: diff/check → commit → push → clean status.
- Не использовать stash без крайней необходимости.
- Не спрашивать Codex “что дальше”.
- Следующий шаг определяет ChatGPT вместе с пользователем.
- Codex получает только конкретный bounded prompt.
- Не предлагать implementation без explicit `+` от пользователя.
- docs/RULES.md должен читать и соблюдать ChatGPT.
- Codex не должен получать docs/RULES.md как входной документ.
- Если правило важно для Codex-step, ChatGPT явно включает его в prompt.
- Не делать production/cache изменения.
- Не делать cache rebuild.
- Не делать SQL apply без отдельного явного gate.
- Не делать auto-canonical selection.
- Не делать auto-merge.
- Термин `gate` оставляем как рабочий термин проекта.

## Статус по максимальному напору

Текущая характеристика:

`максимальный напор`

Category scope:

`11900213`

Canonical attribute:

`12 — Максимальный напор`

Included aliases:

```text
101 — Максимальный напор, м.вод.ст.
119 — Максимальный напор, м
81 — Max напор, м
```

Canonical unit:

`m`

Phase 1 canonical value apply выполнена на controlled local dump.

Transactional apply successful:

- `post_apply_verification_ok: 1`
- production/cache не трогались;
- cache rebuild не выполнялся;
- SQL files/diff не создавались.

## Важное уточнение по Phase 1

Выполнена только Phase 1: canonical value apply.

Что сделано:

- canonical rows `attribute_id = 12` обновлены/добавлены в controlled local dump;
- source alias rows `101/119/81` были сохранены;
- unresolved values не применялись;
- repeat run определяет already-applied state.

Что ещё не сделано:

- alias cleanup / consolidation ещё НЕ выполнен;
- source alias rows не удалялись;
- задача стандартизации не считается полностью закрытой до отдельного alias cleanup gate, если цель — убрать синонимы из product attribute rows.

## Alias cleanup future gate

Следующий отдельный gate для max head:

`DB-readonly alias cleanup preview`

Он должен показать, какие `product_attribute` rows с `attribute_id` `101/119/81` можно безопасно удалить после наличия canonical row `12`.

Границы alias cleanup:

- удалять можно только product rows в scope;
- не удалять сами attributes из `oc_attribute` / `oc_attribute_description`;
- unresolved values не трогать;
- real cleanup только через отдельный transactional confirm apply;
- preview/review/confirm gate обязательны.

## Architecture direction

Зафиксирован документ:

`framework-standardization/docs/GENERIC_CONTROLLED_ATTRIBUTE_APPLY_SPEC.md`

Ключевое решение:

- max-head command является prototype/proof-of-concept;
- не копировать `db-controlled-apply-max-head.php` под каждую характеристику;
- следующий architectural direction: generic controlled attribute apply engine;
- characteristic-specific data должны перейти в explicit contract/input.

Human gates остаются обязательными:

- canonical selection;
- aliases include/exclude;
- unit contract;
- unresolved policy;
- review;
- final confirm.

## Paused / rejected

Не продолжать:

- characteristic-specific commands как основной pattern;
- production/cache;
- cache rebuild;
- auto-merge;
- auto-canonical selection;
- alias cleanup без preview/review/confirm gate.

## Документы, которые обязательно читать в новом чате

В новом чате сначала читать:

1. `framework-standardization/docs/START_HERE.md`
2. `framework-standardization/docs/HANDOFF.md`
3. `framework-standardization/README.md`
4. `framework-standardization/PROJECT_MASTER_SUMMARY.md`
5. `PROJECT_MASTER_SUMMARY.md`
6. `framework-standardization/docs/DECISIONS.md`
7. `framework-standardization/docs/RUNTIME_CHECKS.md`
8. `framework-standardization/docs/RULES.md`
9. Актуальные docs/specs:
   - `framework-standardization/docs/HUMAN_DECISION_MAX_HEAD_SCOPE_11900213.md`
   - `framework-standardization/docs/MAX_HEAD_UNIT_CONTRACT_SCOPE_11900213.md`
   - `framework-standardization/docs/MAX_HEAD_RANGE_POLICY_SCOPE_11900213.md`
   - `framework-standardization/docs/HUMAN_REVIEW_MAX_HEAD_PROPOSALS_SCOPE_11900213.md`
   - `framework-standardization/docs/SQL_PREVIEW_PLAN_MAX_HEAD_SCOPE_11900213.md`
   - `framework-standardization/docs/APPLY_READINESS_MAX_HEAD_SCOPE_11900213.md`
   - `framework-standardization/docs/BOUNDED_APPLY_COMMAND_MAX_HEAD_SCOPE_11900213.md`
   - `framework-standardization/docs/GENERIC_CONTROLLED_ATTRIBUTE_APPLY_SPEC.md`

## Следующий рекомендуемый step

Есть два возможных directions. Не начинать новую характеристику до решения, какой из них выбран.

Если продолжаем max head до полного закрытия:

```text
doc-only alias cleanup spec / DB-readonly alias cleanup preview
```

Если продолжаем framework architecture:

```text
generic controlled attribute apply implementation spec/refactor
```

## Boundaries

Этот HANDOFF update:

- только documentation handoff update;
- не меняет PHP/code;
- не выполняет SQL/apply;
- не трогает production/cache;
- не делает cache rebuild.
