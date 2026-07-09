# Normalizer / нормализатор

`Normalizer` — это логика или инструмент, который приводит исходные значения к утверждённому формату.

В рамках `framework-standardization` normalizer работает только по заранее утверждённому contract.

Normalizer может:

* читать raw values;
* очищать значения от лишнего текста;
* приводить единицы измерения к canonical unit;
* формировать `normalized_value`;
* генерировать proposals;
* отмечать unresolved cases;
* выводить warnings.

Normalizer не должен угадывать смысл характеристики автоматически.

Normalizer не должен сам выбирать canonical attribute.

Normalizer не должен сам включать или исключать aliases.

Normalizer не должен менять БД, production-данные, cache или schema без отдельного apply-step.

Normalizer не является apply.

Normalizer не является human decision.

Если значение нельзя безопасно нормализовать по contract, normalizer должен оставить его unresolved или вывести warning.
