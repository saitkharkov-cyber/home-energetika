# DOCUMENTATION_BOUNDARIES — границы ответственности документов

Документ фиксирует, какой документ за что отвечает в `framework-standardization`.

Цель — не допускать дублей, устаревших повторов и конфликтующих сведений о текущем состоянии между:

1. `framework-standardization/glossary/!_README.md`
2. `framework-standardization/docs/CURRENT_OVERRIDE.md`
3. `framework-standardization/docs/HANDOFF.md`
4. `framework-standardization/docs/HANDOFF_SPEC.md`
5. `framework-standardization/docs/DECISIONS.md`
6. `framework-standardization/docs/RUNTIME_CHECKS.md`
7. `framework-standardization/docs/LEGACY_DECISIONS.md`

`CURRENT_OVERRIDE.md` является необязательным внутренним документом активной сессии и обычно отсутствует.

`HANDOFF.md` является временным межсессионным transport artifact и обычно отсутствует во время активной сессии.

## 1. Главный принцип

У каждого важного факта должен быть один основной документ-источник.

Другие документы могут ссылаться на этот факт кратко, но не должны заново переписывать его полностью.

Если факт уже описан в одном документе, в другом документе нужно писать короткую ссылку или краткое состояние, а не копировать весь блок.

Временная оперативная корректива может быть зафиксирована в `CURRENT_OVERRIDE.md`, если она существенно меняет текущую работу до обновления постоянных документов.

## 2. Источники правды

### `glossary/!_README.md`

Отвечает только за устройство глоссария:

* какие термины вынесены в glossary;
* как читать glossary;
* как добавлять новые glossary-файлы;
* какие glossary-файлы существуют.

Не должен содержать:

* текущий workflow status;
* stable point;
* runtime check results;
* SQL/apply status;
* handoff summary;
* оперативные коррективы текущей сессии;
* архитектурные решения проекта, если они не являются правилами ведения glossary.

### `docs/CURRENT_OVERRIDE.md`

`CURRENT_OVERRIDE.md` — необязательный временный документ для существенных оперативных корректив, обнаруженных во время активной рабочей сессии.

Он действует только внутри активной сессии.

Он не является handoff между сессиями и не означает завершение или перенос текущей сессии.

Файл должен существовать только тогда, когда текущая корректива должна сохраняться вне текущего окна контекста и иметь приоритет над ранее зафиксированным рабочим состоянием.

Наличие `CURRENT_OVERRIDE.md` означает, что описанная в нём оперативная корректива действует.

Отдельное поле состояния для override не используется.

После переноса актуальных сведений в постоянные документы `CURRENT_OVERRIDE.md` удаляется.

Перед созданием `HANDOFF.md` файл `CURRENT_OVERRIDE.md` должен быть закрыт и удалён.

`CURRENT_OVERRIDE.md` не передаётся новой сессии, не коммитится вместе с handoff, не указывается в handoff metadata и не входит в startup/inter-session reading order.

#### Когда создаётся

`CURRENT_OVERRIDE.md` создаётся, если одновременно выполняются условия:

* во время работы обнаружен новый существенный факт;
* этот факт меняет текущую задачу, target, scope, gate, блокировки, последовательность действий или следующий bounded step;
* продолжение по текущему `HANDOFF.md`, `DECISIONS.md` или spec может привести к неверному действию;
* корректива должна сохраниться независимо от текущего диалога.

Файл не требуется:

* для каждого небольшого наблюдения;
* для обычного уточнения, которое уже надёжно удерживается в текущем контексте;
* для результата завершённого шага, который сразу переносится в постоянные документы;
* вместо создания нового `HANDOFF.md` при завершении или переносе сессии.

#### Приоритет

Если `CURRENT_OVERRIDE.md` существует внутри активной сессии, он имеет высший приоритет среди рабочих документов в части:

* текущей задачи;
* текущего состояния;
* target;
* scope;
* gate;
* блокировок;
* последовательности действий;
* следующего bounded step;
* временно приостановленного пути.

`CURRENT_OVERRIDE.md` имеет приоритет над рабочим контекстом активной сессии, включая:

* `DECISIONS.md`;
* `RUNTIME_CHECKS.md` в части текущего рабочего вывода;
* актуальными specs;
* roadmap;
* ранее выбранным следующим шагом.

`CURRENT_OVERRIDE.md` не может отменять:

* пользовательские инструкции;
* правила безопасности;
* explicit SQL/apply gate;
* production restrictions;
* запрет cache rebuild;
* обязательные проверки;
* системные ограничения проекта.

#### Что должен содержать

`CURRENT_OVERRIDE.md` должен содержать:

