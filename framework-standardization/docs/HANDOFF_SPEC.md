# HANDOFF_SPEC — шаблон и lifecycle межсессионного handoff

Дата: 11-07-2026

Документ является постоянной спецификацией структуры и lifecycle временного файла `docs/HANDOFF.md`.

Он не содержит текущий статус проекта и не заменяет сам `HANDOFF.md`.

## 1. Назначение

`HANDOFF.md` — временная оперативная память между завершаемой и новой ChatGPT-сессией.

`HANDOFF.md` является компактным MASTER SUMMARY завершаемой рабочей сессии и непосредственного продолжения.

Он передаёт:

* snapshot состояния на момент передачи;
* один выбранный следующий bounded step;
* текущий target, stage и gates;
* protected working tree changes;
* ссылки на постоянные документы, где находятся детали.

`HANDOFF.md` не является полным MASTER SUMMARY всего проекта, постоянным status-документом, changelog, backlog, архивом сессий, заменой `START_HERE.md`, `DECISIONS.md`, `RUNTIME_CHECKS.md` или инженерных specs.

## 2. Границы ответственности

Startup prompt отвечает только за bootstrap новой сессии:

* указать repository identity;
* указать working project;
* направить к `START_HERE.md`;
* поручить прочитать `HANDOFF.md`, если он существует;
* запретить implementation и любые file changes без отдельного пользовательского `+`.

`START_HERE.md` отвечает за постоянный общий порядок входа в проект, общий reading order и правила восстановления контекста.

`HANDOFF.md` отвечает только за dynamic inter-session snapshot конкретной завершённой сессии. Он не authorizes file changes, commit или push.

`DECISIONS.md` отвечает за постоянные архитектурные и процессные решения.

`RUNTIME_CHECKS.md` отвечает за проверенные runtime/manual evidence.

Инженерные specs отвечают за устойчивые границы конкретных workflow, services, contracts или tools.

`CURRENT_OVERRIDE.md` отвечает только за временную оперативную коррективу внутри одной активной сессии и не участвует в межсессионной передаче.

## 3. Lifecycle HANDOFF.md

Во время активной сессии `HANDOFF.md` обычно отсутствует.

При завершении или переносе сессии создаётся новый актуальный `HANDOFF.md`.

Создание `HANDOFF.md`, transport commit создания и push transport commit требуют явного пользовательского поручения и действующего `+` на этот documentation lifecycle action.

Новая сессия читает `START_HERE.md`, затем существующий `HANDOFF.md`, получает свежий git state и восстанавливает контекст.

После успешного восстановления контекста новая сессия сообщает пользователю, что `HANDOFF.md` выполнил функцию передачи и готов к lifecycle cleanup.

Новая сессия не удаляет `HANDOFF.md`, не делает commit и не выполняет push без отдельного пользовательского `+` на cleanup.

После отдельного `+` разрешён только bounded lifecycle cleanup:

* удалить только `framework-standardization/docs/HANDOFF.md`;
* сделать отдельный documentation lifecycle commit;
* выполнить push;
* не включать другие изменения.

Permanent documentation changes и cleanup handoff не должны попадать в один commit. Lifecycle cleanup commit должен содержать только удаление `framework-standardization/docs/HANDOFF.md`; другие изменённые или новые файлы в cleanup commit не включаются.

Если blocking inconsistency мешает достоверно восстановить контекст, новая сессия:

* не начинает engineering work;
* не обновляет `HANDOFF.md`;
* не удаляет `HANDOFF.md`;
* запрашивает у пользователя недостающий факт;
* после устранения inconsistency завершает восстановление и сообщает, что cleanup готов и требует отдельного `+`.

`HANDOFF.md` не поддерживается актуальным во время активной сессии и не обновляется после чтения или после commits.

## 4. Lifecycle CURRENT_OVERRIDE.md

`CURRENT_OVERRIDE.md` возникает только внутри активной сессии.

Он временно переопределяет текущий путь, пока оперативная корректива не реализована, не отменена или не перенесена в постоянную документацию.

Перед созданием `HANDOFF.md` файл `CURRENT_OVERRIDE.md` должен быть закрыт и удалён.

`CURRENT_OVERRIDE.md`:

* не переживает границу сессий;
* не коммитится вместе с `HANDOFF.md`;
* не передаётся новой сессии;
* не указывается в metadata handoff;
* не читается новой сессией как часть startup/inter-session flow.

## 5. Git lifecycle и Session close base commit

Каждый `HANDOFF.md` должен содержать:

```text
Session close base commit: short SHA commit subject
```

`Session close base commit` — это `HEAD` непосредственно перед transport commit, создающим `framework-standardization/docs/HANDOFF.md`.

