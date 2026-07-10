# VOLTAGE_NORMALIZATION_POLICY — approved voltage normalization policy

Дата: 10-07-2026

Policy status: approved

Approved by: user

Approval date: 10-07-2026

Этот документ является утверждённой policy для normalization behavior характеристики `Напряжение`.

Он не является разрешением SQL/apply, apply-plan, `--confirm-apply`, product/category changes, production/cache actions или cache rebuild.

Источник фактов:

`framework-standardization/runtime/reports/submersible_pumps_voltage/20260710155454_6a5115ce3c26`

Использованные artifacts:

* `inventory.json`;
* `proposals.json`;
* `discovery.md`;
* `manifest.json`.

## 1. Approved legacy boundary

### Canonical output

Допустимые ненулевые canonical values:

* `'220'`;
* `'380'`.

Другие ненулевые canonical values запрещены.

Machine-readable contract хранит canonical values как строки, чтобы normalizer output и job contract сравнивались strict без PHP type juggling.

Разрешённый non-value результат для unresolved cases:

* `null`.

### Canonical attribute

* attribute_id: `15`;
* canonical name: `Напряжение (В)`.

### Approved duplicate

* `57 -> 15`.

### Approved aliases

Approved aliases:

* `57`;
* `79`;
* `99`;
* `118`;
* `170`.

### Excluded candidate

Excluded discovery candidate:

* `73`.

`73` относится к `Параметры котла` и не входит в voltage aliases.

## 2. Current review package facts

Package:

`framework-standardization/runtime/reports/submersible_pumps_voltage/20260710155454_6a5115ce3c26`

Manifest facts:

* runtime mode: `live_db_readonly`;
* database: `he_framework_prod_snapshot_20260710`;
* category: `11900213`;
* inventory rows: `618`;
* proposals total: `618`;
* unique `source_attribute_id + raw_value` groups: `34`;
* proposal statuses: `normalized: 196`, `unchanged: 399`, `review_required: 22`, `invalid: 1`, `unsupported: 0`;
* current value types: `single: 555`, `range: 40`, `compound: 22`, `invalid: 1`.

Safety markers:

```text
read_only: 1
sql_generated: 0
apply_plan_created: 0
apply_performed: 0
safe_to_apply: 0
sql_apply_allowed: 0
production_ready: 0
cache_rebuild_allowed: 0
```

## 3. Full raw value registry

Полный реестр уникальных сочетаний `source_attribute_id + raw_value` из текущего package.

