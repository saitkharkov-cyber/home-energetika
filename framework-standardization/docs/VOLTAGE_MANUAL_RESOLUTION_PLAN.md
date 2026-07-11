# Voltage Manual Resolution Plan

Documentation plan only.

Not SQL.

Not an apply authorization.

Decision date: 10-07-2026.

Decision source: explicit user approval through conservative evidence gate.

Package evidence:

`framework-standardization/runtime/reports/submersible_pumps_voltage/20260710190844_6a51433c8f43`

Target canonical attribute: `15`.

Allowed canonical values in this plan: `"220"`, `"380"`.

## Approved Manual Resolutions

| product_id | source attribute | source raw value | target attribute | approved canonical value | apply status |
| ---------: | ---------------: | ---------------- | ---------------: | -----------------------: | ------------ |
| 8231 | 79 | `220-230 В (однофазный) / 380-400 В (трехфазный)` | 15 | 220 | blocked |
| 8233 | 79 | `220-230 В (однофазный) / 380-400 В (трехфазный)` | 15 | 220 | blocked |
| 8277 | 79 | `400 В (3 фазы, 50 Гц); также доступна однофазная версия 230 В` | 15 | 380 | blocked |

## Boundaries

- This is documentation only.
- This plan does not contain SQL.
- This plan does not authorize apply-plan, SQL/apply, `--confirm-apply`, product/category changes, production/cache actions, or cache rebuild.
- Target canonical attribute is only `15`.
- Canonical value is only `"220"` or `"380"`.
- Alias rows may be removed only by a separate approved apply-plan.
- Other attributes are not changed.
- Categories are not changed.
- Unresolved products are not touched: `8192`, `8218`, `8219`, `8226`, `8227`.
- Invalid product `8243` is not touched.
- Product IDs must not be added to `VoltageNormalizer`.
