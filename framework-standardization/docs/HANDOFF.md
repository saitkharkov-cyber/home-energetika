# HANDOFF — HmEnerg_Характеристики / аудит зарегистрированных канонов

Handoff date: 13-07-2026 23:31:14 Europe/Kyiv
Project: `HmEnerg_Характеристики`
Repository: `saitkharkov-cyber/home-energetika`
Local repository path: `D:\Git\home-energetika`
Working area: `framework-standardization`
Branch: `main`
Codex resume: `codex resume 019f59e6-8d55-77c0-805a-1014d6ae48c8`

## 1. Назначение файла

Временная передача контекста между сессиями ChatGPT.

Файл содержит итог текущей сессии, действующие ограничения и один следующий ограниченный шаг.

После успешного восстановления контекста удаление файла, отдельный коммит и push требуют отдельного пользовательского `+`.

## 2. Базовый коммит завершения сессии

Session close base commit: `7af2635 Record dry-run protection local migration result`

Это `HEAD` до отдельного transport-коммита, создающего `framework-standardization/docs/HANDOFF.md`.

SHA transport-коммита внутри handoff не записывается.

## 3. Состояние репозитория

Remote sync state before handoff: `synced`

Последние коммиты до базового:

```text
7af2635 Record dry-run protection local migration result
e314667 Add DB-readonly contract normalization preview
dcdee0c Add attribute contract readiness auditor
bc561d0 Activate dry-run protection normalizer contract
```

Точный `git status --short` перед transport-коммитом:

```text
?? framework-standardization/docs/HANDOFF.md
```

Project-scope uncommitted changes: none

Единственный незакоммиченный файл — создаваемый transport artifact `HANDOFF.md`.

`HEAD`, `origin/main` и `origin/HEAD` до transport-коммита указывали на `7af2635`.

## 4. Текущая цель и стадия

Current target: привести товары категории `11900213` к уже зарегистрированным канонам:

```text
max_head      -> 12
max_flow      -> 13
voltage       -> 15
pump_diameter -> 44
```

Current scope: controlled local dump `he_framework_local_dump`, префикс `oc_`, иерархическая область категории `11900213`.

Current stage: planning.

До реализации универсального apply/cleanup runner нужно установить фактическое состояние товаров по этим четырём канонам.

## 5. Завершено в этой сессии

* В `oc_canonical_attributes` зарегистрированы и идемпотентно проверены:

  * `dry_run_protection -> 47`
  * `max_head -> 12`
  * `max_flow -> 13`
  * `voltage -> 15`
  * `pump_diameter -> 44`
* При регистрации канонов `oc_product_attribute` не изменялась.
* Для товара `8197` значение `Да` перенесено с alias `82` на canonical `47`.
* Повторный apply подтвердил `already_applied`.
* Alias-строка `8197 / 82 / 1 / Да` удалена.
* Повторная очистка подтвердила `already_cleaned`.
* Итог по `dry_run_protection`:

  * canonical `47`: 11 товаров;
  * `Да`: 3;
  * `Нет`: 8;
  * alias `82`: 0 строк;
  * товаров одновременно с `47` и `82`: 0.
* Contract хранит post-cleanup evidence, pre-migration evidence и controlled-local result.
* Static checks и реальный DB-readonly preview прошли.
* Production и cache не затрагивались.
* Результат committed и pushed: `7af2635`.

## 6. Открытые вопросы

* Нет актуального единого read-only аудита канонов `12`, `13`, `15`, `44` по всей категории `11900213`.
* Для `max_head` нужно пересчитать остатки aliases `101`, `119`, `81`.
* Для `voltage` утверждены canonical `15`, aliases `57`, `79`, `99`, `118`, `170`; `73` исключён.
* Для `max_flow` нельзя смешивать максимальную, минимальную, номинальную и обычную производительность.
* Для `pump_diameter` нельзя смешивать диаметр насоса, подключения, резьбы, трубы и скважины.

## 7. Разрешения и безопасность