| source_attribute_id | raw_value | usage count | sample product_ids | detected voltages | range | phase | frequency | value structure |
| ------------------- | --------- | ----------: | ------------------ | ----------------- | ----- | ----- | --------- | --------------- |
| `15` | `220V` | 157 | `1068`, `1069`, `1070` | `220` | no | none | none | formatting-only single |
| `15` | `380V` | 144 | `1087`, `1091`, `1093` | `380` | no | none | none | formatting-only single |
| `15` | `220` | 49 | `1812`, `1813`, `1814` | `220` | no | none | none | exact canonical |
| `15` | `380` | 49 | `1845`, `1846`, `1847` | `380` | no | none | none | exact canonical |
| `15` | `1~230 В, 50 Гц / 1~220 В, 60 Гц / 1~110 В, 60 Гц* (номинальное)` | 1 | `8192` | `230`, `220`, `110` | no | `1~` | `50 Гц`, `60 Гц` | mixed alternative compound |
| `57` | `380 В` | 67 | `1941`, `1942`, `1943` | `380` | no | none | none | formatting-only single |
| `57` | `220 В` | 50 | `1935`, `1936`, `1937` | `220` | no | none | none | formatting-only single |
| `79` | `380` | 14 | `8195`, `8196`, `8198` | `380` | no | none | none | exact canonical raw format in unapproved candidate |
| `79` | `220` | 10 | `8197`, `8211`, `8221` | `220` | no | none | none | exact canonical raw format in unapproved candidate |
| `79` | `230` | 10 | `8245`, `8246`, `8247` | `230` | no | none | none | alternate nominal |
| `79` | `220-230` | 7 | `8222`, `8223`, `8224` | `220`, `230` | `220-230` | none | none | range |
| `79` | `220–230` | 7 | `8213`, `8214`, `8215` | `220`, `230` | `220-230` | none | none | range |
| `79` | `220–240` | 5 | `8282`, `8283`, `8285` | `220`, `240` | `220-240` | none | none | range |
| `79` | `380–400` | 5 | `8220`, `8275`, `8287` | `380`, `400` | `380-400` | none | none | range |
| `79` | `220-230 В (однофазный) / 380-400 В (трехфазный)` | 4 | `8226`, `8227`, `8231` | `220`, `230`, `380`, `400` | `220-230`, `380-400` | single-phase and three-phase text | none | mixed alternative compound |
| `79` | `220-240` | 4 | `8258`, `8259`, `8260` | `220`, `240` | `220-240` | none | none | range |
| `79` | `230 В (1 фаза, 50 Гц)` | 4 | `8239`, `8240`, `8241` | `230` | no | `1 фаза` | `50 Гц` | alternate nominal with phase/frequency |
| `79` | `380-400` | 3 | `8225`, `8228`, `8268` | `380`, `400` | `380-400` | none | none | range |
| `79` | `380–400 В / 50 Гц (трёхфазный)` | 3 | `8203`, `8204`, `8205` | `380`, `400` | `380-400` | three-phase text | `50 Гц` | range with phase/frequency |
| `79` | `400` | 3 | `8200`, `8202`, `8276` | `400` | no | none | none | alternate nominal |
| `79` | `1×230 В, 50 Гц` | 2 | `8236`, `8237` | `230` | no | `1×` | `50 Гц` | alternate nominal with phase/frequency |
| `79` | empty | 1 | `8243` | none | no | none | none | invalid blank |
| `79` | `220–230 В (однофазный, 50 Гц)` | 1 | `8212` | `220`, `230` | `220-230` | single-phase text | `50 Гц` | range with phase/frequency |
| `79` | `220–240 В (однофазный) / 50 Гц` | 1 | `8281` | `220`, `240` | `220-240` | single-phase text | `50 Гц` | range with phase/frequency |
| `79` | `230 В` | 1 | `8242` | `230` | no | none | none | alternate nominal |
| `79` | `380–400 В (трёхфазный) / 220–230 В (однофазный — версия 4SRm)` | 1 | `8219` | `380`, `400`, `220`, `230` | `380-400`, `220-230` | three-phase and single-phase text | none | mixed alternative compound |
| `79` | `400 В (3 фазы, 50 Гц); также доступна однофазная версия 230 В` | 1 | `8277` | `400`, `230` | no | `3 фазы` plus single-phase version text | `50 Гц` | mixed alternative compound |
| `79` | `400 В (трёхфазный) / 220–240 В` | 1 | `8218` | `400`, `220`, `240` | `220-240` plus `400` | three-phase text | none | mixed alternative compound |
| `99` | `210..240` | 3 | `8208`, `8209`, `8210` | `210`, `240` | `210-240` | none | none | range |
| `99` | `380..420` | 2 | `8206`, `8207` | `380`, `420` | `380-420` | none | none | range |
| `118` | `220` | 1 | `7316` | `220` | no | none | none | exact canonical raw format in unapproved candidate |
| `170` | `1 × 200–240 В (однофазное)` | 5 | `3357`, `3358`, `3359` | `200`, `240` | `200-240` | `1 ×`, single-phase text | none | compound range |
| `170` | `1 × 220–240 В (однофазное)` | 1 | `3360` | `220`, `240` | `220-240` | `1 ×`, single-phase text | none | compound range |
| `170` | `1 × 230 В` | 1 | `7933` | `230` | no | `1 ×` | none | alternate nominal with phase |

## 4. Analytical classes

Classes are evidence groups for review. They are not implementation approval.

| class | groups | rows | notes |
| ----- | -----: | ---: | ----- |
| A. Exact canonical | 5 | 123 | raw after trim equals `220` or `380`; only `attribute_id = 15` exact rows are `unchanged` |
| B. Formatting-only normalization | 4 | 418 | `220V`, `380V`, `220 В`, `380 В`; mapping is approved |
| C. Alternate nominal simple | 3 | 14 | `230`, `230 В`, `400`; mapping to enum is approved |
| D. Range values | 16 | 55 | includes simple ranges, phase/frequency ranges and compound ranges |
| E. Phase/frequency descriptions | 13 | 26 | phase/frequency must not be discarded silently |
| F. Mixed alternative values | 5 | 8 | contain multiple voltage classes or modes; policy result is `review_required`, `canonical_value: null` |
| G. Outside enum evidence | 19 | 81 | includes `110`, `200`, `210`, `230`, `240`, `400`, `420`, ranges containing them |
| H. Invalid/unsupported | 1 | 1 | blank raw value |

