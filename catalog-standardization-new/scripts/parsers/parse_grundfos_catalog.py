#!/usr/bin/env python3
"""Read-only specialized parser for the Grundfos catalog and detail pages."""

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import unicodedata
from collections import Counter
from dataclasses import asdict, dataclass
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
from pathlib import Path
from urllib.parse import urljoin, urlparse

try:
    import requests
    from bs4 import BeautifulSoup, Tag
except ImportError as exc:
    raise ImportError(
        "Missing parser dependency. Install 'requests' and 'beautifulsoup4'."
    ) from exc


SOURCE_CATALOG_URL = "https://nasosymarket.ru/catalog/grundfos_2/"
EXPECTED_PAGES = 3
EXPECTED_RECORDS = 27
USER_AGENT = "home-energetika-grundfos-parser/1.0"
DETAIL_ROOT_SELECTOR = "div[id$='_main_properties'] > .catalog-detail-properties"
DETAIL_ROW_SELECTOR = ":scope > .catalog-detail-property"
FIELDNAMES = (
    "source_catalog_url",
    "source_page_url",
    "source_product_url",
    "raw_product_name",
    "normalized_model",
    "raw_max_head",
    "normalized_max_head_m",
    "raw_max_flow",
    "normalized_max_flow_l_min",
    "raw_power",
    "normalized_power_kw",
    "raw_voltage",
    "normalized_voltage_v",
    "raw_temperature",
    "raw_min_casing_inner_diameter",
    "normalized_min_casing_inner_diameter_mm",
    "parse_status",
    "review_reason",
)
REQUIRED_NORMALIZED_FIELDS = (
    "normalized_max_head_m",
    "normalized_max_flow_l_min",
    "normalized_power_kw",
    "normalized_voltage_v",
    "normalized_min_casing_inner_diameter_mm",
)
LABEL_ALIASES = {
    "max_head": {"Максимальный напор, м", "Макс. напор"},
    "max_flow": {"Макс. производительность, м³/ч", "Макс. производ"},
    "power": {"Потребляемая мощность, Вт", "Макс. мощность"},
    "voltage": {"Напряжение сети, В", "Напряжение"},
    "temperature": {"Температура"},
    "min_casing": {"Мин. внутренний диаметр обсадной трубы, мм"},
}
URL_MODEL_REVIEW_URL = (
    "https://nasosymarket.ru/catalog/vodyanye_nasosy/"
    "skvazhinnyy_nasos_grundfos_sqe_5_70_/"
)


@dataclass
class GrundfosRecord:
    source_catalog_url: str
    source_page_url: str
    source_product_url: str
    raw_product_name: str
    normalized_model: str
    raw_max_head: str
    normalized_max_head_m: str
    raw_max_flow: str
    normalized_max_flow_l_min: str
    raw_power: str
    normalized_power_kw: str
    raw_voltage: str
    normalized_voltage_v: str
    raw_temperature: str
    raw_min_casing_inner_diameter: str
    normalized_min_casing_inner_diameter_mm: str
    parse_status: str
    review_reason: str


class ParserError(Exception):
    """Raised for source and parser contract failures."""


def clean_text(value: str) -> str:
    value = unicodedata.normalize("NFC", value)
    value = value.replace("\xad", "").replace("\xa0", " ")
    return re.sub(r"\s+", " ", value).strip()


def parse_decimal(value: str) -> Decimal | None:
    try:
        return Decimal(value.replace(",", "."))
    except (InvalidOperation, AttributeError):
        return None


def format_decimal(value: Decimal) -> str:
    text = format(value.normalize(), "f")
    return text.rstrip("0").rstrip(".") if "." in text else text


def bare_number(value: str) -> Decimal | None:
    match = re.fullmatch(r"(\d+(?:[.,]\d+)?)", clean_text(value))
    return parse_decimal(match.group(1)) if match else None


def normalize_head(value: str) -> str:
    parsed = bare_number(value)
    return format_decimal(parsed) if parsed is not None else ""


def normalize_flow(value: str) -> str:
    parsed = bare_number(value)
    if parsed is None:
        return ""
    normalized = (parsed * Decimal(1000) / Decimal(60)).quantize(
        Decimal("1"), rounding=ROUND_HALF_UP
    )
    return format_decimal(normalized)


def normalize_power(value: str, label: str) -> str:
    parsed = bare_number(value)
    if parsed is None:
        return ""
    label = clean_text(label).lower()
    if "вт" in label:
        parsed /= Decimal(1000)
    return format_decimal(parsed)


def normalize_voltage(value: str) -> str:
    text = clean_text(value)
    if re.fullmatch(r"\d{3}", text):
        return text
    match = re.fullmatch(r"(?:3\s*[xх]\s*)?(\d{3})\s*[вv]?", text, re.I)
    return match.group(1) if match else ""


