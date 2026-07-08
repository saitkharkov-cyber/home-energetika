# Ручное ревью предложений нормализации — максимальный напор, scope 11900213

Дата: 2026-07-09

## Контекст

Целевой смысл характеристики:

`максимальный напор`

Область категории:

`11900213`

Связанные решения и проверки:

- `docs/HUMAN_DECISION_MAX_HEAD_SCOPE_11900213.md`
- `docs/MAX_HEAD_UNIT_CONTRACT_SCOPE_11900213.md`
- `docs/MAX_HEAD_RANGE_POLICY_SCOPE_11900213.md`
- `docs/RUNTIME_CHECKS.md`

Связанные команды:

- `framework-standardization/bin/db-readonly-normalization-proposals.php`
- `framework-standardization/bin/db-readonly-normalization-review-chain.php`
- `framework-standardization/bin/db-readonly-normalization-review-sample.php`

Связанные коммиты:

- `eafe9e6 Add DB readonly normalization proposals command`
- `f1b76a8 Add DB readonly normalization review chain command`
- `d9e3acd Add DB readonly normalization review sample command`
- `571f353 Document manual review of normalization sample`

## Входные данные ревью

Сгенерированная review-chain содержала:

- `pending_review_count: 481`
- `unresolved_count: 14`
- `skipped_count: 0`

Проверенная выборка содержала:

- `pending_review_sample_count: 50`
- `unresolved_sample_count: 14`

Результат ручного просмотра выборки:

`sample выглядит корректно`

## Решение ручного ревью

Решение:

`review-approved для accepted simple values`

Это решение применяется только к строкам, которые одновременно соответствуют всем условиям:

- category_scope: `11900213`;
- attribute_id один из: `12`, `101`, `119`, `81`;
- canonical_attribute_id: `12`;
- canonical_unit: `m`;
- исходный review_status был `pending_review`;
- raw value соответствует правилам accepted simple value из unit contract;
- proposed normalized value является decimal meters;
- range-like / upper-bound / mixed-text values исключены.

## Что одобрено на уровне ревью

На уровне ревью одобрены простые преобразования:

- `46.5м.` -> `46.5`
- `68м.` -> `68`
- `93м.` -> `93`
- `133м.` -> `133`
- `60м.` -> `60`
- decimal comma в decimal dot, например `31,5` -> `31.5`
- numeric values в `101 — Максимальный напор, м.вод.ст.` как meters of water head.

## Что не одобрено и остаётся unresolved

Следующие значения не одобрены для нормализации и остаются unresolved:

- ranges;
- upper-bound textual values;
- mixed-text values;
- ambiguous multi-number values.

Наблюдавшиеся unresolved examples:

- `100-104`
- `104–118`
- `50–51,5`
- `до 51 м`

Эти значения нельзя нормализовать молча.

## Важное уточнение безопасности

`review-approved` — это только статус ручного ревью.

Он не означает:

- разрешение на SQL preview;
- разрешение на SQL generation;
- разрешение на SQL apply;
- разрешение на production changes;
- разрешение на cache rebuild;
- разрешение на automatic merge.

Перед любым SQL preview или apply-plan нужен отдельный явный gate.

## Подтверждение границ

Подтверждено:

- это только human review decision;
- SQL preview не создавался;
- SQL files/diff не создавались;
- apply plan не создавался;
- SQL apply не выполнялся;
- product data не менялись;
- production/cache не трогались;
- cache rebuild не выполнялся;
- unresolved values остаются unresolved.

## Результат

Результат ревью:

- `481` accepted simple proposals получают статус review-approved на уровне ревью;
- `14` unresolved values остаются unresolved;
- SQL/apply остаётся запрещён.

## Следующий gate

Следующий gate должен быть отдельным и явным.

Разрешённая следующая тема для обсуждения:

`подготовка SQL preview / apply-plan`

Автоматически не разрешено:

- SQL apply;
- production changes;
- cache rebuild.