Classes overlap when one value is both a range and a phase/frequency description or mixed alternative.

## 5. Class A — Exact canonical

Only values where `trim(raw_value)` is exactly `220` or `380`.

| source_attribute_id | raw_value | rows | status | canonical value | decision |
| ------------------- | --------- | ---: | --------------- | ------------------------ | -------------- |
| `15` | `220` | 49 | `unchanged` | `220` | no extra mapping decision |
| `15` | `380` | 49 | `unchanged` | `380` | no extra mapping decision |
| `79` | `220` | 10 | `normalized` | `220` | alias approved |
| `79` | `380` | 14 | `normalized` | `380` | alias approved |
| `118` | `220` | 1 | `normalized` | `220` | alias approved |

`unchanged` must be used only for:

`source_attribute_id = 15` and `trim(raw_value) in (220, 380)`.

Finding raw `220` or `380` in an alias is not enough for `unchanged`; aliases are `normalized`.

## 6. Class B — Formatting-only normalization

Values where the electrical nominal is already exactly `220` or `380`, and only spelling/unit suffix changes.

| source_attribute_id | raw_value | rows | canonical value | confidence | rule |
| ------------------- | --------- | ---: | ------------------------ | ---------- | -------------- |
| `15` | `220V` | 157 | `220` | high | formatting cleanup approved |
| `15` | `380V` | 144 | `380` | high | formatting cleanup approved |
| `57` | `220 В` | 50 | `220` | high | approved duplicate `57 -> 15` |
| `57` | `380 В` | 67 | `380` | high | approved duplicate `57 -> 15` |

Status:

* `normalized`;
* not `unchanged`.

## 7. Class C — Alternate nominal

Alternate nominal values are outside canonical enum as raw values, but approved policy maps them into canonical classes.

| raw group | rows | products | phase | frequency | approved electrical class | note | rule |
| --------- | ---: | -------- | ----- | --------- | ------------------------- | ---- | -------------- |
| `79 / 230` | 10 | `8245`, `8246`, `8247` | none | none | `220` | `230` raw value is collapsed to class `220` | approved |
| `79 / 230 В` | 1 | `8242` | none | none | `220` | unit suffix removed and `230` collapsed to `220` | approved |
| `79 / 400` | 3 | `8200`, `8202`, `8276` | none | none | `380` | `400` raw value is collapsed to class `380` | approved |
| policy example `400 В` | 0 | not found as standalone DB fact | none | none | `380` | pattern covered if found later | approved |

Related phase/frequency alternates are listed in Class E.

## 8. Class D — Ranges

Ranges must not remain canonical output.

| raw group | min | max | rows | product samples | source attributes | phase/frequency context | class | canonical mapping | note |
| --------- | --: | --: | ---: | --------------- | ----------------- | ----------------------- | ---------------- | ---------------------- | ------------------------ |
| `79 / 220-230` | 220 | 230 | 7 | `8222`, `8223`, `8224` | `79` | none | `220` class | `220` | approved collapse |
| `79 / 220–230` | 220 | 230 | 7 | `8213`, `8214`, `8215` | `79` | none | `220` class | `220` | approved collapse |
| `79 / 220–240` | 220 | 240 | 5 | `8282`, `8283`, `8285` | `79` | none | `220` class | `220` | approved collapse |
| `79 / 220-240` | 220 | 240 | 4 | `8258`, `8259`, `8260` | `79` | none | `220` class | `220` | approved collapse |
| `79 / 380–400` | 380 | 400 | 5 | `8220`, `8275`, `8287` | `79` | none | `380` class | `380` | approved collapse |
| `79 / 380-400` | 380 | 400 | 3 | `8225`, `8228`, `8268` | `79` | none | `380` class | `380` | approved collapse |
| `79 / 380–400 В / 50 Гц (трёхфазный)` | 380 | 400 | 3 | `8203`, `8204`, `8205` | `79` | three-phase, `50 Гц` | `380` class | `380` | frequency/phase kept in diagnostics |
| `99 / 210..240` | 210 | 240 | 3 | `8208`, `8209`, `8210` | `99` | none | `220` class | `220` | approved collapse |
| `99 / 380..420` | 380 | 420 | 2 | `8206`, `8207` | `99` | none | `380` class | `380` | approved collapse |
| `170 / 1 × 200–240 В (однофазное)` | 200 | 240 | 5 | `3357`, `3358`, `3359` | `170` | single-phase | `220` class | `220` | phase kept in diagnostics |
| `170 / 1 × 220–240 В (однофазное)` | 220 | 240 | 1 | `3360` | `170` | single-phase | `220` class | `220` | phase kept in diagnostics |
| `79 / 220–230 В (однофазный, 50 Гц)` | 220 | 230 | 1 | `8212` | `79` | single-phase, `50 Гц` | `220` class | `220` | phase/frequency kept in diagnostics |
| `79 / 220–240 В (однофазный) / 50 Гц` | 220 | 240 | 1 | `8281` | `79` | single-phase, `50 Гц` | `220` class | `220` | phase/frequency kept in diagnostics |
| `79 / 220-230 В (однофазный) / 380-400 В (трехфазный)` | 220 | 400 | 4 | `8226`, `8227`, `8231` | `79` | single-phase and three-phase alternatives | both classes | no mapping; `null` | mixed alternative values |
| `79 / 380–400 В (трёхфазный) / 220–230 В (однофазный — версия 4SRm)` | 220 | 400 | 1 | `8219` | `79` | single-phase and three-phase alternatives | both classes | no mapping; `null` | mixed alternative values |
| `79 / 400 В (трёхфазный) / 220–240 В` | 220 | 400 | 1 | `8218` | `79` | three-phase plus alternate range | both classes | no mapping; `null` | mixed alternative values |

