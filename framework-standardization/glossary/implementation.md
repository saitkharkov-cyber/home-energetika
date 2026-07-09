## Implementation

`Implementation` — это этап фактического создания или изменения кода, конфигурации, команд, файлов или runtime-логики проекта. 
Если проще, то Implementation = реализация / этап внесения изменений.

В рамках `framework-standardization` implementation начинается только после явного подтверждения `+`.

До implementation допустимы:

* анализ;
* чтение документации;
* уточнение архитектуры;
* подготовка specification;
* подготовка bounded prompt для Codex;
* обсуждение scope и ограничений.

Implementation не должна начинаться автоматически после обсуждения идеи, выбора направления или подготовки specification.

Если задача передаётся в Codex, ChatGPT должен явно указать:

* какие файлы читать;
* какой один файл менять или создавать;
* что запрещено менять;
* какие проверки выполнять;
* какие проверки не выполнять;
* что Codex не должен делать commit без отдельного разрешения.

Implementation считается отдельным шагом и не смешивается с анализом, discovery, review или apply-plan.

## Bounded implementation

`Bounded implementation` — это ограниченная реализация одного заранее определённого шага.

В рамках `framework-standardization` bounded implementation выполняется только после явного `+` и только в пределах заданного scope.

Перед bounded implementation должны быть понятны:

- цель шага;
- разрешённые файлы;
- запрещённые изменения;
- allowed operation mode;
- expected output format;
- проверки;
- правило commit.

Bounded implementation не должна расширяться автоматически и не должна превращаться в соседние задачи.
