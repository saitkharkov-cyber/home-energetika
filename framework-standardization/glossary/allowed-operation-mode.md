# Allowed operation mode / разрешённый режим работы

`Allowed operation mode` — это явно разрешённый режим выполнения текущего шага.

Он отвечает на вопрос: что именно в этом шаге можно делать, а что запрещено.

В рамках `framework-standardization` режим работы должен быть указан до запуска Codex, CLI-команды или implementation.

Примеры allowed operation mode:

* только чтение документации;
* только анализ без изменения файлов;
* console output only;
* DB readonly;
* dry-run;
* создание одного markdown-файла;
* изменение только одного указанного файла;
* runtime check без изменения данных.

Allowed operation mode не должен угадываться автоматически.

Если разрешён только DB readonly, значит запрещены:

* SQL apply;
* SQL generation;
* изменение БД;
* изменение schema;
* cache rebuild;
* production write operations.

Если разрешён только console output, значит запрещены:

* создание файлов;
* изменение файлов;
* commit;
* runtime artifacts для commit.

Codex не должен расширять allowed operation mode самостоятельно.

Любой переход к более сильному режиму работы требует отдельного gate или human decision.