## 9. Class E — Phase/frequency descriptions

Phase and frequency are evidence. They must not be removed silently.

| raw group | rows | contains | voltage class interpretation | status |
| --------- | ---: | -------- | ---------------------------- | --------------- |
| `15 / 1~230 В, 50 Гц / 1~220 В, 60 Гц / 1~110 В, 60 Гц* (номинальное)` | 1 | `1~`, `50 Гц`, `60 Гц`, `230`, `220`, `110` | multiple modes, includes outside enum | `review_required`, `canonical_value: null` |
| `79 / 1×230 В, 50 Гц` | 2 | `1×`, `50 Гц`, `230` | `220` class | `normalized` |
| `79 / 230 В (1 фаза, 50 Гц)` | 4 | `1 фаза`, `50 Гц`, `230` | `220` class | `normalized` |
| `79 / 220–230 В (однофазный, 50 Гц)` | 1 | single-phase, `50 Гц`, range | `220` class | `normalized` |
| `79 / 220–240 В (однофазный) / 50 Гц` | 1 | single-phase, `50 Гц`, range | `220` class | `normalized` |
| `79 / 380–400 В / 50 Гц (трёхфазный)` | 3 | three-phase, `50 Гц`, range | `380` class | `normalized` |
| `79 / 220-230 В (однофазный) / 380-400 В (трехфазный)` | 4 | single-phase and three-phase alternatives | both classes | `review_required`, `canonical_value: null` |
| `79 / 380–400 В (трёхфазный) / 220–230 В (однофазный — версия 4SRm)` | 1 | single-phase and three-phase alternatives | both classes | `review_required`, `canonical_value: null` |
| `79 / 400 В (3 фазы, 50 Гц); также доступна однофазная версия 230 В` | 1 | `3 фазы`, `50 Гц`, single-phase version text | both classes | `review_required`, `canonical_value: null` |
| `79 / 400 В (трёхфазный) / 220–240 В` | 1 | three-phase plus alternate range | both classes | `review_required`, `canonical_value: null` |
| `170 / 1 × 200–240 В (однофазное)` | 5 | `1 ×`, single-phase, range | `220` class | `normalized` |
| `170 / 1 × 220–240 В (однофазное)` | 1 | `1 ×`, single-phase, range | `220` class | `normalized` |
| `170 / 1 × 230 В` | 1 | `1 ×`, `230` | `220` class | `normalized` |

## 10. Class F — Mixed alternative values

Mixed alternative values are candidates only for:

```text
review_required
canonical_value: null
```

Full list:

| raw group | rows | product IDs | reason |
| --------- | ---: | ----------- | ------ |
| `15 / 1~230 В, 50 Гц / 1~220 В, 60 Гц / 1~110 В, 60 Гц* (номинальное)` | 1 | `8192` | multiple nominal modes, includes `110` outside enum |
| `79 / 220-230 В (однофазный) / 380-400 В (трехфазный)` | 4 | `8226`, `8227`, `8231`, `8233` | both `220` and `380` class alternatives |
| `79 / 380–400 В (трёхфазный) / 220–230 В (однофазный — версия 4SRm)` | 1 | `8219` | both `220` and `380` class alternatives |
| `79 / 400 В (3 фазы, 50 Гц); также доступна однофазная версия 230 В` | 1 | `8277` | both `400` and `230` alternatives |
| `79 / 400 В (трёхфазный) / 220–240 В` | 1 | `8218` | `400` plus `220-240` alternative |

