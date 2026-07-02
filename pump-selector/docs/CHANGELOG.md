## 2026-07-02

### Changed

- Reworked recommendation role assignment logic.
- Introduced distinct recommendation roles:
  - Best Price — lowest-priced suitable pump.
  - Optimal — balanced recommendation with better performance/value.
  - Premium — reserved for premium brands only.
- Prevented the same product from occupying multiple recommendation roles.
- Prevented the same brand from appearing in multiple recommendation roles when alternatives exist.
- If no premium recommendation is available, Premium is omitted instead of assigning a misleading product.
- Updated recommendation selection to produce consistent and explainable results across test scenarios.