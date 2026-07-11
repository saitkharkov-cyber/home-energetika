# Voltage Exception Review

Review outcome: approved.

Decision date: 10-07-2026.

Decision source: explicit user approval through conservative evidence gate.

Not an apply authorization.

Analysis date: 10-07-2026.

Package: `framework-standardization/runtime/reports/submersible_pumps_voltage/20260710190844_6a51433c8f43`.

Target: `Напряжение`.

Scope root category: `11900213`.

Reviewed products: `8192`, `8218`, `8219`, `8226`, `8227`, `8231`, `8233`, `8277`, `8243`.

## Approved Outcome

Approved manual resolutions:

| product_id | approved canonical value | evidence level |
| ---------: | -----------------------: | -------------- |
| 8231 | 220 | high |
| 8233 | 220 | high |
| 8277 | 380 | high |

Unresolved products:

```text
8192
8218
8219
8226
8227
```

Invalid product:

```text
8243
```

Products `8226` and `8227` remain unresolved because their evidence level is only `medium`.

This outcome does not authorize SQL preview, apply-plan, SQL/apply, product/category changes, production/cache actions, or cache rebuild.

## Evidence Sources

- Review package `summary.md`, `manifest.json`, `proposals.json`, `inventory.json`.
- Read-only snapshot DB through project runtime `live_db_readonly`.
- Repository documentation: `START_HERE.md`, `DECISIONS.md`, `VOLTAGE_NORMALIZATION_POLICY.md`.
- Legacy/import search inside the repository for exact SKU/model evidence.

No internet evidence was used.

No write SQL, SQL/apply, pipeline rerun, product/category edit, cache operation, or generated review package was performed.

## Package Exception Summary

Review-required proposals:

| product_id | source_attribute_id | raw voltage value | reason |
| ---------: | ------------------: | ----------------- | ------ |
| 8192 | 15 | `1~230 В, 50 Гц / 1~220 В, 60 Гц / 1~110 В, 60 Гц* (номинальное)` | `voltage_outside_allowed_classes` |
| 8218 | 79 | `400 В (трёхфазный) / 220–240 В` | `mixed_voltage_classes` |
| 8219 | 79 | `380–400 В (трёхфазный) / 220–230 В (однофазный — версия 4SRm)` | `mixed_voltage_classes` |
| 8226 | 79 | `220-230 В (однофазный) / 380-400 В (трехфазный) ` | `mixed_voltage_classes` |
| 8227 | 79 | `220-230 В (однофазный) / 380-400 В (трехфазный) ` | `mixed_voltage_classes` |
| 8231 | 79 | `220-230 В (однофазный) / 380-400 В (трехфазный)` | `mixed_voltage_classes` |
| 8233 | 79 | `220-230 В (однофазный) / 380-400 В (трехфазный)` | `mixed_voltage_classes` |
| 8277 | 79 | `400 В (3 фазы, 50 Гц); также доступна однофазная версия 230 В` | `mixed_voltage_classes` |

Invalid proposal:

| product_id | source_attribute_id | raw voltage value | reason |
| ---------: | ------------------: | ----------------- | ------ |
| 8243 | 79 | empty | `empty_value` |

## Product Cards

### Product 8192