## 11. Class G — Outside enum

Values outside approved enum are not automatically converted.

Full outside-enum evidence:

| value evidence | rows | context | why not automatic |
| -------------- | ---: | ------- | ----------------- |
| `110` | 1 | inside `15 / 1~230... / 1~110...` | outside enum and mixed with other modes |
| `200` | 5 | `170 / 1 × 200–240 В` | range endpoint outside enum |
| `210` | 3 | `99 / 210..240` | range endpoint outside enum |
| `230` | 32 | exact `230`, `230 В`, phase/frequency values, ranges and mixed alternatives | common nominal but not approved enum |
| `240` | 22 | ranges `220-240`, `210..240`, `200-240` | range endpoint outside enum |
| `400` | 22 | exact `400`, ranges and mixed alternatives | common nominal but not approved enum |
| `420` | 2 | `99 / 380..420` | range endpoint outside enum |

Rows can overlap because one raw value may contain several outside-enum numbers.

## 12. Class H — Invalid/unsupported

| source_attribute_id | raw_value | rows | status | reason |
| ------------------- | --------- | ---: | --------------- | ------ |
| `79` | empty | 1 | `invalid` | blank value, no recognizable voltage |

Unsupported values count in current package: `0`.

## 13. Decision matrix

| raw pattern | factual meaning | canonical value | status | confidence | information loss | human decision |
| ----------- | --------------- | ------------------------ | --------------- | ---------- | ---------------- | ----------------------- |
| `220` in attribute `15` | already canonical raw value | `220` | `unchanged` | high | no | no |
| `380` in attribute `15` | already canonical raw value | `380` | `unchanged` | high | no | no |
| `220` in unapproved candidate | exact canonical value in non-approved candidate ID | `220` only if ID approved | unresolved | medium | attribute provenance changes | approve alias ID |
| `380` in unapproved candidate | exact canonical value in non-approved candidate ID | `380` only if ID approved | unresolved | medium | attribute provenance changes | approve alias ID |
| `220V` | `220` with latin unit suffix | `220` | `normalized` | high | unit suffix removed | yes |
| `380V` | `380` with latin unit suffix | `380` | `normalized` | high | unit suffix removed | yes |
| `220 В` | `220` with Cyrillic unit suffix | `220` | `normalized` | high | unit suffix removed | yes |
| `380 В` | `380` with Cyrillic unit suffix | `380` | `normalized` | high | unit suffix removed | yes |
| `230` | alternate nominal | `220` | `normalized` | medium | nominal `230` collapsed into `220` | approved |
| `400` | alternate nominal | `380` | `normalized` | medium | nominal `400` collapsed into `380` | approved |
| `1 × 230 В` | single-phase alternate nominal | `220` | `normalized` | medium | phase marker kept only in diagnostics | approved |
| `3 × 400 В` | policy example, not found as exact raw DB fact | `380` | `normalized` | medium | phase marker kept only in diagnostics | approved |
| `200240 В` | policy example for compact range, not found as exact raw DB fact | `220` | `normalized` | medium | range kept only in diagnostics | approved |
| `210240 В` | policy example for compact range; DB fact exists as `210..240` | `220` | `normalized` | medium | range kept only in diagnostics | approved |
| `220230 В` | policy example for compact range; DB facts exist as `220-230`, `220–230` | `220` | `normalized` | medium | range kept only in diagnostics | approved |
| `220240 В` | policy example for compact range; DB facts exist as `220-240`, `220–240` | `220` | `normalized` | medium | range kept only in diagnostics | approved |
| `380400 В` | policy example for compact range; DB facts exist as `380-400`, `380–400` | `380` | `normalized` | medium | range kept only in diagnostics | approved |
| `380420 В` | policy example for compact range; DB fact exists as `380..420` | `380` | `normalized` | medium | range kept only in diagnostics | approved |
| `400 В / 220240 В` | policy example; DB fact exists as `400 В ... / 220–240 В` | `null` | `review_required` | high for manual review | no automatic class can preserve both alternatives | yes |
| `220230 В / 380400 В` | policy example; DB fact exists as `220-230 В ... / 380-400 В` | `null` | `review_required` | high for manual review | both classes would be lost if collapsed | yes |
| `110 В` | outside enum | `null` | `review_required` or `unsupported` | high for not normalizing | would invent unsupported class | yes |
| empty | blank value | `null` | `invalid` | high | none | no mapping; data decision needed |

