# DECISIONS — catalog-standardization-new

Этот документ хранит только утверждённые долговечные решения.

1. catalog-standardization-new — новая активная рабочая область.
2. catalog-standardization — историческая область; её не изменяют в рамках новой работы без отдельного решения пользователя.
3. docs/standardization/manual-toolkit — параллельная самостоятельная ветка ручной стандартизации, не часть catalog-standardization-new.
4. Предметная область: получение, проверка, сопоставление и безопасный импорт характеристик производителей для каталога OpenCart.
5. Общая последовательность контроля может повторяться, но parsers, matchers и generators могут быть специализированы под конкретного производителя.
6. Универсализация не является самостоятельной целью.
7. Generic-компонент создаётся только после нескольких реально одинаковых подтверждённых кейсов.
8. Нельзя заранее строить framework, платформу, административный интерфейс или дополнительные архитектурные уровни.
9. Локальные служебные файлы, preview, временные результаты и разовые выгрузки БД не включаются в Git.
10. Не переносить как действующие артефакты: catalog-standardization/Catalog_Standardization.xlsx, catalog-standardization/sources/sumoto/opencart_export.csv, catalog-standardization/scripts/local_proxy.txt, catalog-standardization/scripts/vinko_parsed_preview.csv.
11. Пустые normalizers/, validators/, sources/ и иные каталоги не создаются без реальной необходимости.
12. Каждый создаваемый или содержательно обновляемый Markdown-документ завершается временной отметкой формата YYYY-MM-DD HH:mm:ss Europe/Kyiv.
13. SESSION_START_PROMPT запускает процесс; START_HERE определяет startup flow; HANDOFF передаёт временное состояние; спецификации определяют форму своих артефактов; DOCUMENTATION_BOUNDARIES определяет общие роли документов.
14. HANDOFF.md и CURRENT_OVERRIDE.md — временные lifecycle-файлы и обычно отсутствуют.
15. Создание, commit, push, удаление и cleanup HANDOFF.md требуют отдельных пользовательских разрешений.
16. SQL/apply, production и cache actions всегда требуют отдельного явного gate пользователя.

---

Последнее обновление: 2026-07-23 21:25:22 Europe/Kyiv