| Field | Evidence |
| ----- | -------- |
| product_id | `8192` |
| active | yes, `status = 1` |
| product name | `Насос скважинный Pedrollo DAVIS` |
| model / SKU | model `8192`, SKU `484DAV00A1` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900308 Скважинные насосы > Насосы Pedrollo`; `11900556 Скважинные насосы > Насосы Pedrollo > Скважинные DAVIS` |
| source attribute | `15`, `Напряжение`, group `Параметры насоса` |
| raw voltage value | `1~230 В, 50 Гц / 1~220 В, 60 Гц / 1~110 В, 60 Гц* (номинальное)` |
| all voltage-like attributes | only attribute `15` found for voltage in the queried evidence |
| phase-related attributes | phase markers are embedded in raw value: `1~` |
| frequency-related attributes | embedded in raw value: `50 Гц`, `60 Гц` |
| power/current context | `Мощность двигателя`: `0,75 кВт (1 л.с.)` |
| description evidence | explicit snippets mention `Исполнение на другие напряжения` and `Частота 60 Гц (отражено в версиях)`; description also says data are for `50 Гц`, with possible `60 Гц` correction |
| related source evidence | no exact legacy/import record for SKU `484DAV00A1` found inside repository |

Model identity review: the card describes DAVIS voltage/frequency alternatives and does not prove which voltage/frequency execution is sold by this exact SKU. The `110 В` value is explicitly present beside allowed 220-class alternatives, so choosing `220` would require a human decision.

Conclusion: proposed canonical `null`; confidence `insufficient`; proposed action `keep_review_required`.

### Product 8218

| Field | Evidence |
| ----- | -------- |
| product_id | `8218` |
| active | yes, `status = 1` |
| product name | `Скважинный насос 4SR  1,5/ 7  F -P  0,37 кВт Pedrollo` |
| model / SKU | model `8218`, SKU `49480107WNA` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900262 Скважинные насосы > Насосы Pedrollo > Скважинные 4-х дюймовые насосы (Диаметр 100мм) > Pedrollo 4SR 1.5`; `11900308 Скважинные насосы > Насосы Pedrollo` |
| source attribute | `79`, `Напряжение сети, В`, group `Насосы Pedrollo` |
| raw voltage value | `400 В (трёхфазный) / 220–240 В` |
| all voltage-like attributes | only attribute `79` found for voltage in the queried evidence |
| phase-related attributes | embedded in raw/description: `трёхфазный`, `однофазный` |
| frequency-related attributes | no separate frequency attribute found |
| power/current context | product name says `0,37 кВт`; description says current for both `400 В` and `230 В` variants |
| description evidence | explicit snippet: `Напряжение питания: 400 В (трёхфазный) / 220–240 В (однофазный — версия 4SRm)` |
| related source evidence | no exact legacy/import record for SKU `49480107WNA` found inside repository |

Model identity review: the card name does not contain `4SRm`, while the description presents both three-phase and single-phase variants. The exact SKU is present, but the internal evidence does not independently map this SKU to one voltage class.

Conclusion: proposed canonical `null`; confidence `insufficient`; proposed action `keep_review_required`.

### Product 8219

| Field | Evidence |
| ----- | -------- |
| product_id | `8219` |
| active | yes, `status = 1` |
| product name | `Скважинный насос 4SR 1,5/11  F -P  0,55 кВт  Pedrollo` |
| model / SKU | model `8219`, SKU `49480111WLA1` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900262 Скважинные насосы > Насосы Pedrollo > Скважинные 4-х дюймовые насосы (Диаметр 100мм) > Pedrollo 4SR 1.5`; `11900308 Скважинные насосы > Насосы Pedrollo` |
| source attribute | `79`, `Напряжение сети, В`, group `Насосы Pedrollo` |
| raw voltage value | `380–400 В (трёхфазный) / 220–230 В (однофазный — версия 4SRm)` |
| all voltage-like attributes | only attribute `79` found for voltage in the queried evidence |
| phase-related attributes | embedded in raw/description: `трёхфазный`, `однофазный` |
| frequency-related attributes | no separate frequency attribute found |
| power/current context | product name says `0,55 кВт`; description says current for both `400 В` and `230 В` variants |
| description evidence | explicit snippet: `Напряжение питания: 380–400 В (трёхфазный) / 230 В (однофазный — версия 4SRm)` |
| related source evidence | no exact legacy/import record for SKU `49480111WLA1` found inside repository |

Model identity review: the card name does not contain `4SRm`. The voltage evidence describes two variants and does not prove the exact SKU is one of them.

Conclusion: proposed canonical `null`; confidence `insufficient`; proposed action `keep_review_required`.

### Product 8226

| Field | Evidence |
| ----- | -------- |
| product_id | `8226` |
| active | yes, `status = 1` |
| product name | `Скважинный насос  4SR  1,5m/22  F -P  1,1 кВт Pedrollo` |
| model / SKU | model `8226`, SKU `49480122WLA1` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900262 Скважинные насосы > Насосы Pedrollo > Скважинные 4-х дюймовые насосы (Диаметр 100мм) > Pedrollo 4SR 1.5`; `11900308 Скважинные насосы > Насосы Pedrollo` |
| source attribute | `79`, `Напряжение сети, В`, group `Насосы Pedrollo` |
| raw voltage value | `220-230 В (однофазный) / 380-400 В (трехфазный) ` |
| all voltage-like attributes | only attribute `79` found for voltage in the queried evidence |
| phase-related attributes | raw value contains both `однофазный` and `трехфазный`; product name contains `1,5m/22` |
| frequency-related attributes | no separate frequency attribute found |
| power/current context | product name says `1,1 кВт` |
| description evidence | no short voltage/phase snippet was found by the keyword scan |
| related source evidence | no exact legacy/import record for SKU `49480122WLA1` found inside repository |

