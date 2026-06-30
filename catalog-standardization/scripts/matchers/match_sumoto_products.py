#!/usr/bin/env python3
from __future__ import annotations

import csv
import difflib
import re
import sys
from collections import defaultdict
from dataclasses import dataclass
from pathlib import Path


SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent
HOME_CSV = REPO_ROOT / "p.csv"
MANUAL_SOURCE_CSV = REPO_ROOT / "sumoto_manual_specs.csv"
SOURCE_CSV = REPO_ROOT / "sumoto_parsed_preview.csv"
OUTPUT_CSV = REPO_ROOT / "sumoto_matched_preview.csv"


@dataclass
class HomeRow:
    product_id: str
    name: str
    model: str
    manufacturer: str


@dataclass
class SourceRow:
    title: str
    model: str
    max_head_m: str
    max_flow_original: str
    max_flow_l_min: str
    voltage: str
    power_kw: str
    diameter_raw: str
    diameter_mm: str
    source_url: str
    status: str
    source_used: str


def norm_space(value: str) -> str:
    return re.sub(r"\s+", " ", (value or "")).strip()


def extract_model_from_name(name: str) -> str:
    name = norm_space(name)
    match = re.search(
        r"\b((?:OPC\s*)?(?:3OPC|4SRM|4SR|6OPC|6SH|6SR)\s*\d+(?:[.,]\d+)?(?:/\d+)?)\b",
        name,
        flags=re.I,
    )
    if match:
        return norm_space(match.group(1))
    match = re.search(r"\bSUMOTO\s+(.+)$", name, flags=re.I)
    if match:
        return norm_space(match.group(1))
    return ""


def is_numeric_model(value: str) -> bool:
    text = norm_space(value)
    return bool(text) and text.isdigit()


def extract_voltage(value: str) -> str:
    text = norm_space(value).upper()
    if re.search(r"(?<!\d)220\s*[ВV]\b|\(220\s*[ВV]\)", text, re.I):
        return "220"
    if re.search(r"(?<!\d)380\s*[ВV]\b|\(380\s*[ВV]\)", text, re.I):
        return "380"
    return ""


def normalize_model_key(value: str) -> str:
    text = norm_space(value).upper()
    if not text:
        return ""

    text = text.replace("ОРС", "OPC").replace("ОPC", "OPC")
    text = text.replace("СРМ", "SRM")
    text = text.replace("СР", "SR")

    text = re.sub(r"\bOPC\s*(?=(?:4SRM|4SR|6SR|6OPC))", "", text)
    text = re.sub(r"\(?\b220\s*[ВV]\b\)?", "", text, flags=re.I)
    text = re.sub(r"\(?\b380\s*[ВV]\b\)?", "", text, flags=re.I)
    text = re.sub(r"[\s\-]+", "", text)
    return text.upper()


def is_pump(name: str) -> bool:
    lowered = (name or "").lower()
    return "насос" in lowered and "электродвигатель" not in lowered


def read_home_rows() -> list[HomeRow]:
    rows: list[HomeRow] = []
    with HOME_CSV.open("r", encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f, delimiter=";")
        for row in reader:
            rows.append(
                HomeRow(
                    product_id=norm_space(row.get("product_id", "")),
                    name=norm_space(row.get("name", "")),
                    model=norm_space(row.get("model", "")),
                    manufacturer=norm_space(row.get("manufacturer", "")),
                )
            )
    return rows


