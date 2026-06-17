#!/usr/bin/env python3
from __future__ import annotations

import csv
import json
import re
import sys
import os
from dataclasses import dataclass, asdict
from pathlib import Path
from typing import Iterable, Optional

import requests
from bs4 import BeautifulSoup


BASE_URL = "https://nasosymarket.ru"
START_URL = f"{BASE_URL}/catalog/sumoto/"
MAX_PAGES = 14
SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent
OUT_CSV = REPO_ROOT / "sumoto_parsed_preview.csv"

PROXY_URL = os.getenv("SUMOTO_PROXY")
PROXIES = None
if PROXY_URL:
    PROXIES = {
        "http": PROXY_URL,
        "https": PROXY_URL,
    }

HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/125.0.0.0 Safari/537.36"
    ),
    "Accept-Language": "ru-RU,ru;q=0.9,en;q=0.8",
}


@dataclass
class ProductRow:
    title: str
    model: str
    max_head_m: str
    max_flow_original: str
    max_flow_l_min: str
    voltage: str
    diameter_raw: str
    diameter_mm: str
    source_url: str
    status: str


def fetch(url: str, session: requests.Session) -> str:
    r = session.get(url, headers=HEADERS, timeout=30, proxies=PROXIES)
    r.raise_for_status()
    r.encoding = r.apparent_encoding or r.encoding or "utf-8"
    return r.text


