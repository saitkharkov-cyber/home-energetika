# С чего начать — стандартизация характеристик

## 1. Назначение

Этот документ — постоянный протокол входа нового чистого ChatGPT-чата в проект `HmEnerg_Характеристики / framework-standardization`.

Общие роли и границы ответственности документов определяет `docs/DOCUMENTATION_BOUNDARIES.md`.

`START_HERE.md` определяет постоянный startup process и не хранит текущее состояние проекта.

## 2. Постоянный startup flow

Новый ChatGPT должен выполнить следующие действия в указанном порядке:

1. Подтвердить repository identity, working project и branch.
2. Прочитать `glossary/!_README.md`.
3. Прочитать `docs/DOCUMENTATION_BOUNDARIES.md`.
4. Прочитать `docs/HANDOFF.md`, только если файл существует.
5. Получить свежие Git-факты и считать их фактическим состоянием репозитория.
6. Прочитать `docs/RULES.md`.
7. Прочитать `docs/DECISIONS.md`.
8. Прочитать `docs/LEGACY_DECISIONS.md`, если задача связана с legacy-характеристиками.
9. Прочитать `docs/RUNTIME_CHECKS.md`.
10. Прочитать только specs, релевантные восстановленному target и необходимые для достоверного выбора одного следующего bounded step.

## 3. Обработка HANDOFF.md

`HANDOFF.md` — временный snapshot предыдущей ChatGPT-сессии. Его читает новый ChatGPT; он не передаётся Codex как общий рабочий документ.

Если файл существует, ChatGPT сверяет snapshot со свежим Git-state. Свежие проверенные Git-факты имеют приоритет над snapshot. Blocking inconsistency останавливает восстановление контекста до получения недостающего факта.

После успешного восстановления ChatGPT сообщает, что handoff выполнил функцию передачи. Само чтение handoff не разрешает его удаление, commit, push или engineering work. Lifecycle cleanup возможен только после отдельного пользовательского разрешения.

Подробная структура и lifecycle определены в `docs/HANDOFF_SPEC.md`.

Отсутствие `HANDOFF.md` — нормальное состояние активной сессии; handoff-specific состояние в этом случае не предполагается.

## 4. Отчёт о восстановлении

После успешного завершения startup flow либо сразу после обнаружения blocking inconsistency новый ChatGPT кратко сообщает:

* подтверждённые repository identity, project и branch;
* фактический Git-state;
* был ли найден и успешно обработан `HANDOFF.md`;
* восстановленные target, scope, stage, completed и open items — только в той мере, в которой они подтверждены;
* действующие gates и safety-ограничения;
* protected user-owned changes;
* ровно один рекомендуемый следующий bounded step при успешно восстановленном контексте;
* blocking inconsistency и недостающий факт вместо продолжения, если она обнаружена.

После отчёта новый ChatGPT обязан остановиться. До отдельного пользовательского разрешения он не должен удалять `HANDOFF.md`, менять файлы, начинать implementation, обращаться к БД, запускать pipeline, готовить или выполнять SQL, делать commit или push.

## 5. Переход к активной работе

После успешного восстановления и необходимых lifecycle actions работа продолжается по схеме: пользователь -> ChatGPT -> Codex -> документация.

ChatGPT выбирает один следующий bounded step и направляет Codex отдельным task-specific prompt. Codex не получает общее поручение восстановить проект по `START_HERE.md`.

Каждый новый bounded step, предполагающий изменения, требует отдельного явного пользовательского разрешения. Commit выполняет пользователь по предложенной ChatGPT однострочной PowerShell-команде.

Краткие правила взаимодействия ChatGPT и Codex определены в `docs/RULES.md`.

## 6. Что START_HERE.md не содержит

Этот документ не содержит:

* current HEAD или stable-point SHA;
* текущий target, stage или next step;
* результаты последней сессии;
* полный handoff;
* exact handoff template;
* архитектурные решения;
* runtime evidence;
* copy-ready startup prompt;
* prompt для Codex.