* дату создания;
* дату последнего обновления, если файл изменялся;
* причину появления;
* подтверждённый новый факт;
* что именно переопределяется;
* текущий gate;
* временные блокировки;
* новый следующий bounded step;
* условия завершения действия override;
* явно сохранённые safety-ограничения.

Необязательно повторять в нём весь текущий контекст проекта.

Нужно указывать только ту часть контекста, которая изменилась или должна временно иметь более высокий приоритет.

#### Как обновляется

Если в рамках той же оперативной коррективы появляются новые подтверждённые сведения, обновляется существующий `CURRENT_OVERRIDE.md`.

Не следует создавать несколько одновременно существующих override-файлов.

Новое обновление не должно молча удалять ранее действующие ограничения.

Если какое-либо положение:

* отменено;
* уточнено;
* заменено;
* признано ошибочным,

это должно быть указано явно.

`CURRENT_OVERRIDE.md` не должен превращаться в накопительный changelog или общий backlog.

#### Когда удаляется

`CURRENT_OVERRIDE.md` удаляется, когда выполняется одно из условий:

* корректива реализована и проверена;
* исходное предположение признано ошибочным;
* принято постоянное решение и перенесено в основные документы;
* текущая сессия завершается или переносится, а актуальные сведения сначала перенесены в постоянные документы;
* текущая задача больше не зависит от этой коррективы.

После этого актуальные сведения переносятся в соответствующие постоянные документы, а `CURRENT_OVERRIDE.md` удаляется.

#### Порядок закрытия и удаления

Перед удалением `CURRENT_OVERRIDE.md` необходимо:

1. определить, какие сведения остаются актуальными;
2. перенести постоянные архитектурные или процессные решения в `DECISIONS.md`;
3. обновить `RUNTIME_CHECKS.md`, если были выполнены новые проверки;
4. обновить соответствующий spec, если изменилась рабочая логика;
5. убедиться, что после удаления override обычный порядок чтения не возвращает проект к устаревшему пути;
6. удалить `CURRENT_OVERRIDE.md`;
7. только после этого создавать новый `HANDOFF.md`, если сессия завершается или переносится.

После переноса актуальных сведений файл удаляется.

Промежуточный закрытый override-файл не хранится.

#### Что запрещено

Нельзя:

* использовать `CURRENT_OVERRIDE.md` как постоянный backlog;
* сохранять его после завершения коррективы;
* создавать его для каждого небольшого наблюдения;
* использовать его вместо обновления постоянных документов;
* использовать его как handoff;
* через него разрешать SQL/apply без отдельного explicit gate;
* через него ослаблять production/cache restrictions;
* хранить в нём credentials, secrets или runtime passwords.

### `docs/HANDOFF.md`

`HANDOFF.md` — временный tracked transport artifact для оперативной передачи контекста в новый чат.

Он может отсутствовать. Отсутствие `HANDOFF.md` во время активной сессии является нормальным состоянием.

`HANDOFF.md` создаётся заново только при завершении или переносе сессии.

Создание `HANDOFF.md`, transport commit создания и push требуют отдельного пользовательского `+` на documentation lifecycle action.

Перед созданием `HANDOFF.md` завершённая project-scope работа должна быть committed и pushed, а project-scope uncommitted changes должны отсутствовать.

После успешного восстановления контекста новая сессия сообщает, что `HANDOFF.md` выполнил функцию передачи и готов к lifecycle cleanup.

Cleanup handoff требует отдельного пользовательского `+`: только после него можно удалить `HANDOFF.md`, сделать отдельный documentation lifecycle commit и выполнить push.

При blocking inconsistency `HANDOFF.md` сохраняется до устранения проблемы и успешного восстановления. После восстановления cleanup всё равно требует отдельного `+`.

`HANDOFF.md` не является status-документом и не поддерживается актуальным во время активной сессии.

`HANDOFF.md` не даёт authorization на file changes, commit, push, DB, pipeline, SQL/apply, production/cache actions или следующий engineering step.

Структура, exact template и validation checklist определены в:

`docs/HANDOFF_SPEC.md`

Должен содержать:

* header metadata;
* `Session close base commit`;
* repository snapshot на момент закрытия;
* current target, scope и stage;
* completed items;
* open items;
* gates and safety;
* protected working tree;
* paused or rejected paths;
* task-specific references;
* один next bounded step;
* actions after reading this handoff.

Не должен содержать:

* старые handoff-блоки;
* исторические handoff summary;
* полный changelog;
* полные diff-блоки;
* raw terminal output;
* полные правила из `RULES.md`;
* `CURRENT_OVERRIDE.md`;
* полные решения из `DECISIONS.md`;
* полные runtime outputs из `RUNTIME_CHECKS.md`;
* glossary definitions;
* startup prompt;
* полный startup reading order;
* transport SHA;
* повторные финальные блоки вида `На момент handoff: stable point ...`.

