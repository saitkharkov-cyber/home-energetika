# Spec — Phase 2 alias cleanup для максимального напора, scope 11900213

Дата: 2026-07-09

## 1. Scope

Целевая характеристика:

`максимальный напор`

Category scope:

`11900213`

Canonical attribute:

`12 — Максимальный напор`

Source alias attribute IDs:

- `101 — Максимальный напор, м.вод.ст.`
- `119 — Максимальный напор, м`
- `81 — Max напор, м`

Canonical unit:

`m`

Target table:

`oc_product_attribute`

Allowed operation in future:

`DELETE` только alias `product_attribute` rows, если они безопасно covered by canonical row `12`.

## 2. Что уже сделано

Phase 1 canonical value apply выполнена на controlled local dump.

Подтверждено:

- canonical rows `attribute_id = 12` созданы/обновлены;
- source alias rows `101/119/81` были сохранены;
- production/cache не трогались;
- cache rebuild не выполнялся.

## 3. Что ещё НЕ сделано

Alias cleanup ещё не выполнен.

Не выполнялось:

- alias rows не удалялись;
- `oc_attribute` не трогался;
- `oc_attribute_description` не трогался;
- справочник характеристик не чистился;
- названия характеристик не менялись.

## 4. Safety rule

Удалять можно только `product_attribute` rows с `attribute_id` `101/119/81` в `category_scope = 11900213`, если одновременно выполнены условия:

- у того же `product_id` есть canonical row `attribute_id = 12`;
- canonical row прошла post-apply verification;
- alias value был review-approved и covered by canonical value;
- удаление не затрагивает unresolved values;
- удаление не затрагивает products вне category_scope;
- удаление не затрагивает `oc_attribute` / `oc_attribute_description`.

Если хотя бы одно условие не выполнено, alias row должен остаться.

## 5. DB-readonly alias cleanup preview

Следующий preview gate должен показать:

- total alias rows in scope по `101/119/81`;
- safely removable alias rows;
- not removable alias rows;
- breakdown by source alias `attribute_id`;
- sample rows для manual review.

Причины `not removable` должны включать:

- missing canonical row `12`;
- canonical value mismatch;
- unresolved / excluded value;
- product outside scope;
- duplicate/conflict case.

Preview должен быть DB-readonly и не должен выполнять DELETE.

## 6. Future apply gate

Real cleanup запрещён до отдельного review/confirm.

Будущий apply должен быть:

- transactional;
- explicit `--confirm-apply`;
- DELETE only from `oc_product_attribute`;
- only alias attribute_ids `101/119/81`;
- only category_scope `11900213`;
- verification before commit;
- rollback on mismatch/error;
- idempotent repeat run;
- no production/cache;
- no cache rebuild.

Real cleanup не должен быть частью preview gate.

## 7. Explicit forbidden

Запрещено:

- не удалять rows `canonical_attribute_id = 12`;
- не удалять `oc_attribute`;
- не удалять `oc_attribute_description`;
- не менять названия характеристик;
- не делать auto-merge;
- не делать auto-canonical selection;
- не трогать unresolved values;
- не выполнять SQL/apply в рамках этого doc step;
- не трогать production/cache.

## 8. Next step

Следующий шаг после этого документа:

`DB-readonly alias cleanup preview command/spec для max head`

Этот следующий шаг должен оставаться preview-only:

- без real DELETE;
- без SQL apply;
- без production/cache;
- без cache rebuild.

## 9. Boundaries текущей задачи

Этот документ:

- doc only;
- не меняет PHP/code;
- не меняет `bin/`;
- не меняет `src/`;
- не меняет `config/`;
- не выполняет SQL;
- не выполняет apply;
- не трогает production/cache;
- не делает cache rebuild;
- не меняет `HANDOFF.md`;
- не меняет `RUNTIME_CHECKS.md`;
- не меняет `DECISIONS.md`.

Этот spec фиксирует только отдельный gate для Phase 2 alias cleanup / consolidation после successful canonical value apply.