def norm_space(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip()


def parse_float(text: str) -> Optional[float]:
    m = re.search(r"(\d+(?:[.,]\d+)?)", text)
    if not m:
        return None
    return float(m.group(1).replace(",", "."))


def extract_model(title: str) -> str:
    title = norm_space(title)
    m = re.search(r"\b(?:SUMOTO|Sumoto)\s+(.+)$", title)
    if m:
        return m.group(1).strip()
    return title


def extract_diameter(title: str, text: str) -> tuple[str, str, bool]:
    blob = f"{title} {text}"
    if re.search(r"100\s*мм|100\s*mm", blob, re.I):
        return "100 мм", "100", True
    if re.search(r"96\s*мм|96\s*mm", blob, re.I):
        return "96 мм", "96", True
    if re.search(r"4\s*[\"″]|4\s*дюй", blob, re.I):
        return '4"', "96", False
    if re.search(r"3\s*[\"″]|3\s*дюй", blob, re.I):
        return '3"', "76", False
    return "", "", False


def extract_specs(text: str) -> dict[str, str]:
    specs = {
        "max_head_m": "",
        "max_flow_original": "",
        "max_flow_l_min": "",
        "voltage": "",
    }
    lines = [norm_space(x) for x in text.splitlines() if norm_space(x)]
    joined = " | ".join(lines)

    for line in lines:
        if not specs["max_head_m"] and re.search(r"напор", line, re.I):
            if m := re.search(r"(\d+(?:[.,]\d+)?)\s*м\b", line):
                specs["max_head_m"] = m.group(1).replace(",", ".")
        if not specs["voltage"] and re.search(r"напряж", line, re.I):
            if m := re.search(r"(\d{3})\s*в\b", line, re.I):
                specs["voltage"] = m.group(1)
        if not specs["max_flow_original"] and re.search(r"производ", line, re.I):
            specs["max_flow_original"] = line
            if m := re.search(r"\((\d+(?:[.,]\d+)?)\s*л/мин\)", line, re.I):
                specs["max_flow_l_min"] = m.group(1).replace(",", ".")
            elif m := re.search(r"(\d+(?:[.,]\d+)?)\s*л/мин\b", line, re.I):
                specs["max_flow_l_min"] = m.group(1).replace(",", ".")
            elif m := re.search(r"(\d+(?:[.,]\d+)?)\s*м³/ч\b", line, re.I):
                m3h = float(m.group(1).replace(",", "."))
                specs["max_flow_l_min"] = f"{m3h * 1000 / 60:.2f}".rstrip("0").rstrip(".")

    if not specs["max_head_m"]:
        m = re.search(r"Макс\.\s*напор:\s*(\d+(?:[.,]\d+)?)\s*м", joined, re.I)
        if m:
            specs["max_head_m"] = m.group(1).replace(",", ".")
    if not specs["voltage"]:
        m = re.search(r"Напряжение:\s*(\d{3})\s*В", joined, re.I)
        if m:
            specs["voltage"] = m.group(1)
    return specs


def parse_product(url: str, session: requests.Session) -> ProductRow:
    html = fetch(url, session)
    soup = BeautifulSoup(html, "html.parser")
    text = soup.get_text("\n", strip=True)

    title = ""
    for tag in ("h1", "h2"):
        node = soup.find(tag)
        if node and norm_space(node.get_text(" ", strip=True)):
            title = norm_space(node.get_text(" ", strip=True))
            break
    if not title:
        title = norm_space(soup.title.get_text(" ", strip=True)) if soup.title else ""

    specs = extract_specs(text)
    diameter_raw, diameter_mm, diameter_is_heuristic = extract_diameter(title, text)

    model = extract_model(title)
    max_head_m = specs["max_head_m"]
    max_flow_original = specs["max_flow_original"]
    max_flow_l_min = specs["max_flow_l_min"]
    voltage = specs["voltage"]

    status = "ok"
    if not title or not model or not max_head_m or not max_flow_original or not voltage or not diameter_mm:
        status = "missing_data"
    if diameter_is_heuristic or (diameter_mm and not diameter_raw):
        if status == "ok":
            status = "needs_check"
        elif status == "missing_data":
            status = "needs_check"

    if max_flow_original and not max_flow_l_min:
        m = re.search(r"(\d+(?:[.,]\d+)?)\s*м³/ч", max_flow_original, re.I)
        if m:
            m3h = float(m.group(1).replace(",", "."))
            max_flow_l_min = f"{m3h * 1000 / 60:.2f}".rstrip("0").rstrip(".")

    if not diameter_raw and diameter_mm:
        diameter_raw = diameter_mm + " мм"

    return ProductRow(
        title=title,
        model=model,
        max_head_m=max_head_m,
        max_flow_original=max_flow_original,
        max_flow_l_min=max_flow_l_min,
        voltage=voltage,
        diameter_raw=diameter_raw,
        diameter_mm=diameter_mm,
        source_url=url,
        status=status,
    )


def extract_product_links(listing_html: str) -> list[str]:
    soup = BeautifulSoup(listing_html, "html.parser")
    links: list[str] = []
    for a in soup.select("a[href]"):
        href = a.get("href", "")
        if "/catalog/sumoto/" in href and href.rstrip("/") != "/catalog/sumoto":
            full = href if href.startswith("http") else BASE_URL + href
            if full not in links:
                links.append(full)
    return links


def find_next_page_url(listing_html: str, current_page: int) -> Optional[str]:
    soup = BeautifulSoup(listing_html, "html.parser")
    next_page = current_page + 1
    target = str(next_page)
    for a in soup.select("a[href]"):
        if norm_space(a.get_text(" ", strip=True)) == target:
            href = a.get("href", "")
            return href if href.startswith("http") else BASE_URL + href
    return None


def walk_catalog(session: requests.Session) -> list[str]:
    urls: list[str] = []
    seen = set()
    page_url = START_URL
    for page in range(1, MAX_PAGES + 1):
        html = fetch(page_url, session)
        for url in extract_product_links(html):
            if url not in seen:
                seen.add(url)
                urls.append(url)
        next_url = find_next_page_url(html, page)
        if not next_url or next_url == page_url:
            break
        page_url = next_url
    return urls


def write_csv(rows: Iterable[ProductRow], out_path: Path) -> None:
    fieldnames = list(asdict(ProductRow("", "", "", "", "", "", "", "", "", "")).keys())
    with out_path.open("w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        for row in rows:
            writer.writerow(asdict(row))


def main() -> int:
    session = requests.Session()
    try:
        print("proxy enabled" if PROXIES else "proxy disabled")
        product_urls = walk_catalog(session)
        rows = []
        for url in product_urls:
            try:
                rows.append(parse_product(url, session))
            except Exception as exc:
                rows.append(
                    ProductRow(
                        title="",
                        model="",
                        max_head_m="",
                        max_flow_original="",
                        max_flow_l_min="",
                        voltage="",
                        diameter_raw="",
                        diameter_mm="",
                        source_url=url,
                        status="not_found",
                    )
                )
                print(f"failed: {url} -> {exc}", file=sys.stderr)
        write_csv(rows, OUT_CSV)
        print(json.dumps({"output": str(OUT_CSV), "rows": len(rows), "catalog_urls": len(product_urls)}, ensure_ascii=False))
        return 0
    except Exception as exc:
        print(f"fatal: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
