# DOCUMENTATION_BOUNDARIES — границы ответственности документов

Документ фиксирует, какой документ за что отвечает в `framework-standardization`.

Цель — не допускать дублей, устаревших повторов и конфликтующих статусов между:

1. `framework-standardization/glossary/!_README.md`
2. `framework-standardization/docs/HANDOFF.md`
3. `framework-standardization/docs/DECISIONS.md`
4. `framework-standardization/docs/RUNTIME_CHECKS.md`

## 1. Главный принцип

У каждого важного факта должен быть один основной документ-источник.

Другие документы могут ссылаться на этот факт кратко, но не должны заново переписывать его полностью.

Если факт уже описан в одном документе, в другом документе нужно писать короткую ссылку или краткое состояние, а не копировать весь блок.

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
* архитектурные решения проекта, если они не являются правилами ведения glossary.

### `docs/HANDOFF.md`

Отвечает только за оперативную передачу текущего состояния в новый чат.

Должен содержать:

* дату handoff;
* проект и рабочую область;
* `Functional stable point`;
* команду `codex resume`, если она есть;
* последнюю цепочку коммитов;
* текущий target;
* что уже сделано;
* что ещё не выполнялось;
* paused / rejected path;
* текущий следующий gate;
* один рекомендуемый следующий bounded step;
* короткий стартовый prompt для нового ChatGPT, если нужен.

Не должен содержать:

* старые handoff-блоки;
* исторические handoff summary;
* полный changelog;
* полные diff-блоки;
* raw terminal output;
* полные правила из `RULES.md`;
* полные решения из `DECISIONS.md`;
* полные runtime outputs из `RUNTIME_CHECKS.md`;
* glossary definitions;
* повторные финальные блоки вида `На момент handoff: stable point ...`.

В `HANDOFF.md` всё актуальное должно быть указано один раз.

### `docs/DECISIONS.md`

Отвечает за архитектурные и процессные решения.

Должен содержать:

* утверждённые project-wide conventions;
* решения по workflow;
* решения по safety model;
* решения по allowed / forbidden modes;
* решения, которые должны пережить один чат и один handoff.

Примеры:

* формат даты/времени;
* запрет auto-canonical selection;
* правила SQL/apply gate;
* решение использовать `gate` как рабочий термин;
* решение о том, какой путь rejected или paused.

Не должен содержать:

* оперативный статус текущего handoff;
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
* непроверенные предположения.

## 3. Что должно быть только один раз

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

## 4. Правило ссылок вместо дублей

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

## 5. Правило для HANDOFF.md

`HANDOFF.md` не является историческим журналом.

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

Если такой блок нужен, он должен быть единственным и находиться в верхнем summary.

## 6. Правило для stable point

В `HANDOFF.md` использовать формулировку:

```text
Functional stable point before this handoff update:
```

После неё указывать последний содержательный commit, от которого безопасно продолжать работу.

Если сам `HANDOFF.md` потом коммитится отдельным doc-only commit, новый repo `HEAD` может быть новее указанного `Functional stable point`.

Это нормально.

Не нужно снова править `HANDOFF.md` только ради hash самого handoff-коммита.

## 7. Правило для текущего статуса

Статус должен быть коротким, проверяемым и без двусмысленности.

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

## 8. Правило для next step

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

## 9. Проверка перед commit

Перед commit документации проверить:

```powershell
Select-String -Path framework-standardization/docs/HANDOFF.md -Pattern "stable point","Codex resume","SQL/apply","production/cache","next gate","На момент handoff"
```

Проверить вручную:

* `Functional stable point` указан один раз как актуальный;
* старый stable point не указан как текущий;
* `Codex resume` указан один раз;
* нет старого блока `На момент handoff`;
* нет второго финального summary с другим stable point;
* нет разрешения на SQL/apply/production/cache;
* следующий bounded step один.

Затем выполнить:

```powershell
git diff --check && git status --short
```

## 10. Когда обновлять этот документ

`DOCUMENTATION_BOUNDARIES.md` обновляется только если меняются правила ответственности документов.

Не использовать его как handoff, changelog или runtime log.