Transport commit создания handoff ожидаемо делает текущий `HEAD` новее base commit.

SHA transport commit не записывается внутрь handoff.

После transport commit нельзя редактировать handoff только ради обновления base SHA.

Новая сессия должна проверить, что `Session close base commit` существует в истории текущего `HEAD`, и классифицировать изменения после него как:

* handoff transport commit;
* user-owned working-tree changes;
* confirmed new changes;
* blocking inconsistency.

Base commit не обязан совпадать с текущим `HEAD`, `origin/main` или будущим commit удаления handoff.

## 6. Готовность working tree перед handoff

Перед созданием `HANDOFF.md`:

* все завершённые изменения текущего project scope должны быть committed;
* завершённая project-scope работа должна быть pushed;
* project-scope uncommitted changes должны отсутствовать;
* незакоммиченными могут оставаться только явно классифицированные protected user-owned changes вне текущего scope.

Если существуют незакоммиченные изменения текущей задачи, сессия не готова к handoff.

Нельзя передавать project-scope uncommitted changes через текст handoff: новая GitHub-сессия не получит их содержимое.

В готовом handoff ожидаемое sync-state:

```text
Remote sync state before handoff: synced
```

Значение `not checked` допустимо только как diagnostic draft state. Handoff нельзя создавать до проверки и подтверждения `synced`.

## 7. Правила одного источника

Git snapshot указывается только в `Repository Snapshot At Session Close`.

Классификация user-owned changes указывается только в `Protected Working Tree`.

Target, scope и stage указываются только в `Current Target And Stage`.

Gates и safety permissions указываются только в `Gates And Safety`.

Завершённые результаты указываются только в `Completed In This Session`.

Незавершённые вопросы указываются только в `Open Items`.

Постоянные детали заменяются кратким выводом и ссылкой на постоянный документ.

Один факт не должен повторяться в нескольких смысловых блоках.

## 8. Обязательная структура HANDOFF.md

Разделы `HANDOFF.md` должны идти в таком порядке:

1. Header metadata.
2. Lifecycle Notice.
3. Session Close Base Commit.
4. Repository Snapshot At Session Close.
5. Current Target And Stage.
6. Completed In This Session.
7. Open Items.
8. Gates And Safety.
9. Protected Working Tree.
10. Paused Or Rejected Paths.
11. Task-Specific References.
12. Next Bounded Step.
13. Actions After Reading This Handoff.

Разделы нельзя переставлять без изменения этой spec.

## 9. Metadata

Header metadata должен содержать:

```text
Handoff date: DD-MM-YYYY HH:mm:ss Europe/Kyiv
Project: project name
Repository: owner/repository
Local repository path: absolute local path
Working area: repository-relative path
Branch: branch
Codex resume: command | none
```

Формат даты синхронизирован с решением в `DECISIONS.md`: `DD-MM-YYYY HH:mm:ss Europe/Kyiv`.

`Codex resume` optional по смыслу, но строка присутствует всегда. Если продолжать конкретную Codex session не нужно, указывается `none`.

В metadata нельзя включать:

* `CURRENT_OVERRIDE.md`;
* transport SHA создания;
* SHA удаления;
* текущий `HEAD` после transport commit.

`Session close base commit` находится в отдельном одноимённом разделе.

## 10. Gates and safety

`Gates And Safety` должен использовать Markdown-таблицу:

| Gate | Authorized | Note |
| ---- | ---------- | ---- |
| New user + required before implementation | yes | Handoff alone never authorizes implementation. |
| DB read-only | yes/no | condition |
| Pipeline run | yes/no | condition |
| SQL preview | yes/no | condition |
| Apply-plan | yes/no | condition |
| SQL/apply | yes/no | condition |
| Production/cache actions | yes/no | condition |
| Commit/push for next engineering step | yes/no | condition |
| Handoff deletion commit/push | no | Requires separate user + after successful context restoration. |

Дополнительные строки допустимы только при релевантном риске:

* `--confirm-apply`;
* product/category data changes;
* cache rebuild.

После таблицы обязательна фраза:

```text
Lifecycle cleanup handoff и следующий engineering step требуют разных пользовательских +.
```

## 11. Размер

Целевой размер exact template: 100-140 строк.

Жёсткий максимум exact template: 160 строк.

Жёсткий максимум заполненного реального handoff: 180 строк.

Основная часть `HANDOFF_SPEC.md` может быть длиннее.

Если подробность занимает более 5-7 строк и уже есть в постоянном документе, её нужно заменить кратким выводом и ссылкой.

## 12. Запрещённое содержимое

В `HANDOFF.md` нельзя включать:

* полный changelog;
* историю всех сессий;
* старые handoff-блоки;
* архивные копии;
* `HANDOFF_OLD.md`;
* `HANDOFF_FINAL.md`;
* `HANDOFF_NEW.md`;
* полный diff;
* raw terminal transcript;
* большие runtime outputs;
* полные решения;
* полные specs;
* полный `RULES.md`;
* полный startup reading order;
* startup prompt внутри handoff;
* credentials;
* secrets;
* passwords;
* production access data;
* неподтверждённые догадки;
* несколько следующих шагов;
* roadmap;
* backlog;
* повтор одного факта;
* `CURRENT_OVERRIDE.md`;
* инструкции проверять `CURRENT_OVERRIDE.md`;
* transport SHA;
* требование обновить base SHA;
* project-scope uncommitted changes;
* инструкции поддерживать handoff актуальным;
* инструкции проверять, не устарел ли handoff;
* инструкции обновить прочитанный handoff;
* автоматическое разрешение удалить handoff без отдельного `+`.

## 13. Startup prompt

Рекомендуемый startup prompt:

```text
Подключись к GitHub-репозиторию saitkharkov-cyber/home-energetika.

Рабочий проект:
framework-standardization

Сначала прочитай:
framework-standardization/docs/START_HERE.md

Дальше следуй порядку чтения документов, указанному в START_HERE.md.

Если существует:
framework-standardization/docs/HANDOFF.md

обязательно прочитай его как временную оперативную передачу контекста предыдущей сессии.

Если HANDOFF.md существует, после чтения документации восстанови:
- фактическое состояние репозитория;
- Session close base commit;
- текущий target;
- текущий stage;
- действующие gates;
- защищённые пользовательские изменения;
- один следующий bounded step.

Если HANDOFF.md отсутствует, сообщи, что межсессионная оперативная передача отсутствует, и продолжай только по постоянной документации. Не придумывай handoff-specific состояние.

Не начинай implementation и не меняй файлы без отдельного пользовательского +.

Если HANDOFF.md существует и контекст успешно восстановлен, сообщи, что файл выполнил функцию передачи и готов к lifecycle cleanup. Не удаляй файл, не делай commit и не выполняй push без отдельного пользовательского +.
```

Startup prompt не должен содержать динамический status проекта.

## 14. Exact HANDOFF.md template

````markdown
# HANDOFF — <project> / <short session topic>

Handoff date: <DD-MM-YYYY HH:mm:ss Europe/Kyiv>
Project: `<project name>`
Repository: `<owner/repository>`
Local repository path: `<absolute local path>`
Working area: `<repository-relative path>`
Branch: `<branch>`
Codex resume: `<command | none>`

## 1. Lifecycle Notice — назначение файла

Временный tracked transport artifact между ChatGPT-сессиями.

Создан только для завершения или переноса сессии.

Является компактным MASTER SUMMARY завершаемой сессии.

Не является полным MASTER SUMMARY проекта, status-документом, changelog, backlog или архивом.

После успешного восстановления контекста файл готов к lifecycle cleanup, но cleanup требует отдельного пользовательского `+`.

## 2. Session Close Base Commit

Session close base commit: `<short SHA> <commit subject>`

Это `HEAD` до transport commit создания `framework-standardization/docs/HANDOFF.md`.

После transport commit текущий `HEAD` ожидаемо новее. Transport SHA внутри handoff не записывается.

## 3. Repository Snapshot At Session Close

Remote sync state before handoff: `synced`

Recent commits before Session close base commit:

```text
<up to five commits>
```

Exact git status --short before handoff transport commit:

```text
<exact status, including protected user-owned changes, or clean>
```

Project-scope uncommitted changes: none

## 4. Current Target And Stage

Current target: `<target or none>`

Current scope: `<scope or none>`

Current stage: `<planning / documentation / implementation / review / blocked / other>`

## 5. Completed In This Session

- `<completed bounded result>` — `<source path or short evidence>`

## 6. Open Items

- `<unresolved item, blocker, or manual decision>`

Если отсутствует: `none`

## 7. Gates And Safety

| Gate | Authorized | Note |
| ---- | ---------- | ---- |
| New user + required before implementation | yes | Handoff alone never authorizes implementation. |
| DB read-only | yes/no | condition |
| Pipeline run | yes/no | condition |
| SQL preview | yes/no | condition |
| Apply-plan | yes/no | condition |
| SQL/apply | yes/no | condition |
| Production/cache actions | yes/no | condition |
| Commit/push for next engineering step | yes/no | condition |
| Handoff deletion commit/push | no | Requires separate user + after successful context restoration. |

Lifecycle cleanup handoff и следующий engineering step требуют разных пользовательских `+`.

## 8. Protected Working Tree

`<path>` — user-owned; do not modify, stage, commit, clean, restore, or use as a reason to reset the working tree.