def normalize_min_casing(value: str) -> str:
    parsed = bare_number(value)
    return format_decimal(parsed) if parsed is not None else ""


def normalize_model(name: str) -> str:
    name = clean_text(name)
    return clean_text(
        re.sub(r"^Скважинный\s+насос\s+Grundfos\s+", "", name, flags=re.I)
    )


def load_proxy(proxy_file: Path) -> dict[str, str]:
    if not proxy_file.is_file():
        raise ParserError("proxy file must exist and be a regular file")
    lines = [
        line.strip()
        for line in proxy_file.read_text(encoding="utf-8").splitlines()
        if line.strip()
    ]
    if len(lines) != 1:
        raise ParserError("proxy file must contain exactly one non-empty line")
    parsed = urlparse(lines[0])
    if parsed.scheme not in {"http", "https"} or not parsed.netloc:
        raise ParserError("proxy file must contain an http or https URL")
    return {"http": lines[0], "https": lines[0]}


def fetch(session: requests.Session, url: str, proxies: dict[str, str]) -> str:
    response = session.get(
        url,
        headers={"User-Agent": USER_AGENT, "Accept-Language": "ru-RU,ru;q=0.9"},
        proxies=proxies,
        timeout=30,
    )
    response.raise_for_status()
    response.encoding = response.apparent_encoding or response.encoding or "utf-8"
    return response.text


def catalog_card_urls(page_html: str, page_url: str) -> list[str]:
    soup = BeautifulSoup(page_html, "html.parser")
    urls: list[str] = []
    for card in soup.select(".catalog-item-card"):
        link = card.select_one("a.item-title[href]")
        if link is not None:
            urls.append(urljoin(page_url, str(link["href"])))
    if not urls:
        raise ParserError(f"no catalog cards found: {page_url}")
    return urls


def find_next_page_url(page_html: str, current_url: str, page_number: int) -> str | None:
    soup = BeautifulSoup(page_html, "html.parser")
    for link in soup.select("a[href]"):
        if clean_text(link.get_text(" ", strip=True)) == str(page_number + 1):
            return urljoin(current_url, str(link["href"]))
    return None


def detail_properties(soup: BeautifulSoup) -> dict[str, str]:
    roots = soup.select(DETAIL_ROOT_SELECTOR)
    if len(roots) != 1:
        raise ParserError(f"expected one detail root, found {len(roots)}")
    properties: dict[str, str] = {}
    for row in roots[0].select(DETAIL_ROW_SELECTOR):
        name = row.select_one(":scope > .name")
        value = row.select_one(":scope > .val")
        if name is None or value is None:
            continue
        label = clean_text(name.get_text(" ", strip=True))
        raw_value = clean_text(value.get_text(" ", strip=True))
        if label and label not in properties:
            properties[label] = raw_value
    return properties


def pick_property(properties: dict[str, str], aliases: set[str]) -> tuple[str, str]:
    matches = [(label, value) for label, value in properties.items() if label in aliases]
    if len(matches) > 1:
        raise ParserError(f"conflicting source labels: {', '.join(label for label, _ in matches)}")
    return matches[0] if matches else ("", "")


def detail_record(product_url: str, page_url: str, page_html: str) -> GrundfosRecord:
    soup = BeautifulSoup(page_html, "html.parser")
    title_node = soup.select_one("h1#pagetitle") or soup.select_one("h1")
    raw_name = clean_text(title_node.get_text(" ", strip=True)) if title_node else ""
    properties = detail_properties(soup)
    head_label, head = pick_property(properties, LABEL_ALIASES["max_head"])
    flow_label, flow = pick_property(properties, LABEL_ALIASES["max_flow"])
    power_label, power = pick_property(properties, LABEL_ALIASES["power"])
    _, voltage = pick_property(properties, LABEL_ALIASES["voltage"])
    _, temperature = pick_property(properties, LABEL_ALIASES["temperature"])
    _, min_casing = pick_property(properties, LABEL_ALIASES["min_casing"])
    reasons: set[str] = set()
    normalized_model = normalize_model(raw_name)
    if product_url == URL_MODEL_REVIEW_URL and normalized_model == "SQ 5-70":
        reasons.add("url_model_series_mismatch")
    record = GrundfosRecord(
        source_catalog_url=SOURCE_CATALOG_URL,
        source_page_url=page_url,
        source_product_url=product_url,
        raw_product_name=raw_name,
        normalized_model=normalized_model,
        raw_max_head=head,
        normalized_max_head_m=normalize_head(head),
        raw_max_flow=flow,
        normalized_max_flow_l_min=normalize_flow(flow),
        raw_power=power,
        normalized_power_kw=normalize_power(power, power_label),
        raw_voltage=voltage,
        normalized_voltage_v=normalize_voltage(voltage),
        raw_temperature=temperature,
        raw_min_casing_inner_diameter=min_casing,
        normalized_min_casing_inner_diameter_mm=normalize_min_casing(min_casing),
        parse_status="ok",
        review_reason="",
    )
    missing = [field for field in REQUIRED_NORMALIZED_FIELDS if not getattr(record, field)]
    if missing:
        reasons.update(f"missing_{field.removeprefix('normalized_')}" for field in missing)
        record.parse_status = "missing_required"
    elif reasons:
        record.parse_status = "needs_review"
    record.review_reason = ";".join(sorted(reasons))
    return record


