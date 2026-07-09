# Spec — generic controlled attribute apply engine

Дата: 2026-07-09

## 1. Контекст

Workflow для характеристики `максимальный напор` успешно прошёл полный controlled cycle до transactional local apply:

- discovery / canonical selection;
- raw values inventory;
- canonical unit / normalized_value contract;
- normalization proposals;
- review-chain;
- SQL preview;
- apply-plan preview;
- bounded transactional local apply.

Команда `framework-standardization/bin/db-controlled-apply-max-head.php` доказала safety model:

- apply был выполнен только на controlled local dump;
- production/cache не трогались;
- cache rebuild не выполнялся;
- source alias rows сохранялись;
- unresolved values были исключены;
- repeat run определяет already-applied state.

## 2. Architecture decision

`db-controlled-apply-max-head.php` и `DbControlledMaxHeadApplyCommand` являются prototype / proof-of-concept.

Этот подход не должен копироваться под каждую характеристику.

Запрещённый direction:

- не создавать `db-controlled-apply-attribute.php` для каждой характеристики;
- не создавать отдельный `ApplyCommand` class под каждую характеристику;
- не превращать каждый canonical attribute в отдельный PHP command/class.

Дальнейшая архитектура должна двигаться к generic controlled attribute apply engine.

## 3. Почему max-head command не является final pattern

Текущий max-head command содержит characteristic-specific assumptions:

- hardcoded target meaning;
- hardcoded `category_scope = 11900213`;
- hardcoded `canonical_attribute_id = 12`;
- hardcoded aliases `101/119/81`;
- hardcoded `canonical_unit = m`;
- hardcoded expected counts `400/81/14/81`;
- characteristic-specific class name `DbControlledMaxHeadApplyCommand`;
- characteristic-specific command name `db-controlled-apply-max-head.php`.

Такой подход плохо масштабируется на остальные характеристики категории и будет приводить к дублированию safety logic, verification logic и CLI-поведения.

## 4. Reusable safety model из prototype

Из max-head prototype нужно сохранить как reusable safety model:

- dry-run по умолчанию;
- explicit `--confirm-apply`;
- runtime allow-list / production block;
- no cache rebuild;
- no production/cache changes;
- reuse SQL preview / apply-plan logic;
- transactional apply;
- verification before commit;
- commit only if verification ok;
- rollback on mismatch/error;
- idempotency / already-applied detection;
- source alias rows preserved;
- unresolved values excluded;
- post-apply verification summary.

Именно эти свойства являются полезным результатом prototype, а не hardcoded max-head command shape.

## 5. Целевая архитектура

Целевой generic command:

`framework-standardization/bin/db-controlled-attribute-apply.php`

Целевой generic apply engine/class:

`FrameworkStandardization\Apply\DbControlledAttributeApplyCommand`

Имя может быть уточнено при implementation spec, если другой вариант лучше впишется в проект.

Characteristic-specific data must come from explicit contract/input, not hardcoded class.

## 6. Contract/input

Generic apply command должен получать explicit contract/input, который описывает:

- target meaning;
- category_scope;
- canonical_attribute_id;
- included attribute_ids / aliases;
- excluded attributes;
- canonical_unit;
- normalized_value contract;
- accepted value policy / parser family;
- unresolved policy;
- review-approved source;
- expected counts or computed plan constraints;
- allowed target table/columns;
- safety constraints.

Contract/input должен быть результатом human-gated workflow, а не автоматическим guess.

## 7. Generic apply engine behavior

Универсальный apply engine должен:

- принимать runtime config;
- принимать contract/config path или structured explicit options;
- строить plan из generic SQL preview / approved proposals;
- выполнять dry-run без изменений;
- выполнять real apply только при `--confirm-apply`;
- использовать transaction;
- делать verification before commit;
- выполнять COMMIT только если verification ok;
- делать ROLLBACK on mismatch/error;
- печатать summary.

Engine не должен сам выбирать canonical attribute, aliases, unit или parser semantics.

## 8. Human-gated areas

Остаются human-gated:

- target meaning;
- canonical selection;
- alias include/exclude;
- unit contract;
- unresolved/range policy;
- review approval;
- final `--confirm-apply`;
- production/cache gate.

Generic apply engine должен исполнять только уже утверждённый contract и review-approved plan.

## 9. Что можно универсализировать

Можно и нужно универсализировать:

- discovery command;
- raw values inventory;
- proposals generation skeleton;
- review-chain;
- SQL preview;
- apply-plan preview;
- controlled apply;
- post-apply verification.

Общая логика должна жить в generic components, а не копироваться по характеристикам.

## 10. Что нельзя универсализировать без contract

Нельзя универсализировать без explicit contract:

- semantic parser decisions;
- unit meaning;
- range interpretation;
- ambiguous values;
- canonical identity;
- production/cache decisions.

Эти решения должны оставаться отдельными human-approved gates.

## 11. Next direction

Не продолжать создание characteristic-specific commands.

Следующий implementation direction должен быть refactor/spec для generic controlled attribute apply command.

Max-head command can remain as prototype/reference until generic replacement exists.

После появления generic replacement max-head command may become deprecated or wrapper around generic command.

## 12. Boundaries

Этот документ:

- does not implement generic command;
- does not change PHP;
- does not run SQL;
- does not apply changes;
- does not touch production/cache;
- does not rebuild cache;
- does not modify existing max-head command;
- does not modify `HANDOFF.md`;
- does not modify `RUNTIME_CHECKS.md`.

Этот документ фиксирует architecture direction только на уровне spec/decision analysis.
