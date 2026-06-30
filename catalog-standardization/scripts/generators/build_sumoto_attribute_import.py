#!/usr/bin/env python3
from __future__ import annotations

import csv
from dataclasses import dataclass
from decimal import Decimal, InvalidOperation
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent
SOURCE_CSV = REPO_ROOT / "sumoto_import_ready_98.csv"
OUTPUT_CSV = REPO_ROOT / "sumoto_product_attribute_import_98.csv"


@dataclass
class SourceRow:
    product_id: str
    max_head_m: str
    max_flow_l_min: str
    voltage: str
    power_kw: str
    diameter_mm: str
    match_status: str


def norm_space(value: str) -> str:
    return (value or "").strip()


def read_source_rows() -> list[SourceRow]:
    rows: list[SourceRow] = []
    with SOURCE_CSV.open("r", encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            rows.append(
                SourceRow(
                    product_id=norm_space(row.get("product_id", "")),
                    max_head_m=norm_space(row.get("max_head_m", "")),
                    max_flow_l_min=norm_space(row.get("max_flow_l_min", "")),
                    voltage=norm_space(row.get("voltage", "")),
                    power_kw=norm_space(row.get("power_kw", "")),
                    diameter_mm=norm_space(row.get("diameter_mm", "")),
                    match_status=norm_space(row.get("match_status", "")),
                )
            )
    return rows


def parse_decimal(value: str) -> Decimal:
    return Decimal(value.replace(",", "."))


def format_decimal_trimmed(value: Decimal) -> str:
    text = format(value.normalize(), "f")
    if "." in text:
        text = text.rstrip("0").rstrip(".")
    return text


def format_flow_attribute(max_flow_l_min: str) -> str:
    liters = parse_decimal(max_flow_l_min)
    cubic = liters * Decimal(60) / Decimal(1000)
    cubic_text = format(cubic.quantize(Decimal("0.1")), "f")
    liters_text = format_decimal_trimmed(liters)
    return f"{cubic_text} куб/час ({liters_text}л/мин)"


def format_power_kw(power_kw: str) -> str:
    return f"{power_kw.replace('.', ',')} кВт"


def format_head(max_head_m: str) -> str:
    return f"{max_head_m}м."


def format_voltage(voltage: str) -> str:
    return f"{voltage}V"


def format_diameter(diameter_mm: str) -> str:
    return f"{diameter_mm}мм."


def add_row(rows: list[dict[str, str]], product_id: str, attribute_id: int, text: str) -> None:
    rows.append(
        {
            "product_id": product_id,
            "attribute_id": str(attribute_id),
            "language_id": "1",
            "text": text,
        }
    )


def main() -> int:
    source_rows = read_source_rows()
    out_rows: list[dict[str, str]] = []
    products_processed = 0
    missing_power_kw = 0
    warnings_count = 0

    for row in source_rows:
        if row.match_status != "matched":
            continue

        products_processed += 1

        if row.max_head_m:
            add_row(out_rows, row.product_id, 12, format_head(row.max_head_m))
        else:
            warnings_count += 1
            print(f"warning: product_id={row.product_id} missing max_head_m")

        if row.max_flow_l_min:
            try:
                add_row(out_rows, row.product_id, 13, format_flow_attribute(row.max_flow_l_min))
            except (InvalidOperation, ValueError):
                warnings_count += 1
                print(f"warning: product_id={row.product_id} invalid max_flow_l_min={row.max_flow_l_min}")
        else:
            warnings_count += 1
            print(f"warning: product_id={row.product_id} missing max_flow_l_min")

        if row.power_kw:
            add_row(out_rows, row.product_id, 14, format_power_kw(row.power_kw))
        else:
            missing_power_kw += 1

        if row.voltage:
            add_row(out_rows, row.product_id, 15, format_voltage(row.voltage))
        else:
            warnings_count += 1
            print(f"warning: product_id={row.product_id} missing voltage")

        if row.diameter_mm:
            add_row(out_rows, row.product_id, 44, format_diameter(row.diameter_mm))
        else:
            warnings_count += 1
            print(f"warning: product_id={row.product_id} missing diameter_mm")

    with OUTPUT_CSV.open("w", encoding="utf-8-sig", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=["product_id", "attribute_id", "language_id", "text"])
        writer.writeheader()
        writer.writerows(out_rows)

    print(f"products processed: {products_processed}")
    print(f"attribute rows written: {len(out_rows)}")
    print(f"missing power_kw: {missing_power_kw}")
    print(f"warnings count: {warnings_count}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
