# HANDOFF — framework-standardization / canonical characteristics registry

Handoff date: 12-07-2026 22:04:22 Europe/Kyiv
Project: `framework-standardization`
Repository: `saitkharkov-cyber/home-energetika`
Local repository path: `D:\Git\home-energetika`
Working area: `framework-standardization`
Branch: `main`
Codex resume: `codex resume 019f35ab-7752-7251-a297-f16421ea092a`

## 1. Lifecycle Notice — назначение файла

Временный tracked transport artifact между ChatGPT-сессиями.

Создан только для завершения текущей сессии из-за исчерпания лимита Codex.

Является компактным MASTER SUMMARY завершаемой сессии.

Не является полным MASTER SUMMARY проекта, status-документом, changelog, backlog или архивом.

После успешного восстановления контекста файл готов к lifecycle cleanup, но cleanup требует отдельного пользовательского `+`.

## 2. Session Close Base Commit

Session close base commit: `620bdc8 Add Gate 1 characteristic review queue`

Это `HEAD` до transport commit создания `framework-standardization/docs/HANDOFF.md`.

После transport commit текущий `HEAD` ожидаемо новее. Transport SHA внутри handoff не записывается.

## 3. Repository Snapshot At Session Close

Remote sync state before handoff: `synced`

Recent commits before Session close base commit:

```text
620bdc8 Add Gate 1 characteristic review queue
bfeb182 Document characteristic registry runtime check
1fee072 Mark registry DB connection after discovery
d7380e1 Allow approved contracts without normalizers
dc73efc Clarify runtime checks documentation scope
```

Exact git status --short before handoff transport commit:

```text
 M framework-standardization/config/runtime/local.dump.example.php
```

Project-scope uncommitted changes: none

## 4. Current Target And Stage

Current target: `dry_run_protection` — выбор канонического target между `attribute_id 47` и `attribute_id 82`

Current scope: `root_category_id 11900213`, `hierarchical_category_path_exists`, `language_id 1`

Current stage: `blocked` — точный read-only анализ был разрешён пользовательским `+`, но не выполнен из-за исчерпания лимита Codex

## 5. Completed In This Session

* Актуальный источник данных исправлен на `framework-standardization/config/runtime/prod.snapshot.local.php`; пользователь подтвердил, что это локальная копия актуального production snapshot.
* В snapshot создана и семантически проверена `oc_canonical_attributes`: 11 колонок, 3 индекса, InnoDB, `utf8_general_ci`.
* Зарегистрированы четыре draft-канона: `pump_diameter → 44`, `max_head → 12`, `max_flow → 13`, `voltage → 15`; таблица содержит ровно 4 строки.
* Подтверждён snapshot baseline: `total_discovered 74`, `contract_required 65`, `contract_approved 9`, matched legacy IDs `12,13,15,44,57,73,76,100,121`.
* Зафиксирована граница: legacy `contract_approved=9` не равен реальным строкам canonical registry и не является источником истины для новых регистраций.
* Повторный разбор форматов напряжения отклонён как уже выполненная работа; известны `57` как будущий source/alias candidate и `73` как future exclusion candidate, но формальные mapping-решения ещё не записаны.

## 6. Open Items

* Read-only сравнение `attribute_id 47` и `82` ещё не выполнено.
* Mapping/alias/exclusion layer ещё не создан; связанные ID для `max_flow` и `voltage` не зарегистрированы формально.
* Unit contracts, normalized value contracts и normalizer assignments для четырёх канонов ещё не утверждены.
* Runtime evidence создания таблицы и четырёх регистраций ещё не перенесён в `docs/RUNTIME_CHECKS.md`.

## 7. Gates And Safety