## 14. Status rules

Rules:

* `unchanged`: only `source_attribute_id = 15` and `trim(raw_value)` is exactly `220` or `380`;
* `normalized`: only approved alias/candidate IDs and approved formatting-only or approved mapping rules;
* `review_required`: mixed alternatives and outside-policy values;
* `invalid`: blank or structurally broken values;
* `unsupported`: text without recognizable voltage, if found later.

Do not mark `220V` or `380V` as `unchanged`.

Do not use presence in attribute `15` as sufficient condition for `unchanged`.

## 15. Approved human decisions

1. Formatting-only forms `220V`, `220 В`, `380V`, `380 В` normalize to `220` or `380`.
   * Current facts: `418` rows.
   * Examples: `15 / 220V` rows `157`; `15 / 380V` rows `144`; `57 / 220 В` rows `50`; `57 / 380 В` rows `67`.

2. `230` is equivalent to catalog class `220`.
   * Current facts: simple `230` rows `11`; with phase/frequency `7`; inside ranges/mixed additional evidence exists.
   * Examples: `79 / 230` rows `10`; `79 / 230 В` row `1`; `79 / 1×230 В, 50 Гц` rows `2`; `170 / 1 × 230 В` row `1`.

3. `400` is equivalent to catalog class `380`.
   * Current facts: simple `400` rows `3`; `400` also appears in ranges and mixed alternatives.
   * Examples: `79 / 400` rows `3`; `79 / 400 В (3 фазы, 50 Гц); также доступна однофазная версия 230 В` row `1`.

4. Ranges `200240`, `210240`, `220230`, `220240` normalize to `220`.
   * Current facts: `210..240` rows `3`; `220-230` rows `7`; `220–230` rows `7`; `220-240` rows `4`; `220–240` rows `5`; `1 × 200–240 В` rows `5`; `1 × 220–240 В` row `1`.
   * Compact forms are policy examples unless exact compact raw value appears later.

5. Ranges `380400`, `380420` normalize to `380`.
   * Current facts: `380-400` rows `3`; `380–400` rows `5`; `380–400 В / 50 Гц (трёхфазный)` rows `3`; `380..420` rows `2`.
   * Compact forms are policy examples unless exact compact raw value appears later.

6. Phase can be used as additional class signal.
   * Current facts: phase/frequency descriptions appear in `26` rows.
   * Examples: `1×230 В, 50 Гц`; `230 В (1 фаза, 50 Гц)`; `380–400 В / 50 Гц (трёхфазный)`.
   * Phase contradiction always requires manual review with warning `phase_voltage_class_conflict` and `canonical_value: null`.
   * Examples: `3 230 В`, `230 В (трёхфазный)`, `1 400 В`, `400 В (однофазный)`.

7. Frequency can be omitted from canonical output after a single class is determined.
   * Current facts: `50 Гц` / `60 Гц` appears in several values.
   * Examples: `1~230 В, 50 Гц / 1~220 В, 60 Гц / 1~110 В, 60 Гц*`; `380–400 В / 50 Гц`.

8. Mixed alternative values remain manual review.
   * Current facts: `5` groups, `8` rows.
   * Examples: `220-230 В / 380-400 В`; `400 В ... 230 В`; `400 В / 220–240 В`.

9. `110` and other outside-policy values are not normalized automatically.
   * Current facts: `110` appears in `1` mixed row; outside-enum evidence also includes `200`, `210`, `230`, `240`, `400`, `420`.
   * Policy result: `review_required` or `unsupported`, `canonical_value: null`.

10. IDs `79`, `99`, `118`, `170` are approved aliases.
    * Current facts: `79` rows `88`; `99` rows `5`; `118` row `1`; `170` rows `7`.
    * Legacy approval currently covers only `57 -> 15`.

## 16. Boundary

This approved policy does not permit:

* mixed alternative auto-normalization;
* outside-policy auto-normalization;
* SQL/apply;
* `--confirm-apply`;
* product/category changes;
* production/cache actions;
* cache rebuild.
