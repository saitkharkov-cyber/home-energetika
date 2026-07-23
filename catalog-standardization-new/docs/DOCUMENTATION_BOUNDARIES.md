# DOCUMENTATION BOUNDARIES — catalog-standardization-new

У каждого важного факта есть один основной документ-источник. В остальных документах используется краткая ссылка, а не полное дублирование. Конфликтующие копии текущего состояния запрещены.

## Постоянные документы

### README.md

Тип: постоянная навигационная карта рабочей области. Отвечает за назначение области, фактическую структуру, краткий reading order и ссылки на постоянные документы. Не отвечает за текущий target или stage, handoff, runtime results, полный набор архитектурных решений и текущий next step. Файл пока не создан; эта роль зарезервирована для него.

### PROJECT_MASTER_SUMMARY.md

Тип: постоянный master-context документ. Отвечает за назначение catalog-standardization-new, границы с Pump Selector и ручной стандартизацией, предметную область, устойчивые принципы и общую модель работы. Не отвечает за динамический статус, текущий target, результаты конкретных запусков, handoff, raw outputs или историю каждого шага. Файл пока не создан; эта роль зарезервирована для него.

### CATALOG_STANDARD.md

Тип: постоянный нормативный стандарт данных. Отвечает за канонические правила хранения характеристик, формат названий и значений, единицы измерения, канонические attribute_id, допустимые и недопустимые значения и безопасную миграцию данных. Не отвечает за текущий target, handoff, runtime checks, результаты отдельного производителя или общую рабочую дисциплину. Файл пока не создан; эта роль зарезервирована для него.

### docs/SESSION_START_PROMPT.md

Тип: короткий постоянный copy-ready prompt. Его единственная функция — запустить bootstrap, указать repository, working area и branch, направить к docs/START_HERE.md, потребовать startup flow и отчёт, запретить изменения до отдельного + и остановиться после отчёта. Он не содержит полный startup process, dynamic state, handoff, target, stage, next step, инструкции Codex или подробные gates.

### docs/SESSION_START_PROMPT_SPEC.md

Тип: постоянная спецификация. Отвечает только за форму, обязательные элементы, запрещённое содержимое, размер и validation checklist для SESSION_START_PROMPT.md. Не хранит текущий статус или startup flow.

### docs/START_HERE.md

Тип: постоянный startup-протокол. Отвечает за восстановление контекста, reading order, проверку repository/working area/branch, чтение HANDOFF.md только при наличии, сверку handoff со свежим Git-state, startup-отчёт и остановку до отдельного +. Не является copy-ready prompt, dynamic state последней сессии, полным handoff template, текущим target или историей проекта.

### docs/HANDOFF.md

Тип: временный tracked transport artifact, обычно отсутствующий во время активной сессии. Отвечает только за snapshot завершённой сессии, безопасную передачу контекста и ровно один следующий bounded step. Не является master summary, status-документом, changelog, backlog, roadmap или разрешением на file changes, SQL/apply, commit, push, production и следующий engineering step. Сейчас файл не создаётся.

### docs/HANDOFF_SPEC.md

Тип: постоянная спецификация. Отвечает за форму и lifecycle HANDOFF.md, Session close base commit, validation checklist и cleanup lifecycle. Не хранит текущий status, target или runtime evidence.

### docs/CURRENT_OVERRIDE.md

Тип: необязательный временный документ активной сессии. Создаётся только для существенной оперативной коррективы target, scope, gate, блокировки, последовательности работы или следующего bounded step. Он не является handoff, не входит в startup/inter-session reading order и не передаётся новой сессии; не может отменять пользовательские инструкции, safety, SQL/apply gate или production restrictions. Перед handoff актуальные постоянные сведения переносятся в свои документы, а файл закрывается и удаляется. Сейчас файл не создаётся.

### docs/DECISIONS.md

Тип: постоянный журнал утверждённых архитектурных и процессных решений. Отвечает за durable decisions, workflow conventions, safety model, allowed/forbidden modes, отклонённые или приостановленные пути и решения, которые должны пережить чат и handoff. Не отвечает за текущий status, runtime outputs, raw diff, временные заметки или текущий next step.

### docs/RUNTIME_CHECKS.md

Тип: постоянный журнал существенных проверок на реальных данных. Отвечает за то, что было проверено, метод, режим, результат, влияние на DB/product data/production/cache и краткий проверяемый вывод. Не отвечает за планы, неподтверждённые предположения, разрешение на SQL/apply, архитектурные решения или полный raw output. Обычные syntax checks и стандартные тесты не записываются, если не выявили новый существенный runtime-факт.

### docs/RULES.md

Тип: постоянные правила рабочей дисциплины. Отвечает за роли пользователя, ChatGPT и Codex, bounded-step workflow, gates, Git discipline, запрет scope expansion, временные отметки и общие ограничения реализации. Не отвечает за текущий target, dynamic session state, handoff snapshot, runtime evidence или конкретные архитектурные решения, уже зафиксированные в DECISIONS.md.

## Reading и обновление

- Основной reading order задаёт START_HERE.md.
- HANDOFF_SPEC.md читается при создании или изменении handoff lifecycle, а не обязательно в начале каждой сессии.
- CURRENT_OVERRIDE.md не входит в startup нового чата.
- Ссылки предпочтительнее дублирования.
- Этот документ обновляется только при изменении ролей документов.
- Его нельзя использовать как changelog, backlog, handoff или runtime log.

---

Последнее обновление: 2026-07-23 21:09:28 Europe/Kyiv
