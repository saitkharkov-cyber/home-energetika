# DRY_RUN_PROTECTION_NORMALIZER_SPEC - approved read-only normalizer policy

Spec status: approved

Approved by: user

Approval date: 2026-07-13

Implementation status: not implemented

Normalizer status: required, not ready

## 1. Назначение и approved identity

Этот документ является approved normalization policy для будущего read-only normalizer approved semantic contract `dry_run_protection`. Class name является утверждённой implementation identity будущего класса, но сам класс ещё не существует и normalizer не становится ready.

```text
normalizer key: boolean_yes_no
class: FrameworkStandardization\Normalizer\BooleanYesNoNormalizer
method: normalize($rawValue)
value type: boolean_enum
canonical unit: отсутствует
allowed canonical values: Да, Нет
```

Это approved implementation identity. Существующий contract остаётся с `normalizer_key = ''`.

## 2. Основной принцип

Normalizer должен быть pure, deterministic и совместимым с PHP 5.6. Он принимает любой `$rawValue`, но успешно обрабатывает только string input и возвращает диагностический normalization result.

Он не зависит от БД или runtime config, не читает product/category data, не выполняет SQL, не создаёт artifacts, не принимает semantic decisions, не расширяет approved canonical domain и не имеет pipeline side effects.

## 3. Approved result schema

Каждый result содержит:

```text
status
value_type
canonical_value
unit
warnings
ambiguity_reason
metadata
```

`value_type` всегда `boolean_enum`, а `unit` всегда пустая строка. `canonical_value` строго `Да`, `Нет` или `null`; `warnings` всегда является array; `ambiguity_reason` является пустой строкой либо точной machine-readable reason. Metadata не содержит SQL, apply plan или данные из БД.

Отдельное поле `reason` намеренно отсутствует: canonical reason передаётся одновременно единственным элементом `warnings` и значением `ambiguity_reason`, в стиле rich normalizer result.

| Status/case | warnings | ambiguity_reason |
| --- | --- | --- |
| successful normalized value | `array()` | `''` |
| unsupported string | `array('unsupported_boolean_value')` | `unsupported_boolean_value` |
| empty string or null | `array('empty_value')` | `empty_value` |
| non-string scalar | `array('non_string_scalar_value')` | `non_string_scalar_value` |
| array, object or resource | `array('non_scalar_value')` | `non_scalar_value` |
| mixed canonical tokens | `array('mixed_boolean_values')` | `mixed_boolean_values` |

Во всех unsuccessful cases `canonical_value = null`, `unit = ''` и `value_type = boolean_enum`. В successful cases `status = normalized`, `canonical_value` равен `Да` или `Нет`, `warnings = array()` и `ambiguity_reason = ''`.

## 4. Input types и безопасная metadata

Классификация `$rawValue` происходит в следующем порядке.

1. `null`: `status = invalid`, reason `empty_value`.
2. Non-string scalar (`integer`, `float`, native `boolean`): `status = invalid`, reason `non_string_scalar_value`. Преобразование через `(string)` запрещено.
3. `array`, `object` или `resource`: `status = invalid`, reason `non_scalar_value`. Их содержимое не сериализуется; пользовательские методы object не вызываются; resource не читается.
4. `string`: применяется только утверждённый boundary trim, затем exact/mixed/unsupported classification.

Для string input metadata содержит:

```text
original_value
trimmed_value
boundary_whitespace_changed
input_type = string
```

Для `null` metadata содержит `input_type = NULL`. Для non-string scalar metadata содержит только `input_type = integer|double|boolean` и не сохраняет scalar value. Для `array`, `object` или `resource` metadata содержит только `input_type = array|object|resource`. Для object дополнительно допускается безопасный class name через `get_class`, без вызова методов и без сериализации object.

## 5. Unicode boundary trim

Обычный PHP `trim()` не определяет эту policy, потому что не покрывает весь утверждённый Unicode whitespace set. Удаляются только с начала и конца string следующие code points:

```text
U+0009-U+000D
U+0020
U+00A0
U+1680
U+2000-U+200A
U+2028
U+2029
U+202F
U+205F
U+3000
U+FEFF
```

Эти символы удаляются только с границ. Внутренние символы не меняются, внутренние пробелы не схлопываются, punctuation не удаляется, регистр не меняется, Unicode normalization form не меняется, а другие code points автоматически whitespace не считаются. `boundary_whitespace_changed = true` только если исходная string отличается от trimmed string byte-for-byte.

## 6. Exact classification и mixed-token detection

После boundary trim string классифицируется строго в таком порядке:

1. Если string стала пустой: `invalid`, `empty_value`.
2. Если string byte-for-byte равна `Да`: `normalized`, canonical value `Да`.
3. Если string byte-for-byte равна `Нет`: `normalized`, canonical value `Нет`.
4. Если найдены оба standalone approved tokens: `review_required`, `mixed_boolean_values`.
5. Любая другая string: `unsupported`, `unsupported_boolean_value`.

Mixed result возникает только когда в одной trimmed string найдены одновременно standalone case-sensitive token `Да` и standalone case-sensitive token `Нет`. Conceptual UTF-8 patterns:

```regex
(?<![\p{L}\p{N}_])Да(?![\p{L}\p{N}_])
(?<![\p{L}\p{N}_])Нет(?![\p{L}\p{N}_])
```

