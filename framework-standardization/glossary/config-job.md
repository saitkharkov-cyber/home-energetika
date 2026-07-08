## Config / Job

**Config / Job** — это настройка конкретной задачи обработки характеристики.

В нашем проекте это не сама логика, не parser, не normalizer и не SQL.

Это “паспорт задания”:

- какую характеристику обрабатываем;
- в какой категории;
- с каким canonical contract;
- с какими правилами;
- через какой parser / normalizer family.

### Простыми словами

Job отвечает на вопрос:

**Что именно мы сейчас хотим обработать?**

Например:

`обрабатываем характеристику “Максимальный напор” в категории “Скважинные насосы”, canonical key: pump_max_head, canonical unit: m, normalized_value: число в метрах`

А config — это файл, где такая задача описана в машинно-читаемом виде.

### Важная мысль

В новой архитектуре `config/job` не является началом работы.

Нельзя начинать так:

`давай создадим config/job для pump_max_head`

Потому что мы можем ошибиться:

- характеристика в базе называется иначе;
- есть несколько похожих `attribute_id`;
- часть похожих названий не является дублями;
- единица измерения может быть неоднозначной;
- raw values могут быть грязными;
- parser может не подходить.

### Правильный порядок

Сначала характеристика должна пройти workflow:

`target attribute meaning -> discovery похожих attribute names -> canonical selection человеком -> include/exclude aliases -> raw values inventory -> canonical unit / normalized_value contract -> proposals generation -> review-chain -> только потом config/job`

### Что может хранить config/job

Job может описывать:

- canonical key;
- canonical `attribute_id`;
- canonical `attribute_name`;
- category scope;
- included alias `attribute_ids`;
- excluded `attribute_ids`;
- canonical unit;
- normalized_value contract;
- parser / normalizer family;
- safety flags;
- limits;
- output / report settings.

### Пример из жизни

Представь, что у тебя есть повар и кухня.

Parser / normalizer — это умение повара готовить:

- резать;
- варить;
- жарить;
- смешивать ингредиенты.

А job — это конкретный заказ:

`приготовить борщ на 5 человек, без мяса, с фасолью, подавать горячим`

Повар не становится новым поваром под каждый заказ.

Он использует свои общие навыки, но заказ говорит, что именно нужно сделать сейчас.

Так же и у нас:

`одна характеристика = один job/contract`

`один тип значений = один parser/normalizer family`

Новая характеристика не обязательно требует новый PHP-обработчик.

### Пример в нашем проекте

Для `pump_max_head` будущий job мог бы сказать:

- canonical key: `pump_max_head`;
- attribute_id: `12`;
- attribute_name: `Максимальный напор`;
- category_id: `11900213`;
- canonical unit: `m`;
- normalized_value: `decimal meters`;
- parser family: `numeric_with_unit`.

Но такой job можно создавать только после того, как пройдены:

- discovery;
- canonical selection;
- raw values inventory;
- unit contract.

### Главная мысль

**Config/job — это не место, где мы угадываем характеристику.**

Это место, где мы фиксируем уже принятое решение:

`мы уже выбрали canonical attribute -> мы уже поняли aliases -> мы уже посмотрели raw values -> мы уже утвердили unit contract -> теперь можно описать задачу для выполнения`