В `HANDOFF.md` всё актуальное должно быть указано один раз.

Если текущая сессия завершается при существующем `CURRENT_OVERRIDE.md`, актуальные сведения из него нужно перенести в постоянные документы, после чего удалить override-файл. Только затем можно создавать новый `HANDOFF.md`.

Если существуют незакоммиченные изменения текущего project scope, сессия не готова к handoff. Незакоммиченными могут оставаться только явно классифицированные protected user-owned changes вне текущего scope.

### `docs/HANDOFF_SPEC.md`

`HANDOFF_SPEC.md` — постоянный process/spec документ.

Он определяет:

* назначение `HANDOFF.md`;
* lifecycle handoff;
* lifecycle `CURRENT_OVERRIDE.md` относительно handoff;
* git-модель `Session close base commit`;
* exact template;
* validation checklist;
* startup prompt boundaries.

`HANDOFF_SPEC.md` не содержит текущий project status, текущий target, текущий next step или runtime evidence.

Он не удаляется после сессии и не заменяет сам `HANDOFF.md`.

Использовать его нужно при создании нового handoff или при изменении правил handoff lifecycle.

### `docs/DECISIONS.md`

Отвечает за архитектурные и процессные решения.

Должен содержать:

* утверждённые project-wide conventions;
* решения по workflow;
* решения по safety model;
* решения по allowed / forbidden modes;
* решения, которые должны пережить один чат и один handoff;
* постоянные решения, перенесённые из закрываемого `CURRENT_OVERRIDE.md`.

Примеры:

* формат даты/времени;
* запрет auto-canonical selection;
* правила SQL/apply gate;
* решение использовать `gate` как рабочий термин;
* решение о том, какой путь rejected или paused.

Не должен содержать:

* оперативный статус текущего handoff;
* временные оперативные коррективы;
* команду `codex resume`;
* последние 5 коммитов;
* длинные runtime outputs;
* raw diff;
* временные заметки чата.

### `docs/RUNTIME_CHECKS.md`

Отвечает за проверяемые runtime checks.

Должен содержать:

* что проверялось;
* какой командой проверялось;
* какой результат получен;
* был ли это dry-run или confirm/apply;
* был ли затронут DB/product data/cache/production;
* краткий вывод.

Не должен содержать:

* glossary definitions;
* handoff prompt;
* общие архитектурные решения, если они уже зафиксированы в `DECISIONS.md`;
* будущие планы без выполненной проверки;
* разрешение на SQL/apply;
* непроверенные предположения;
* полный текст `CURRENT_OVERRIDE.md`.

Если runtime check выявил существенную новую проблему, которая меняет текущую работу, сам результат фиксируется в `RUNTIME_CHECKS.md`, а временная оперативная корректива — отдельно в `CURRENT_OVERRIDE.md`.

### `docs/LEGACY_DECISIONS.md`

Отвечает за durable provenance и утверждённые предметные решения старого проекта `catalog-standardization`.

Должен содержать:

* repository-relative paths authoritative legacy sources;
* provenance источников;
* source hierarchy для legacy decisions;
* утверждённые legacy contracts;
* различение approved aliases и newly discovered candidates;
* unresolved gaps, которые требуют human decision.

Не должен содержать:

* текущий следующий шаг;
* handoff state;
* runtime check output;
* generated reports;
* SQL/apply permission;
* machine-readable runtime config;
* credentials или secrets.

`LEGACY_DECISIONS.md` не заменяет `HANDOFF.md`, `RUNTIME_CHECKS.md` или explicit SQL/apply gate.

## 3. Порядок чтения и приоритет

Основной порядок чтения задаётся в `START_HERE.md`.

После `START_HERE.md` документы читаются в таком порядке:

1. `glossary/!_README.md`
2. `docs/DOCUMENTATION_BOUNDARIES.md`
3. `docs/HANDOFF.md` — только если файл существует
4. `docs/DECISIONS.md`
5. `docs/RUNTIME_CHECKS.md`
6. актуальные specs из `docs/`, если они нужны для конкретного шага

`docs/HANDOFF_SPEC.md` не входит в обязательный reading order каждой сессии. Его читают при создании или изменении handoff lifecycle.

`docs/CURRENT_OVERRIDE.md` не входит в startup/inter-session reading order новой сессии.

## 4. Что должно быть только один раз

Следующие сущности не должны дублироваться в одном документе:

* `Functional stable point`;
* текущий target;
* текущий следующий gate;
* команда `codex resume`;
* список последних коммитов;
* текущий SQL/apply status;
* текущий production/cache status;
* recommended next bounded step.

