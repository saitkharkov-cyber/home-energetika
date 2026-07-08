## SQL apply

**SQL apply** — это фактическое выполнение SQL-команд, которые меняют базу данных.

В нашем проекте это самый опасный этап, потому что после SQL apply данные в OpenCart уже реально изменены.

Например, SQL apply может:

- заменить один `attribute_id` на другой;
- обновить значения характеристик;
- удалить старые дубли;
- изменить связи товаров с характеристиками;
- затронуть данные, которые использует подборщик или cache.

### Простыми словами

SQL apply — это момент, когда мы уже не просто анализируем и не просто предлагаем изменения.

Мы реально нажимаем “применить к базе”.

До SQL apply можно смотреть, сравнивать, готовить proposals, делать review и обсуждать план.

После SQL apply данные уже изменены.

### Пример из жизни

Представь ремонт в квартире.

До apply:

- мы обсуждаем план;
- смотрим чертёж;
- выбираем материалы;
- согласовываем смету;
- проверяем, что не трогаем несущую стену.

SQL apply — это момент, когда рабочий уже взял перфоратор и начал ломать стену.

Поэтому нельзя “просто попробовать apply”.

### В нашем workflow

SQL apply стоит только в самом конце:

`target attribute meaning -> discovery -> canonical selection -> raw values inventory -> unit contract -> proposals -> review-chain -> apply-plan -> SQL apply`

То есть SQL apply возможен только после:

- найден target attribute meaning;
- выполнен DB-readonly discovery;
- пользователь выбрал canonical `attribute_id`;
- пользователь подтвердил included aliases;
- пользователь исключил similar-but-different attributes;
- собран raw values inventory;
- утверждён canonical unit / normalized_value contract;
- сгенерированы normalization proposals;
- proposals прошли review-chain;
- отдельно подготовлен apply-plan;
- пользователь отдельно подтвердил apply.

### Важное отличие от review approval

`approved` в review-chain не означает SQL apply permission.

Review approval значит только:

`это предложение нормализации выглядит корректным`

Но это ещё не значит:

`можно менять базу`

Для изменения базы нужен отдельный explicit apply-plan и отдельное подтверждение.

### Почему SQL apply нельзя делать автоматически

Потому что ошибка может затронуть реальные данные магазина.

Например:

- можно случайно объединить разные характеристики;
- можно перепутать `Максимальный напор` и `Минимальный напор`;
- можно потерять единицу измерения;
- можно записать `68м.` как строку вместо числа `68`;
- можно перепутать `m`, `mm`, `cm`, `l/min`, `m³/h`;
- можно сломать данные, которые использует selector/cache;
- можно восстановить старые неправильные значения после cache rebuild.

### SQL apply и production

В production SQL apply особенно опасен.

Для production запрещено:

- делать SQL apply без отдельного explicit approval;
- запускать cache rebuild без отдельного explicit approval;
- менять selector/cache-related attributes без approved unit contract;
- применять SQL только потому, что proposals получили статус `approved`.

### Что не является SQL apply

SQL apply — это не:

- DB-readonly discovery;
- raw values inventory;
- canonical selection;
- unit contract;
- normalization proposals;
- review-chain;
- SQL preview;
- apply-plan.

Все эти шаги могут быть подготовительными.

SQL apply — это только фактическое выполнение изменений в базе.

### Главная мысль

**SQL apply — это не анализ и не review.**

Это реальное изменение базы данных.

Поэтому в нашем проекте действует правило:

`no auto-apply`

И ещё одно правило:

`SQL apply возможен только отдельным explicit step после review и apply-plan`