Model identity review: product name contains `m`, which is consistent with one-phase `4SRm` naming seen elsewhere in the same catalog evidence, but this step does not establish a global suffix rule from one signal. The raw value still lists both one-phase and three-phase variants.

Conclusion: proposed canonical `null`; confidence `medium`; proposed action `keep_review_required`.

Final outcome: unresolved. Medium evidence is not sufficient for approved manual resolution.

### Product 8227

| Field | Evidence |
| ----- | -------- |
| product_id | `8227` |
| active | yes, `status = 1` |
| product name | `Скважинный насос 4SR  1,5m/30  F -P  1,5 кВт Pedrollo` |
| model / SKU | model `8227`, SKU `49480130WLA` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900262 Скважинные насосы > Насосы Pedrollo > Скважинные 4-х дюймовые насосы (Диаметр 100мм) > Pedrollo 4SR 1.5`; `11900308 Скважинные насосы > Насосы Pedrollo` |
| source attribute | `79`, `Напряжение сети, В`, group `Насосы Pedrollo` |
| raw voltage value | `220-230 В (однофазный) / 380-400 В (трехфазный) ` |
| all voltage-like attributes | only attribute `79` found for voltage in the queried evidence |
| phase-related attributes | raw value contains both `однофазный` and `трехфазный`; product name contains `1,5m/30` |
| frequency-related attributes | no separate frequency attribute found |
| power/current context | product name says `1,5 кВт` |
| description evidence | no short voltage/phase snippet was found by the keyword scan |
| related source evidence | no exact legacy/import record for SKU `49480130WLA` found inside repository |

Model identity review: product name contains `m`, but the raw attribute still describes both variants. Independent confirmation of this exact SKU as the one-phase version was not found.

Conclusion: proposed canonical `null`; confidence `medium`; proposed action `keep_review_required`.

Final outcome: unresolved. Medium evidence is not sufficient for approved manual resolution.

### Product 8231

| Field | Evidence |
| ----- | -------- |
| product_id | `8231` |
| active | yes, `status = 1` |
| product name | `Скважинный насос  4SR  1m/22  F -P  0,75 кВт Pedrollo` |
| model / SKU | model `8231`, SKU `49480022WLA` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900261 Скважинные насосы > Насосы Pedrollo > Скважинные 4-х дюймовые насосы (Диаметр 100мм) > Pedrollo 4SR 1`; `11900308 Скважинные насосы > Насосы Pedrollo` |
| source attribute | `79`, `Напряжение сети, В`, group `Насосы Pedrollo` |
| raw voltage value | `220-230 В (однофазный) / 380-400 В (трехфазный)` |
| all voltage-like attributes | only attribute `79` found for voltage in the queried evidence |
| phase-related attributes | raw value contains both variants; product name contains `1m/22` |
| frequency-related attributes | no separate frequency attribute found |
| power/current context | product name says `0,75 кВт` |
| description evidence | explicit snippet says `в однофазном исполнении 220В` |
| related source evidence | no exact legacy/import record for SKU `49480022WLA` found inside repository |

Model identity review: the exact card has both model-name evidence (`m`) and description evidence for one-phase `220В`. The raw attribute still contains both variants, so this is suitable for human-approved manual resolution, not for changing normalizer policy.

Conclusion: proposed canonical `220`; confidence `high`; proposed action `approve_manual_resolution`.

Final outcome: approved manual resolution `8231 -> 220`.

### Product 8233

