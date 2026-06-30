#!/usr/bin/env python3
from __future__ import annotations

import csv
import json
import re
import sys
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable, Optional
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup


BASE_URL = "https://nasosymarket.ru"
START_URL = f"{BASE_URL}/catalog/sumoto/"
MAX_PAGES = 14

SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent
OUT_CSV = REPO_ROOT / "sumoto_parsed_preview.csv"
PROXY_FILE = REPO_ROOT / "local_proxy.txt"

HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/125.0.0.0 Safari/537.36"
    ),
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    "Accept-Language": "ru-RU,ru;q=0.9,en;q=0.8",
    "Connection": "close",
}


@dataclass
class ProductRow:
    card_id: str
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


def load_proxies() -> Optional[dict[str, str]]:
    if not PROXY_FILE.exists():
        return None
    proxy_url = PROXY_FILE.read_text(encoding="utf-8").strip()
    if not proxy_url:
        return None
    return {"http": proxy_url, "https": proxy_url}


PROXIES = load_proxies()


def fetch(url: str, session: requests.Session) -> str:
    response = session.get(url, headers=HEADERS, timeout=30, proxies=PROXIES)
    response.raise_for_status()
    response.encoding = response.apparent_encoding or response.encoding or "utf-8"
    return response.text


def clean_text(value: str) -> str:
    value = value.replace("\xad", "")
    value = value.replace("&shy;", "")
    value = value.replace("\xa0", " ")
    value = re.sub(r"\s+", " ", value)
    return value.strip()


def normalize_flow_to_l_min(text: str) -> str:
    value = clean_text(text)
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*l\s*/\s*min\b", value, re.I)
    if match:
        return match.group(1).replace(",", ".")
    match = re.search(
        r"(\d+(?:[.,]\d+)?)\s*(?:м3\s*/\s*ч(?:ас)?|м\^3\s*/\s*ч(?:ас)?|м³\s*/\s*ч(?:ас)?)",
        value,
        re.I,
    )
    if match:
        m3h = float(match.group(1).replace(",", "."))
        return f"{m3h * 1000 / 60:.2f}".rstrip("0").rstrip(".")
    return ""


def extract_model(title: str) -> str:
    title = clean_text(title)
    title = re.sub(r"^Скважинный\s+погружной\s+насос\s+", "", title, flags=re.I)
    title = re.sub(r"^Погружной\s+скважинный\s+насос\s+", "", title, flags=re.I)
    title = re.sub(r"^Скважинные\s+насосы\s+", "", title, flags=re.I)
    match = re.search(r"\b(?:SUMOTO|Sumoto)\s+(.+)$", title)
    if match:
        return clean_text(match.group(1))
    return title


def extract_diameter_from_model(model: str) -> tuple[str, str, bool]:
    model = clean_text(model)
    if re.search(r"\b3OPC\b", model, re.I):
        return "3OPC heuristic", "76", True
    if re.search(r"\b(?:4SR|4SRM|OPC\s*4SR)\b", model, re.I):
        return "4SR heuristic", "96", True
    if re.search(r"\b(?:6OPC|6SH|6SR)\b", model, re.I):
        return "6 inch heuristic", "146", True
    return "", "", False


def extract_title(card: BeautifulSoup) -> tuple[str, str]:
    title_node = card.select_one(".item-all-title .item-title span[itemprop='name']")
    link_node = card.select_one(".item-all-title .item-title[href]")
    if not title_node:
        title_node = card.select_one(".item-title span[itemprop='name']")
    if not link_node:
        link_node = card.select_one("a.item-title[href]")

    title = clean_text(title_node.get_text(" ", strip=True)) if title_node else ""
    href = link_node.get("href", "").strip() if link_node else ""

    if not title:
        image = card.select_one("img.item_img[title]")
        if image:
            title = clean_text(image.get("title", ""))

    if not href:
        image_link = card.select_one(".item-image a[href]")
        if image_link:
            href = image_link.get("href", "").strip()

    return title, urljoin(BASE_URL, href) if href else ""


def mi_desc_line(node: BeautifulSoup) -> tuple[str, str]:
    text = clean_text(node.get_text(" ", strip=True))
    span = node.select_one("span")
    span_text = clean_text(span.get_text(" ", strip=True)) if span else ""
    return text, span_text


def extract_power_kw(text: str) -> str:
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*к?вт\b", text, re.I)
    return match.group(1).replace(",", ".") if match else ""