| Gate                          | Authorized | Note                                             |
| ----------------------------- | ---------- | ------------------------------------------------ |
| Новый `+` перед реализацией   | yes        | Сам handoff ничего не разрешает.                 |
| DB read-only                  | yes        | Только controlled local dump и после нового `+`. |
| Pipeline run                  | no         | На следующем шаге не нужен.                      |
| SQL preview                   | no         | Следующий шаг — аудит.                           |
| Apply-plan                    | no         | Не разрешён.                                     |
| SQL/apply                     | no         | INSERT, UPDATE, DELETE запрещены.                |
| Product/category data changes | no         | Изменение данных запрещено.                      |
| Production/cache actions      | no         | Полностью запрещены.                             |
| Commit/push следующего шага   | no         | Требуют отдельных `+`.                           |
| Handoff deletion commit/push  | no         | Отдельный `+` после восстановления контекста.    |

Lifecycle cleanup handoff и следующий engineering step требуют разных пользовательских `+`.

## 8. Защищённые локальные файлы

* `framework-standardization/config/runtime/local.dump.php` — не изменять, не stage, не commit, не раскрывать данные доступа.
* `framework-standardization/config/runtime/local.dump.example.php` — если существует, не изменять, не stage, не commit, не clean, не restore, не stash.

## 9. Приостановленные и отклонённые пути

| Path                                         | Status   | Reason                                                    | Return condition                |
| -------------------------------------------- | -------- | --------------------------------------------------------- | ------------------------------- |
| Generic apply/cleanup runner                 | paused   | Сначала привести товары к уже зарегистрированным канонам. | После анализа четырёх канонов.  |
| Полный ручной цикл для каждой характеристики | rejected | `dry_run_protection` был эталонным первым циклом.         | Только для сложного исключения. |

## 10. Основные ссылки

* `framework-standardization/docs/START_HERE.md` — порядок чтения.
* `framework-standardization/docs/HANDOFF_SPEC.md` — правила handoff.
* `framework-standardization/docs/CANONICAL_ATTRIBUTE_REGISTRATION.md` — реестр канонов.
* `framework-standardization/docs/DECISIONS.md` — утверждённые решения.
* `framework-standardization/docs/RUNTIME_CHECKS.md` — подтверждённые runtime-факты.
* `framework-standardization/config/attribute-contracts/dry_run_protection_11900213.php` — завершённый эталонный contract.
* `framework-standardization/config/attribute-contracts/max_head_11900213.php` — contract максимального напора.
* `framework-standardization/docs/RULES.md` — общие ограничения.

## 11. Следующий ограниченный шаг

Next bounded step: выполнить один consolidated DB-readonly inventory канонов `12`, `13`, `15`, `44` в области категории `11900213`.

Expected result: по каждой характеристике получить canonical/alias usage, распределение значений, products with both, совпадающие дубли, конфликты, unsupported values и безопасных кандидатов на перенос; отдельно сравнить всю категорию с товарами Sumoto.

Allowed scope:

* чтение репозитория и документации;
* SELECT к controlled local dump;
* временный локальный read-only script;
* удаление временного script после запуска;
* консольный отчёт.

Out of scope:

* любые записи в БД;
* изменение registry, contracts и normalizers;
* migration и alias cleanup;
* SQL generation и apply-plan;
* generic runner implementation;
* production/cache actions;
* staging, commit и push.

New explicit user + required before implementation: yes

## 12. Действия после чтения handoff

1. Получить свежие `git log --oneline --decorate -5` и `git status --short`.
2. Считать свежий Git-факт источником истины.
3. Найти `Session close base commit` в истории текущего `HEAD`.
4. Классифицировать изменения после него.
5. Восстановить цель, стадию, ограничения и следующий шаг.
6. При blocking inconsistency остановиться и запросить пользователя.
7. После восстановления сообщить, что handoff готов к cleanup.
8. Не менять файлы, не commit и не push без отдельного `+`.
9. После отдельного `+` удалить только `framework-standardization/docs/HANDOFF.md`, сделать отдельный commit и push.
10. Implementation следующего шага требует другого отдельного `+`.
