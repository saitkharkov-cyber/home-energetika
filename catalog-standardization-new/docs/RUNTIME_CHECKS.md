# RUNTIME CHECKS — catalog-standardization-new

Здесь фиксируются только существенные проверки на реальных данных: что проверялось, каким способом, в каком режиме, какой результат получен и какое было влияние на DB, product data, production или cache.

Обычные syntax checks и стандартные тесты не добавляются, если они не выявили нового существенного runtime-факта. Запись не является разрешением на SQL/apply или иное изменение.

## Шаблон записи

~~~markdown
## YYYY-MM-DD HH:mm:ss Europe/Kyiv — краткое название

- Date/time:
- Target:
- Check type:
- Command or method:
- Mode:
- Environment:
- Data/DB/production/cache impact:
- Result:
- Conclusion:
- Related files or decision:
~~~

## 2026-07-24 — Vinko catalog parser validation

- Date/time: 2026-07-24, Europe/Kyiv
- Target: Vinko catalog parser and generated `vinko_catalog.csv`
- Check type: Real-source parser validation
- Command or method: Full parser run through proxy, followed by CSV consistency checks
- Mode: Read-only
- Environment: Local repository `D:\Git\home-energetika`, source `https://nasosymarket.ru/catalog/vinko/`
- Data/DB/production/cache impact: No DB, product data, production, or cache changes
- Result: 8 pages fetched; 91 unique models; all 91 records contain normalized head, flow, voltage, and power; flow converted from m³/h and rounded to whole l/min; 47 models are 220 V and 44 are 380 V; all 35 paired 4ST/4STm models have identical head, flow, and power; only remaining review reason is `series_diameter_only`
- Conclusion: Parser output is internally consistent and suitable for the next matching stage. Physical diameter remains unconfirmed and `normalized_diameter_mm` stays empty.
- Related files or decision: `scripts/parsers/parse_vinko_catalog.py`; commits `b077a7c`, `7d9f57c`

---
Последнее обновление: 2026-07-24 Europe/Kyiv