Unicode mode обязателен, case-insensitive modifier запрещён, оба patterns должны совпасть в одной строке, а порядок tokens неважен. Punctuation, whitespace, slash или союз между standalone tokens допустимы; token внутри более длинного слова standalone не считается.

Mixed: `Да/Нет`, `Да, Нет`, `Да или Нет`, `Нет либо Да`, `(Да)  (Нет)`.

Не mixed: `ДаНет`, `Далее`, `КоНет`, `НЕТ/ДА`, `да/нет`, `Есть/Нет`. Последние строки, если не равны exact canonical values, имеют `unsupported_boolean_value`; `Есть/Нет` содержит только один approved standalone token и поэтому не mixed.

Canonical domain никогда не расширяется за пределы `Да`, `Нет`, `null`. Case variants, punctuation, synonyms, descriptive forms, numeric strings и native booleans не маппятся автоматически.

## 7. Approved status matrix

| Input | Expected status | Canonical value | Reason |
| --- | --- | --- | --- |
| `Да` | normalized | `Да` | none |
| `Нет` | normalized | `Нет` | none |
| ASCII boundary whitespace + `Да` | normalized | `Да` | none |
| boundary NBSP + `Нет` | normalized | `Нет` | none |
| boundary `U+FEFF` + `Да` | normalized | `Да` | none |
| `да` | unsupported | null | unsupported_boolean_value |
| `НЕТ` | unsupported | null | unsupported_boolean_value |
| `Да.` | unsupported | null | unsupported_boolean_value |
| `Есть` | unsupported | null | unsupported_boolean_value |
| string `"1"` | unsupported | null | unsupported_boolean_value |
| integer `1` | invalid | null | non_string_scalar_value |
| float `1.0` | invalid | null | non_string_scalar_value |
| native `true` | invalid | null | non_string_scalar_value |
| native `false` | invalid | null | non_string_scalar_value |
| `Да/Нет` | review_required | null | mixed_boolean_values |
| `Да, Нет` | review_required | null | mixed_boolean_values |
| `Нет либо Да` | review_required | null | mixed_boolean_values |
| `ДаНет` | unsupported | null | unsupported_boolean_value |
| `Далее` | unsupported | null | unsupported_boolean_value |
| `КоНет` | unsupported | null | unsupported_boolean_value |
| `НЕТ/ДА` | unsupported | null | unsupported_boolean_value |
| `Есть/Нет` | unsupported | null | unsupported_boolean_value |
| empty string | invalid | null | empty_value |
| whitespace-only | invalid | null | empty_value |
| `null` | invalid | null | empty_value |
| array/object/resource | invalid | null | non_scalar_value |
| internal Unicode whitespace inside a noncanonical string | unsupported | null | unsupported_boolean_value |

`normalized` в этой таблице является результатом pure normalizer и ещё не определяет окончательный status внешнего proposal/integration layer.

## 8. Source-aware status boundary

Normalizer не получает `source_attribute_id`, поэтому он не решает `unchanged` против `normalized`.

Future proposal/integration layer определяет, что canonical source `47` с raw value, уже строго равным canonical value без технического изменения, может быть `unchanged`; alias source `82` никогда не `unchanged`, даже если raw text равен `Да`; любое boundary whitespace transformation не `unchanged`.

Эта integration logic находится вне normalizer и не реализуется данным шагом.

## 9. Contract and registry boundary

Существующий contract сохраняет `normalizer_key = ''`, `normalizer_ready = false`, `read_only_ready = false` и `apply_ready = false`.

Approved normalizer key `boolean_yes_no` не означает, что normalizer реализован или зарегистрирован. Будущая регистрация в `NormalizerRegistry` требует отдельного bounded step, а обновление machine-readable contract - отдельного human-approved step. Наличие approved policy не разрешает pipeline execution.

## 10. Proposed implementation files

В будущем, но не сейчас:

* `framework-standardization/src/Normalizer/BooleanYesNoNormalizer.php`;
* `framework-standardization/tests/boolean_yes_no_normalizer_static_checks.php`.

Путь теста выбран по текущей repository convention `tests/*_static_checks.php`; перед implementation его нужно сверить с актуальной naming convention.

## 11. Future test matrix

Будущие static checks должны покрыть PHP 5.6 syntax, exact `Да`/`Нет`, ASCII и NBSP boundary whitespace, null, integer, float, native boolean, array, object без method calls или serialization, resource без reading, exact approved Unicode boundary set, unsupported code point вне утверждённого set, internal whitespace preservation и byte-for-byte `boundary_whitespace_changed`.

Также checks должны покрыть standalone-token positive cases, substring false-positive protection, case-sensitive mixed detection, one-token-only string is not mixed, lowercase и uppercase unsupported, punctuation, synonyms, numeric strings, exact shape `warnings`, exact `ambiguity_reason`, отсутствие отдельного поля `reason`, canonical output только из `Да`, `Нет`, `null`, deterministic repeated call и отсутствие SQL/apply/product/cache fields в result.

Checks должны подтвердить отсутствие DB и filesystem access. Кириллица сравнивается UTF-8-safe способом; для PowerShell `php -r` assertions при необходимости используется Base64 ASCII-safe representation.

## 12. Safety markers

```text
read_only_design = 1
php_implemented = 0
registry_changed = 0
contract_changed = 0
pipeline_wired = 0
db_accessed = 0
normalization_executed_on_catalog = 0
sql_generated = 0
apply_plan_created = 0
apply_performed = 0
product_data_changed = 0
production_touched = 0
cache_rebuild_performed = 0
```
