# Catalog Standardization New

## Назначение

catalog-standardization-new — новая активная рабочая область репозитория home-energetika для получения, проверки, сопоставления и безопасного импорта характеристик производителей в каталог OpenCart.

- catalog-standardization/ — историческая область.
- docs/standardization/manual-toolkit/ — параллельная самостоятельная ветка ручной стандартизации.
- pump-selector/ использует стандартизированные данные, но не отвечает за их исправление.

Полная архитектура этих областей здесь не описывается.

## Текущая фактическая структура

~~~text
catalog-standardization-new/
├── README.md
├── docs/
│   ├── DECISIONS.md
│   ├── DOCUMENTATION_BOUNDARIES.md
│   ├── HANDOFF_SPEC.md
│   ├── RULES.md
│   ├── RUNTIME_CHECKS.md
│   ├── SESSION_START_PROMPT.md
│   ├── SESSION_START_PROMPT_SPEC.md
│   └── START_HERE.md
└── scripts/
    ├── generators/
    ├── matchers/
    └── parsers/
~~~

Каталоги scripts пока являются подготовленным каркасом и содержат только .gitkeep.

## С чего начинать работу

1. Новый ChatGPT-чат запускается через docs/SESSION_START_PROMPT.md.
2. Startup flow определяется в docs/START_HERE.md.
3. Рабочая дисциплина задаётся docs/RULES.md.
4. Роли документов определяются docs/DOCUMENTATION_BOUNDARIES.md.
5. Постоянные утверждённые решения хранятся в docs/DECISIONS.md.
6. Существенные выполненные проверки фиксируются в docs/RUNTIME_CHECKS.md.
7. docs/HANDOFF.md читается только при наличии.

## Основные принципы

- Один маленький bounded step.
- Любое изменение — только после отдельного пользовательского +.
- Специализированные parsers, matchers и generators разрешены.
- Универсализация не является самостоятельной целью.
- Перед изменением данных обязательны inventory, analysis, preview и explicit gate.
- Production SQL/apply, cache, commit и push требуют отдельного разрешения.
- Локальные служебные файлы, preview, временные результаты и разовые DB exports не входят в Git.

## Временные lifecycle-файлы

- docs/HANDOFF.md создаётся только при завершении или переносе сессии.
- docs/CURRENT_OVERRIDE.md допускается только для существенной оперативной коррективы активной сессии.
- Оба файла обычно отсутствуют.
- Подробные правила находятся в docs/HANDOFF_SPEC.md и docs/DOCUMENTATION_BOUNDARIES.md.

Эти файлы не создаются этим README.

## Будущие постоянные документы

По отдельным bounded-шагам будут созданы:

- PROJECT_MASTER_SUMMARY.md;
- CATALOG_STANDARD.md.

---

Последнее обновление: 2026-07-23 21:34:12 Europe/Kyiv