def write_csv(records: list[GrundfosRecord], output: Path) -> None:
    if output.exists():
        raise ParserError(f"output already exists: {output}")
    with output.open("x", newline="", encoding="utf-8-sig") as file:
        writer = csv.DictWriter(
            file,
            fieldnames=FIELDNAMES,
            lineterminator="\r\n",
            quoting=csv.QUOTE_MINIMAL,
        )
        writer.writeheader()
        for record in records:
            writer.writerow(asdict(record))


def range_summary(records: list[GrundfosRecord], field: str) -> dict[str, str]:
    values = [Decimal(getattr(record, field)) for record in records]
    return {"min": format_decimal(min(values)), "max": format_decimal(max(values))}


def summary(records: list[GrundfosRecord], page_urls: list[str]) -> dict[str, object]:
    return {
        "pages_fetched": len(page_urls),
        "records": len(records),
        "unique_source_product_urls": len({record.source_product_url for record in records}),
        "duplicate_normalized_models": sorted(
            model
            for model, count in Counter(record.normalized_model for record in records).items()
            if count > 1
        ),
        "status_counts": dict(sorted(Counter(record.parse_status for record in records).items())),
        "review_reasons": dict(
            sorted(
                Counter(
                    reason
                    for record in records
                    for reason in record.review_reason.split(";")
                    if reason
                ).items()
            )
        ),
        "series_counts": dict(
            sorted(Counter(record.normalized_model.split(" ", 1)[0] for record in records).items())
        ),
        "min_casing_distribution": dict(
            sorted(
                Counter(record.normalized_min_casing_inner_diameter_mm for record in records).items(),
                key=lambda item: Decimal(item[0]),
            )
        ),
        "missing_required": {
            field: sum(not getattr(record, field) for record in records)
            for field in REQUIRED_NORMALIZED_FIELDS
        },
        "ranges": {
            "max_head_m": range_summary(records, "normalized_max_head_m"),
            "max_flow_l_min": range_summary(records, "normalized_max_flow_l_min"),
            "power_kw": range_summary(records, "normalized_power_kw"),
            "voltage_v": range_summary(records, "normalized_voltage_v"),
        },
    }


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Read-only specialized Grundfos parser.")
    parser.add_argument("--output", type=Path, default=Path("grundfos_catalog.csv"))
    parser.add_argument(
        "--proxy-file",
        type=Path,
        default=Path("catalog-standardization/scripts/local_proxy.txt"),
    )
    args = parser.parse_args(argv)
    try:
        if args.output.exists():
            raise ParserError(f"output already exists: {args.output}")
        proxies = load_proxy(args.proxy_file)
        records: list[GrundfosRecord] = []
        page_urls: list[str] = []
        seen_urls: set[str] = set()
        current_url = SOURCE_CATALOG_URL
        with requests.Session() as session:
            for page_number in range(1, EXPECTED_PAGES + 1):
                page_html = fetch(session, current_url, proxies)
                page_urls.append(current_url)
                for product_url in catalog_card_urls(page_html, current_url):
                    if product_url in seen_urls:
                        continue
                    seen_urls.add(product_url)
                    records.append(
                        detail_record(
                            product_url,
                            current_url,
                            fetch(session, product_url, proxies),
                        )
                    )
                if page_number < EXPECTED_PAGES:
                    next_url = find_next_page_url(page_html, current_url, page_number)
                    if not next_url:
                        raise ParserError(f"missing catalog page {page_number + 1}")
                    current_url = next_url
        records.sort(key=lambda record: (record.source_page_url, record.source_product_url))
        report = summary(records, page_urls)
        if len(records) != EXPECTED_RECORDS or report["unique_source_product_urls"] != EXPECTED_RECORDS:
            raise ParserError("expected exactly 27 unique source product URLs")
        if report["duplicate_normalized_models"]:
            raise ParserError("duplicate normalized models found")
        if any(report["missing_required"].values()):
            raise ParserError("required normalized fields are missing")
        if report["min_casing_distribution"] != {"80": 25, "110": 1, "120": 1}:
            raise ParserError("unexpected minimum casing diameter distribution")
        write_csv(records, args.output)
        print(json.dumps(report, ensure_ascii=True, indent=2))
        return 0
    except (ParserError, requests.RequestException) as exc:
        print(f"parser error: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