| Field | Evidence |
| ----- | -------- |
| product_id | `8233` |
| active | yes, `status = 1` |
| product name | `Скважинный насос  4SR  2/ 9  F -P  0,55 кВт Pedrollo` |
| model / SKU | model `8233`, SKU `49480209WLA` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900263 Скважинные насосы > Насосы Pedrollo > Скважинные 4-х дюймовые насосы (Диаметр 100мм) > Pedrollo 4SR 2`; `11900308 Скважинные насосы > Насосы Pedrollo` |
| source attribute | `79`, `Напряжение сети, В`, group `Насосы Pedrollo` |
| raw voltage value | `220-230 В (однофазный) / 380-400 В (трехфазный)` |
| all voltage-like attributes | only attribute `79` found for voltage in the queried evidence |
| phase-related attributes | raw value contains both variants; description references no three-phase requirement |
| frequency-related attributes | no separate frequency attribute found |
| power/current context | product name says `0,55 кВт` |
| description evidence | snippets say `в однофазном исполнении 220В` and `не требует трехфазного напряжения` |
| related source evidence | no exact legacy/import record for SKU `49480209WLA` found inside repository |

Model identity review: although the product name does not include `m`, the product description explicitly describes the card as one-phase `220В` and says it does not require three-phase voltage.

Conclusion: proposed canonical `220`; confidence `high`; proposed action `approve_manual_resolution`.

Final outcome: approved manual resolution `8233 -> 220`.

### Product 8277

| Field | Evidence |
| ----- | -------- |
| product_id | `8277` |
| active | yes, `status = 1` |
| product name | `Скважинный насос 4SR 10/10  P  2,2 кВт кВт Pedrollo` |
| model / SKU | model `8277`, SKU `4941010WLA` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900266 Скважинные насосы > Насосы Pedrollo > Скважинные 4-х дюймовые насосы (Диаметр 100мм) > Pedrollo 4SR 8`; `11900308 Скважинные насосы > Насосы Pedrollo` |
| source attribute | `79`, `Напряжение сети, В`, group `Насосы Pedrollo` |
| raw voltage value | `400 В (3 фазы, 50 Гц); также доступна однофазная версия 230 В` |
| all voltage-like attributes | only attribute `79` found for voltage in the queried evidence |
| phase-related attributes | raw value says `3 фазы`; description says `P — трехфазное исполнение` |
| frequency-related attributes | raw value and description mention `50 Гц` |
| power/current context | product name says `2,2 кВт`; description includes three-phase supply |
| description evidence | explicit snippet: `Напряжение питания (P — трехфазное исполнение): 380–415 В (3 фазы, 50 Гц)` |
| related source evidence | no exact legacy/import record for SKU `4941010WLA` found inside repository |

Model identity review: the exact name includes `P`; both raw value and description identify the listed card as three-phase, while mentioning that a one-phase version is also available. This supports a human-approved manual resolution to `380`, without changing the generic mixed-value normalizer behavior.

Conclusion: proposed canonical `380`; confidence `high`; proposed action `approve_manual_resolution`.

Final outcome: approved manual resolution `8277 -> 380`.

### Product 8243

| Field | Evidence |
| ----- | -------- |
| product_id | `8243` |
| active | yes, `status = 1` |
| product name | `Скважинный насос  4SR  2m/33  F -P  2,2 кВт Pedrollo` |
| model / SKU | model `8243`, SKU `49480233WLA1` |
| manufacturer | `Pedrollo (Италия)` |
| categories | `11900213 Скважинные насосы`; `11900263 Скважинные насосы > Насосы Pedrollo > Скважинные 4-х дюймовые насосы (Диаметр 100мм) > Pedrollo 4SR 2`; `11900308 Скважинные насосы > Насосы Pedrollo` |
| source attribute | `79`, `Напряжение сети, В`, group `Насосы Pedrollo` |
| raw voltage value | empty |
| all voltage-like attributes | attribute `79` exists but is empty; no other voltage attribute found in the queried evidence |
| phase-related attributes | no separate phase attribute found |
| frequency-related attributes | no separate frequency attribute found |
| power/current context | product name says `2,2 кВт` |
| description evidence | snippet mentions a neighboring/comparison pump requiring `трехфазного подключения 380В`, not this exact card's own voltage |
| related source evidence | no exact legacy/import record for SKU `49480233WLA1` found inside repository |

Model identity review: the product name contains `2m/33`, which is a useful hint, but the target voltage attribute is empty and the description evidence found for `380В` is comparative rather than proof for this exact product. The exact value cannot be filled automatically from the available evidence.

Conclusion: proposed canonical `null`; confidence `insufficient`; proposed action `insufficient_evidence`.

Final outcome: invalid remains unresolved and is not included in manual resolution plan.

## Group Review

Group `8226`, `8227`, `8231`, `8233` shares a similar mixed raw attribute pattern, but the evidence is not fully identical:

| product_id | model signal | description signal | proposed canonical | confidence |
| ---------: | ------------ | ------------------ | ------------------ | ---------- |
| 8226 | `1,5m/22` suggests one-phase family | no explicit voltage snippet found | `null` | medium |
| 8227 | `1,5m/30` suggests one-phase family | no explicit voltage snippet found | `null` | medium |
| 8231 | `1m/22` suggests one-phase family | explicit `однофазном исполнении 220В` | `220` | high |
| 8233 | no `m` in name | explicit `однофазном исполнении 220В` and no three-phase requirement | `220` | high |

Unified action for all four is not approved. Only high-confidence products `8231` and `8233` are approved for manual resolution to `220`; `8226` and `8227` remain unresolved.

## Decision Table

| product_id | current raw value | exact model | evidence summary | inferred product version | proposed canonical | confidence | proposed action | human decision required |
| ---------: | ----------------- | ----------- | ---------------- | ------------------------ | ------------------ | ---------- | --------------- | ----------------------- |
| 8192 | `1~230 В, 50 Гц / 1~220 В, 60 Гц / 1~110 В, 60 Гц* (номинальное)` | Pedrollo DAVIS, SKU `484DAV00A1` | raw value and description include multiple voltage/frequency executions including `110 В` | combined/unclear execution | `null` | insufficient | `keep_review_required` | yes |
| 8218 | `400 В (трёхфазный) / 220–240 В` | `4SR 1,5/7`, SKU `49480107WNA` | description lists both three-phase and one-phase `4SRm`; exact SKU-to-phase mapping not proven | combined/unclear execution | `null` | insufficient | `keep_review_required` | yes |
| 8219 | `380–400 В (трёхфазный) / 220–230 В (однофазный — версия 4SRm)` | `4SR 1,5/11`, SKU `49480111WLA1` | description lists both variants; name lacks `m` | combined/unclear execution | `null` | insufficient | `keep_review_required` | yes |
| 8226 | `220-230 В (однофазный) / 380-400 В (трехфазный)` | `4SR 1,5m/22`, SKU `49480122WLA1` | model name suggests `m` one-phase, but raw lists both variants | likely one-phase, not confirmed enough | `null` | medium | `keep_review_required` | yes |
| 8227 | `220-230 В (однофазный) / 380-400 В (трехфазный)` | `4SR 1,5m/30`, SKU `49480130WLA` | model name suggests `m` one-phase, but raw lists both variants | likely one-phase, not confirmed enough | `null` | medium | `keep_review_required` | yes |
| 8231 | `220-230 В (однофазный) / 380-400 В (трехфазный)` | `4SR 1m/22`, SKU `49480022WLA` | model name and description support one-phase `220В` | one-phase | `220` | high | `approve_manual_resolution` | yes |
| 8233 | `220-230 В (однофазный) / 380-400 В (трехфазный)` | `4SR 2/9`, SKU `49480209WLA` | description supports one-phase `220В` and says no three-phase is required | one-phase | `220` | high | `approve_manual_resolution` | yes |
| 8277 | `400 В (3 фазы, 50 Гц); также доступна однофазная версия 230 В` | `4SR 10/10 P`, SKU `4941010WLA` | raw and description identify `P` as three-phase `380–415 В`; one-phase version is only mentioned as available | three-phase | `380` | high | `approve_manual_resolution` | yes |
| 8243 | empty | `4SR 2m/33`, SKU `49480233WLA1` | voltage attribute empty; found `380В` snippet is comparative, not exact-card proof | unknown | `null` | insufficient | `insufficient_evidence` | yes |

## Outcome Counts

Approved `220`: 2 products (`8231`, `8233`).

Approved `380`: 1 product (`8277`).

Unresolved `null`: 5 products (`8192`, `8218`, `8219`, `8226`, `8227`).

Invalid: 1 product (`8243`).

## Human Decisions Required

Resolved by explicit user approval:

1. Products `8231` and `8233` may be manually resolved to `220`.
2. Product `8277` may be manually resolved to `380`.

Still requiring future human decision:

1. Products `8192`, `8218`, `8219`, `8226`, and `8227` remain unresolved.
2. Product `8243` remains invalid.
3. Future catalog card split or source-data correction for unresolved/invalid products is outside this outcome.

## Boundary

This review does not change the approved voltage normalizer policy. Mixed classes remain `review_required`; outside-policy values remain `review_required`; empty values remain `invalid`.

This document does not authorize SQL/apply, product edits, category edits, production writes, cache rebuild, or hardcoded product-specific normalizer exceptions.