Если эти сущности встречаются в `HANDOFF.md` несколько раз, это ошибка handoff.

В `CURRENT_OVERRIDE.md` допускается повторить только ту часть текущего состояния, которая прямо переопределяется.

Не нужно копировать туда полный handoff.

## 5. Правило ссылок вместо дублей

Если важный факт уже зафиксирован в основном документе, в другом документе нужно ссылаться на него коротко.

Правильно:

```text
Формат даты/времени зафиксирован в `docs/DECISIONS.md`.
```

Неправильно:

```text
Повторять полный блок про `DD-MM-YYYY HH:mm:ss Europe/Kyiv` в каждом handoff.
```

Правильно:

```text
Runtime check documented in `docs/RUNTIME_CHECKS.md`.
```

Неправильно:

```text
Копировать весь runtime output в `HANDOFF.md`.
```

Правильно:

```text
Подробности handoff lifecycle описаны в `docs/HANDOFF_SPEC.md`.
```

Неправильно:

```text
Копировать полный template handoff в `START_HERE.md`, `DECISIONS.md` и каждый handoff.
```

## 6. Правило для HANDOFF.md

`HANDOFF.md` не является историческим журналом или текущим status-документом.

Он должен отвечать на вопрос:

```text
Откуда безопасно продолжить работу прямо сейчас?
```

Поэтому в нём запрещены старые финальные блоки, которые могут конфликтовать с верхним актуальным состоянием.

Особенно запрещены повторные блоки вида:

```text
На момент handoff:

stable point: ...
```

Если такой блок нужен, он должен быть единственным и соответствовать `docs/HANDOFF_SPEC.md`.

Если во время активной сессии создан `CURRENT_OVERRIDE.md`, это не требует обновления `HANDOFF.md`.

При реальном завершении или переносе сессии создаётся новый `HANDOFF.md`, а не обновляется старый.

## 7. Правило для Session close base commit

В `HANDOFF.md` использовать формулировку:

```text
Session close base commit:
```

После неё указывать `HEAD` непосредственно перед transport commit, создающим `HANDOFF.md`.

Если сам `HANDOFF.md` потом коммитится отдельным transport commit, новый repo `HEAD` ожидаемо будет новее указанного `Session close base commit`.

Это нормально.

Не нужно снова править `HANDOFF.md` только ради hash самого handoff-коммита.

Transport SHA не записывается внутрь handoff.

## 8. Правило для текущего статуса

Статус текущей работы должен быть коротким, проверяемым и без двусмысленности.

Правильно:

```text
generic --confirm-apply: not run
generic SQL/apply: not executed
production/cache: untouched
```

Неправильно:

```text
вроде apply не запускали
кажется, production не трогали
```

Наличие `CURRENT_OVERRIDE.md` внутри активной сессии само по себе означает, что описанная в нём корректива действует.

Отдельное поле состояния самого override-документа не используется.

Gate status текущей задачи при необходимости указывается внутри документа отдельно, например:

```text
Current gate: BLOCKED
```

## 9. Правило для next step

В `HANDOFF.md` должен быть один рекомендуемый следующий bounded step.

Нельзя писать несколько равнозначных направлений без приоритета.

Следующий step должен быть:

* маленький;
* проверяемый;
* без скрытого SQL/apply;
* без production/cache;
* без cache rebuild;
* без auto-canonical selection;
* без auto-merge.

Если `CURRENT_OVERRIDE.md` существует внутри активной сессии, его следующий bounded step временно имеет приоритет над ранее выбранным шагом активной сессии.

Перед созданием нового handoff override должен быть закрыт и удалён.

## 10. Проверка перед commit

Перед commit документации с `HANDOFF.md` проверить `docs/HANDOFF_SPEC.md`.

Перед обычным documentation commit выполнить:

```powershell
git diff --check && git status --short
```

Если создаётся новый `HANDOFF.md`, проверить вручную:

* `CURRENT_OVERRIDE.md` отсутствует;
* `Session close base commit` указан один раз;
* transport SHA не записан;
* repository snapshot получен до transport commit;
* next bounded step один;
* gates не дублируются;
* user-owned changes не дублируются;
* нет разрешения на SQL/apply/production/cache;
* после успешного восстановления cleanup handoff требует отдельного пользовательского `+`.

## 11. Когда обновлять этот документ

`DOCUMENTATION_BOUNDARIES.md` обновляется только если меняются правила ответственности документов.

Не использовать его как:

* handoff;
* changelog;
* runtime log;
* backlog;
* текущий override;
* место для описания конкретной оперативной проблемы.