def parse_specs_from_card(card: BeautifulSoup) -> tuple[dict[str, str], bool]:
    specs = {
        "max_head_m": "",
        "max_flow_original": "",
        "max_flow_l_min": "",
        "voltage": "",
        "power_kw": "",
    }

    spec_nodes = card.select(".description-must-icons .mi-desc")
    used_fallback = False

    if spec_nodes:
        for node in spec_nodes:
            line, span_text = mi_desc_line(node)
            if not line:
                continue

            if not specs["max_head_m"] and re.search(r"Макс\.\s*напор", line, re.I):
                raw = span_text or line
                match = re.search(r"(\d+(?:[.,]\d+)?)\s*м\b", raw, re.I)
                if match:
                    specs["max_head_m"] = match.group(1).replace(",", ".")

            if not specs["max_flow_original"] and re.search(r"Макс\.\s*производ", line, re.I):
                raw = span_text or line.split(":", 1)[-1].strip()
                specs["max_flow_original"] = raw
                specs["max_flow_l_min"] = normalize_flow_to_l_min(raw)

            if not specs["voltage"] and re.search(r"Напряжение", line, re.I):
                raw = span_text or line
                match = re.search(r"(\d{3})\s*[ВV]\b", raw, re.I)
                if match:
                    specs["voltage"] = match.group(1)

            if not specs["power_kw"] and re.search(r"Макс\.\s*мощность", line, re.I):
                raw = span_text or line
                specs["power_kw"] = extract_power_kw(raw)
    else:
        used_fallback = True
        fallback = clean_text(card.get_text(" ", strip=True))
        if not specs["max_head_m"]:
            match = re.search(r"Макс\.\s*напор:\s*(\d+(?:[.,]\d+)?)\s*м\b", fallback, re.I)
            if match:
                specs["max_head_m"] = match.group(1).replace(",", ".")
        if not specs["max_flow_original"]:
            match = re.search(r"Макс\.\s*производ:\s*([^\n]+)", fallback, re.I)
            if match:
                specs["max_flow_original"] = clean_text(match.group(1))
                specs["max_flow_l_min"] = normalize_flow_to_l_min(specs["max_flow_original"])
        if not specs["voltage"]:
            match = re.search(r"Напряжение(?:\s*сети)?\s*:\s*(\d{3})\s*[ВV]\b", fallback, re.I)
            if match:
                specs["voltage"] = match.group(1)
        if not specs["power_kw"]:
            match = re.search(r"Макс\.\s*мощность:\s*([^\n]+)", fallback, re.I)
            if match:
                specs["power_kw"] = extract_power_kw(match.group(1))

    return specs, used_fallback


def parse_card(card: BeautifulSoup) -> ProductRow:
    card_id = card.get("id", "").strip()
    title, source_url = extract_title(card)
    model = extract_model(title)
    specs, used_fallback = parse_specs_from_card(card)
    diameter_raw, diameter_mm, diameter_is_heuristic = extract_diameter_from_model(model)

    status = "ok"
    if not title or not model or not specs["max_head_m"] or not specs["max_flow_l_min"] or not specs["voltage"]:
        status = "missing_data"
    elif diameter_mm:
        if used_fallback or diameter_is_heuristic:
            status = "needs_check"
    else:
        status = "needs_check"

    return ProductRow(
        card_id=card_id,
        title=title,
        model=model,
        max_head_m=specs["max_head_m"],
        max_flow_original=specs["max_flow_original"],
        max_flow_l_min=specs["max_flow_l_min"],
        voltage=specs["voltage"],
        power_kw=specs["power_kw"],
        diameter_raw=diameter_raw,
        diameter_mm=diameter_mm,
        source_url=source_url,
        status=status,
    )


def extract_cards_from_listing(listing_html: str) -> list[ProductRow]:
    soup = BeautifulSoup(listing_html, "html.parser")
    rows: list[ProductRow] = []
    for card in soup.select(".catalog-item-card"):
        row = parse_card(card)
        if not row.title and not row.source_url:
            continue
        rows.append(row)
    return rows


def find_next_page_url(listing_html: str, current_page: int) -> Optional[str]:
    soup = BeautifulSoup(listing_html, "html.parser")
    target = str(current_page + 1)
    for link in soup.select("a[href]"):
        if clean_text(link.get_text(" ", strip=True)) == target:
            return urljoin(BASE_URL, link.get("href", "").strip())
    return None


def walk_catalog(session: requests.Session) -> list[ProductRow]:
    rows: list[ProductRow] = []
    seen_cards = set()
    page_url = START_URL
    for page in range(1, MAX_PAGES + 1):
        html = fetch(page_url, session)
        for row in extract_cards_from_listing(html):
            dedupe_key = row.source_url or row.card_id
            if not dedupe_key or dedupe_key in seen_cards:
                continue
            seen_cards.add(dedupe_key)
            rows.append(row)
        next_url = find_next_page_url(html, page)
        if not next_url or next_url == page_url:
            break
        page_url = next_url
    return rows


def write_csv(rows: Iterable[ProductRow], out_path: Path) -> None:
    empty_row = ProductRow("", "", "", "", "", "", "", "", "", "", "", "")
    fieldnames = list(asdict(empty_row).keys())
    with out_path.open("w", newline="", encoding="utf-8-sig") as file:
        writer = csv.DictWriter(file, fieldnames=fieldnames)
        writer.writeheader()
        for row in rows:
            writer.writerow(asdict(row))


def main() -> int:
    session = requests.Session()
    try:
        print("proxy enabled from local_proxy.txt" if PROXIES else "proxy disabled")
        rows = walk_catalog(session)
        write_csv(rows, OUT_CSV)

        for row in rows:
            if row.model == "3OPC1.8/47":
                print(f"CHECK 3OPC1.8/47: head={row.max_head_m}, flow={row.max_flow_original}, voltage={row.voltage}")
                break

        statuses: dict[str, int] = {}
        for row in rows:
            statuses[row.status] = statuses.get(row.status, 0) + 1

        print(json.dumps({"output": str(OUT_CSV), "rows": len(rows), "statuses": statuses}, ensure_ascii=False))
        return 0
    except Exception as exc:
        print(f"fatal: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