| Gate                                      | Authorized | Note                                                                                              |
| ----------------------------------------- | ---------- | ------------------------------------------------------------------------------------------------- |
| New user + required before implementation | yes        | Handoff alone never authorizes implementation.                                                    |
| DB read-only                              | yes        | Существующий closing-session `+` разрешает только точный анализ ID `47/82`; без расширения scope. |
| Pipeline run                              | no         | Отдельный пользовательский `+` обязателен.                                                        |
| SQL preview                               | no         | Текущий шаг не включает preview регистрации.                                                      |
| Apply-plan                                | no         | Не разрешён.                                                                                      |
| SQL/apply                                 | no         | Не разрешены CREATE/INSERT/UPDATE/DELETE и product data changes.                                  |
| Production/cache actions                  | no         | Production не подключать; cache не трогать и не перестраивать.                                    |
| Commit/push for next engineering step     | no         | Требует отдельного пользовательского `+`.                                                         |
| Handoff deletion commit/push              | no         | Requires separate user + after successful context restoration.                                    |

Lifecycle cleanup handoff и следующий engineering step требуют разных пользовательских `+`.

## 8. Protected Working Tree

`framework-standardization/config/runtime/local.dump.example.php` — user-owned; do not modify, stage, commit, clean, restore, or use as a reason to reset the working tree.

## 9. Paused Or Rejected Paths

| Path                                                                          | Status   | Reason                                                                                | Return condition                                     |
| ----------------------------------------------------------------------------- | -------- | ------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| `framework-standardization/config/runtime/local.dump.php` как source of truth | rejected | Актуальная база подключается через `prod.snapshot.local.php`; старый dump отличается. | Только новое явное решение пользователя.             |
| Повторный полный inventory форматов напряжения                                | rejected | Анализ уже выполнен; повтор расходует время без новых данных.                         | Только новые данные или прямое решение пользователя. |
| Формализация aliases/exclusions до появления отдельного mapping layer         | paused   | `oc_canonical_attributes` хранит только target canonical records.                     | Отдельный архитектурный и пользовательский gate.     |

## 10. Task-Specific References

* `framework-standardization/docs/HANDOFF_SPEC.md` — обязательная структура и lifecycle этого файла.
* `framework-standardization/docs/DOCUMENTATION_BOUNDARIES.md` — границы ответственности документации.
* `framework-standardization/sql/CREATE_TABLE_canonical_attributes.sql` — схема canonical registry.
* `framework-standardization/config/runtime/prod.snapshot.local.php` — единственный актуальный private runtime config; не раскрывать значения.
* `framework-standardization/src/Orchestration/DbReadOnlyCharacteristicRegistryOrchestrator.php` — актуальный read-only registry baseline.
* `framework-standardization/src/Review/CharacteristicRegistryGate1ReviewQueueBuilder.php` — Gate 1 candidate-only review queue.

## 11. Next Bounded Step

Next bounded step: выполнить ранее разрешённый read-only анализ точных дублей «Защита от сухого хода» — `attribute_id 47` и `82`.

Expected result: точные имена/группы, охват строками и непустыми значениями, пересечение товарных множеств, duplicate-row counters и conflict checks; без выбора канона.

Allowed scope: `prod.snapshot.local.php`, только `SELECT`/`SHOW CREATE TABLE`/`information_schema`, временные runner/report только в `%TEMP%`.

Out of scope: DB writes, SQL preview, canonical selection, alias/exclusion decisions, raw values output, pipeline/apply, product changes, repository changes, commit и push.

New explicit user + required before implementation: yes

## 12. Actions After Reading This Handoff

1. Получить свежие `git log --oneline --decorate -5` и `git status --short`.
2. Считать свежий git state фактом.
3. Найти `Session close base commit` в истории текущего `HEAD`.
4. Классифицировать изменения после base commit: handoff transport commit, user-owned changes, confirmed new changes, blocking inconsistency.
5. Восстановить target, stage, gates, protected changes и один next bounded step.
6. При blocking inconsistency остановить работу, не обновлять и не удалять handoff, запросить пользователя.
7. После успешного восстановления подтвердить контекст и сообщить, что handoff готов к lifecycle cleanup.
8. Не менять файлы, не делать commit и не выполнять push без отдельного пользовательского `+`.
9. После отдельного `+` выполнить только bounded lifecycle cleanup handoff: удалить handoff, отдельный commit, push.
10. Implementation следующего engineering step требует отдельного `+`.