def read_source_rows(path: Path, source_used: str) -> list[SourceRow]:
    if not path.exists():
        return []

    rows: list[SourceRow] = []
    with path.open("r", encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            rows.append(
                SourceRow(
                    title=norm_space(row.get("title", "")),
                    model=norm_space(row.get("model", "")),
                    max_head_m=norm_space(row.get("max_head_m", "")),
                    max_flow_original=norm_space(row.get("max_flow_original", "")),
                    max_flow_l_min=norm_space(row.get("max_flow_l_min", "")),
                    voltage=norm_space(row.get("voltage", "")),
                    power_kw=norm_space(row.get("power_kw", "")),
                    diameter_raw=norm_space(row.get("diameter_raw", "")),
                    diameter_mm=norm_space(row.get("diameter_mm", "")),
                    source_url=norm_space(row.get("source_url", "")),
                    status=norm_space(row.get("status", "")),
                    source_used=source_used,
                )
            )
    return rows


def write_output(rows: list[dict[str, str]]) -> None:
    fieldnames = [
        "product_id",
        "home_name",
        "home_model",
        "home_voltage_from_name",
        "home_norm_model",
        "match_key_home",
        "source_title",
        "source_model",
        "source_voltage",
        "match_key_source",
        "closest_source_model",
        "closest_source_norm_model",
        "max_head_m",
        "max_flow_original",
        "max_flow_l_min",
        "voltage",
        "power_kw",
        "diameter_raw",
        "diameter_mm",
        "source_url",
        "source_status",
        "source_used",
        "match_status",
    ]
    with OUTPUT_CSV.open("w", encoding="utf-8-sig", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        writer.writerows(rows)


def build_source_index(source_rows: list[SourceRow]) -> tuple[dict[str, list[SourceRow]], list[str], dict[str, str]]:
    source_by_key: dict[str, list[SourceRow]] = defaultdict(list)
    source_keys: list[str] = []
    source_key_to_model: dict[str, str] = {}
    for row in source_rows:
        key = normalize_model_key(row.model)
        source_by_key[key].append(row)
        if key:
            source_keys.append(key)
            source_key_to_model.setdefault(key, row.model)
    return source_by_key, source_keys, source_key_to_model


def pick_best_source(source_rows: list[SourceRow]) -> SourceRow:
    return sorted(source_rows, key=lambda row: (row.model, row.title))[0]


def duplicate_in_source(source_rows: list[SourceRow]) -> bool:
    grouped: dict[tuple[str, str], int] = defaultdict(int)
    for row in source_rows:
        grouped[(normalize_model_key(row.model), extract_voltage(row.voltage))] += 1
    return any(count > 1 for count in grouped.values())


def infer_diameter_from_model(model: str) -> tuple[str, str]:
    cleaned_model = re.sub(r"\s+", "", norm_space(model).upper())
    if "3OPC" in cleaned_model:
        return "3OPC heuristic", "76"
    if "4SRM" in cleaned_model or "4SR" in cleaned_model or "OPC4SR" in cleaned_model:
        return "4SR heuristic", "96"
    if "6OPC" in cleaned_model or "6SH" in cleaned_model or "6SR" in cleaned_model:
        return "6 inch heuristic", "146"
    return "", ""


def fill_missing_diameter(row: dict[str, str]) -> None:
    if row.get("diameter_mm"):
        return

    source_model = row.get("source_model", "")
    home_model = row.get("home_model", "")
    diameter_raw, diameter_mm = infer_diameter_from_model(source_model or home_model)
    if diameter_mm:
        row["diameter_raw"] = diameter_raw
        row["diameter_mm"] = diameter_mm


def main() -> int:
    home_rows = read_home_rows()
    manual_rows = read_source_rows(MANUAL_SOURCE_CSV, "manual")
    naso_rows = read_source_rows(SOURCE_CSV, "nasosymarket")
    manual_by_key, manual_keys, manual_key_to_model = build_source_index(manual_rows)
    naso_by_key, naso_keys, naso_key_to_model = build_source_index(naso_rows)

    out_rows: list[dict[str, str]] = []
    not_found_diags: list[tuple[str, str, str]] = []
    total_products = len(home_rows)
    pump_count = 0
    skipped_not_pump = 0
    matched_total = 0
    matched_manual = 0
    matched_nasosymarket = 0
    not_found = 0
    duplicate_source = 0
    voltage_mismatch = 0

    for row in home_rows:
        if not is_pump(row.name):
            skipped_not_pump += 1
            out_rows.append(
                {
                    "product_id": row.product_id,
                    "home_name": row.name,
                    "home_model": row.model,
                    "home_voltage_from_name": "",
                    "home_norm_model": "",
                    "match_key_home": "",
                    "source_title": "",
                    "source_model": "",
                    "source_voltage": "",
                    "match_key_source": "",
                    "closest_source_model": "",
                    "closest_source_norm_model": "",
                    "max_head_m": "",
                    "max_flow_original": "",
                    "max_flow_l_min": "",
                    "voltage": "",
                    "power_kw": "",
                    "diameter_raw": "",
                    "diameter_mm": "",
                    "source_url": "",
                    "source_status": "",
                    "source_used": "none",
                    "match_status": "skipped_not_pump",
                }
            )
            continue

        pump_count += 1
        extracted_model = row.model if row.model and not is_numeric_model(row.model) else ""
        if not extracted_model:
            extracted_model = extract_model_from_name(row.name)
        home_voltage_from_name = extract_voltage(f"{row.name} {row.model}")
        home_norm_model = normalize_model_key(extracted_model or row.model)
        match_key_home = home_norm_model

        matches = manual_by_key.get(match_key_home, [])
        source_pool = "manual"
        source_keys = manual_keys
        source_key_to_model = manual_key_to_model
        if not matches:
            matches = naso_by_key.get(match_key_home, [])
            source_pool = "nasosymarket"
            source_keys = naso_keys
            source_key_to_model = naso_key_to_model

        if not matches:
            not_found += 1
            closest_source_model = ""
            closest_source_norm_model = ""
            closest = difflib.get_close_matches(match_key_home, source_keys, n=1, cutoff=0.45)
            if closest:
                closest_source_norm_model = closest[0]
                closest_source_model = source_key_to_model.get(closest_source_norm_model, "")
            not_found_diags.append((extracted_model or row.model, home_norm_model, closest_source_model))
            out_rows.append(
                {
                    "product_id": row.product_id,
                    "home_name": row.name,
                    "home_model": extracted_model or row.model,
                    "home_voltage_from_name": home_voltage_from_name,
                    "home_norm_model": home_norm_model,
                    "match_key_home": match_key_home,
                    "source_title": "",
                    "source_model": "",
                    "source_voltage": "",
                    "match_key_source": "",
                    "closest_source_model": closest_source_model,
                    "closest_source_norm_model": closest_source_norm_model,
                    "max_head_m": "",
                    "max_flow_original": "",
                    "max_flow_l_min": "",
                    "voltage": "",
                    "power_kw": "",
                    "diameter_raw": "",
                    "diameter_mm": "",
                    "source_url": "",
                    "source_status": "",
                    "source_used": "none",
                    "match_status": "not_found",
                }
            )
            continue

        if duplicate_in_source(matches):
            duplicate_source += 1
            best = pick_best_source(matches)
            source_voltage = extract_voltage(best.voltage)
            match_key_source = normalize_model_key(best.model)
            out_rows.append(
                {
                    "product_id": row.product_id,
                    "home_name": row.name,
                    "home_model": extracted_model or row.model,
                    "home_voltage_from_name": home_voltage_from_name,
                    "home_norm_model": home_norm_model,
                    "match_key_home": match_key_home,
                    "source_title": best.title,
                    "source_model": best.model,
                    "source_voltage": source_voltage,
                    "match_key_source": match_key_source,
                    "closest_source_model": best.model,
                    "closest_source_norm_model": match_key_source,
                    "max_head_m": best.max_head_m,
                    "max_flow_original": best.max_flow_original,
                    "max_flow_l_min": best.max_flow_l_min,
                    "voltage": best.voltage,
                    "power_kw": best.power_kw,
                    "diameter_raw": best.diameter_raw,
                    "diameter_mm": best.diameter_mm,
                    "source_url": best.source_url,
                    "source_status": best.status,
                    "source_used": best.source_used,
                    "match_status": "duplicate_source",
                }
            )
            continue

        best = pick_best_source(matches)
        source_voltage = extract_voltage(best.voltage)
        match_key_source = normalize_model_key(best.model)

        if home_voltage_from_name and source_voltage and home_voltage_from_name != source_voltage:
            voltage_mismatch += 1
            out_rows.append(
                {
                    "product_id": row.product_id,
                    "home_name": row.name,
                    "home_model": extracted_model or row.model,
                    "home_voltage_from_name": home_voltage_from_name,
                    "home_norm_model": home_norm_model,
                    "match_key_home": match_key_home,
                    "source_title": best.title,
                    "source_model": best.model,
                    "source_voltage": source_voltage,
                    "match_key_source": match_key_source,
                    "closest_source_model": best.model,
                    "closest_source_norm_model": match_key_source,
                    "max_head_m": best.max_head_m,
                    "max_flow_original": best.max_flow_original,
                    "max_flow_l_min": best.max_flow_l_min,
                    "voltage": best.voltage,
                    "power_kw": best.power_kw,
                    "diameter_raw": best.diameter_raw,
                    "diameter_mm": best.diameter_mm,
                    "source_url": best.source_url,
                    "source_status": best.status,
                    "source_used": best.source_used,
                    "match_status": "voltage_mismatch",
                }
            )
            continue

        matched_total += 1
        match_status = "matched"
        if source_pool == "manual":
            matched_manual += 1
        else:
            matched_nasosymarket += 1

        out_rows.append(
            {
                "product_id": row.product_id,
                "home_name": row.name,
                "home_model": extracted_model or row.model,
                "home_voltage_from_name": home_voltage_from_name,
                "home_norm_model": home_norm_model,
                "match_key_home": match_key_home,
                "source_title": best.title,
                "source_model": best.model,
                "source_voltage": source_voltage,
                "match_key_source": match_key_source,
                "closest_source_model": best.model,
                "closest_source_norm_model": match_key_source,
                "max_head_m": best.max_head_m,
                "max_flow_original": best.max_flow_original,
                "max_flow_l_min": best.max_flow_l_min,
                "voltage": best.voltage,
                "power_kw": best.power_kw,
                "diameter_raw": best.diameter_raw,
                "diameter_mm": best.diameter_mm,
                "source_url": best.source_url,
                "source_status": best.status,
                "source_used": best.source_used,
                "match_status": match_status,
            }
        )

    for row in out_rows:
        if row.get("match_status") == "matched":
            fill_missing_diameter(row)

    write_output(out_rows)

    print(f"всего товаров в p.csv: {total_products}")
    print(f"насосов Sumoto: {pump_count}")
    print(f"skipped_not_pump: {skipped_not_pump}")
    matched_total = sum(1 for row in out_rows if row["match_status"] == "matched")
    matched_manual = sum(1 for row in out_rows if row["match_status"] == "matched" and row["source_used"] == "manual")
    matched_nasosymarket = sum(1 for row in out_rows if row["match_status"] == "matched" and row["source_used"] == "nasosymarket")
    duplicate_source = sum(1 for row in out_rows if row["match_status"] == "duplicate_source")
    voltage_mismatch = sum(1 for row in out_rows if row["match_status"] == "voltage_mismatch")

    print(f"matched_total: {matched_total}")
    print(f"matched_manual: {matched_manual}")
    print(f"matched_nasosymarket: {matched_nasosymarket}")
    print(f"not_found: {not_found}")
    print(f"duplicate_source: {duplicate_source}")
    print(f"voltage_mismatch: {voltage_mismatch}")
    for home_model, home_norm_model, closest_source_model in not_found_diags[:30]:
        print(f"{home_model} - {home_norm_model} - {closest_source_model}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