Если отсутствует: `none`

## 9. Paused Or Rejected Paths

| Path | Status | Reason | Return condition |
| ---- | ------ | ------ | ---------------- |
| `<path>` | paused/rejected | `<short reason>` | `<condition or explicit user decision required>` |

Если отсутствует: `none`

## 10. Task-Specific References

- `<repository-relative path>` — `<why it is needed>`

## 11. Next Bounded Step

Next bounded step: `<one selected action>`

Expected result: `<one verifiable result>`

Allowed scope: `<allowed files/actions>`

Out of scope: `<forbidden files/actions>`

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
````

## 15. Validation checklist

Перед созданием `HANDOFF.md` проверить:

* handoff создаётся только при завершении или переносе;
* создание handoff, transport commit и push разрешены отдельным пользовательским `+`;
* во время активной сессии старый handoff отсутствует;
* `CURRENT_OVERRIDE.md` отсутствует;
* создаётся новый handoff, а не поддерживается старый;
* указан `Session close base commit`;
* base commit является `HEAD` до transport commit;
* transport SHA отсутствует;
* нет требования обновить base SHA;
* `Remote sync state before handoff: synced`;
* завершённая project-scope работа committed;
* завершённая project-scope работа pushed;
* project-scope uncommitted changes отсутствуют;
* remaining dirty files либо `none`, либо перечислены как protected user-owned changes;
* repository snapshot получен до transport commit;
* все code fences закрыты;
* все таблицы валидный Markdown;
* user-owned changes не дублируются;
* gates не дублируются;
* completed содержит 1-7 bullets или `none`;
* open items содержит реальные unresolved items или `none`;
* есть один next bounded step;
* startup prompt не скопирован в handoff;
* полный reading order отсутствует;
* `CURRENT_OVERRIDE.md` не передаётся;
* нет changelog;
* нет raw output;
* нет secrets;
* при blocking inconsistency handoff не удаляется преждевременно;
* после успешного восстановления cleanup требует отдельного пользовательского `+`;
* cleanup handoff и следующий engineering step имеют разные `+`;
* cleanup commit содержит только удаление HANDOFF.md и не смешан с permanent documentation changes;
* архивные handoff-копии отсутствуют;
* exact template не превышает 160 строк;
* заполненный файл не превышает 180 строк.

## 16. Кто формирует завершающий HANDOFF.md

Смысловое содержание завершающего `HANDOFF.md` формирует ChatGPT, ведущий текущую рабочую сессию.

Основной источник содержания handoff — полный контекст завершаемого чата:

* принятые пользователем решения;
* выполненные и проверенные действия;
* выданные разрешения;
* отклонённые или приостановленные пути;
* текущие target, scope и stage;
* действующие gates;
* protected user-owned changes;
* ровно один следующий bounded step.

Codex не должен самостоятельно реконструировать смысловое содержание handoff по Git history, commits или постоянной документации, поскольку у него нет полного контекста разговора.

Git и постоянная документация используются для проверки фактов, но не заменяют контекст текущего чата.

До подготовки финального текста ChatGPT должен получить свежие Git-факты:

* текущий `HEAD`;
* состояние `origin/main`;
* последние commits;
* `git status --short`;
* наличие project-scope uncommitted changes;
* protected user-owned changes.

Если ChatGPT не имеет прямого доступа к репозиторию, Codex может использоваться в отдельном read-only шаге только для получения этих Git-фактов.

После получения фактов ChatGPT самостоятельно готовит и показывает пользователю полный проект `HANDOFF.md`.

Только после отдельного пользовательского `+` утверждённый текст сохраняется в:

`framework-standardization/docs/HANDOFF.md`

Если для сохранения используется Codex, он должен:

* записать предоставленный текст дословно;
* не менять target, scope, stage, completed items, open items, gates, protected working tree или next bounded step;
* не дополнять и не переосмысливать содержание;
* изменить только `framework-standardization/docs/HANDOFF.md`;
* выполнить только формальные проверки файла и working tree.

Codex не должен одновременно проверять новые Git-факты и самостоятельно изменять на их основании уже утверждённый текст. При обнаружении расхождения он должен остановиться и сообщить о blocking inconsistency.

Transport commit и push выполняются только после следующего отдельного пользовательского `+`.

### Канонический запрос при завершении сессии

> Завершаем текущую сессию. Сначала получи или запроси свежие Git-факты без изменения файлов. Затем сам подготовь и покажи полный проект нового `framework-standardization/docs/HANDOFF.md` по `HANDOFF_SPEC.md`, используя контекст этого чата как основной источник. Не поручай Codex формировать или переосмысливать содержание. Файл не сохраняй, commit и push не выполняй без отдельных пользовательских `+`.
