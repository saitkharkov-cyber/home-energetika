#!/usr/bin/env python3
from __future__ import annotations

import csv
import re
import sys
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable, Optional
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup


BASE_URL = "https://official-sumoto.ru"
CATEGORY_PAGES = (
    "https://official-sumoto.ru/katalog/",
    "https://official-sumoto.ru/katalog/page_2a/",
    "https://official-sumoto.ru/katalog/page_3a/",
)
SCRIPT_DIR = Path(__file__).resolve().parent
REPO_ROOT = SCRIPT_DIR.parent
OUTPUT_CSV = REPO_ROOT / "official_sumoto_parsed_preview.csv"
DEBUG_HTML = REPO_ROOT / "official_sumoto_debug.html"
CATEGORY_DEBUG_HTML = REPO_ROOT / "official_sumoto_category_debug.html"
LINKS_DEBUG = REPO_ROOT / "official_sumoto_links_debug.txt"
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
    title: str
    model: str
    series: str
    max_head_m: str
    nominal_head_m: str
    max_flow_original: str
    max_flow_l_min: str
    nominal_flow_original: str
    nominal_flow_l_min: str
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


def clean_text(value: str) -> str:
    value = value.replace("\xad", "")
    value = value.replace("&shy;", "")
    value = value.replace("\xa0", " ")
    value = re.sub(r"\s+", " ", value)
    return value.strip()


def fetch(url: str, session: requests.Session) -> str:
    response = session.get(url, headers=HEADERS, timeout=30, proxies=PROXIES)
    response.raise_for_status()
    response.encoding = response.apparent_encoding or response.encoding or "utf-8"
    return response.text


def normalize_flow_to_l_min(text: str) -> str:
    value = clean_text(text)
    match = re.search(r"(\d+(?:[.,]\d+)?)\s*l\s*/\s*min\b", value, re.I)
    if match:
        return match.group(1).replace(",", ".")

    match = re.search(r"(\d+(?:[.,]\d+)?)\s*(?:м3\s*/\s*час|м\^3\s*/\s*час|м3\s*/\s*ч|м\^3\s*/\s*ч|м³\s*/\s*час)", value, re.I)
    if match:
        m3h = float(match.group(1).replace(",", "."))
        return f"{m3h * 1000 / 60:.2f}".rstrip("0").rstrip(".")

    return ""


def extract_model(title: str) -> str:
    title = clean_text(title)
    match = re.search(r"\b(?:SUMOTO|Sumoto)\s+(.+)$", title)
    if match:
        return clean_text(match.group(1))
    return title


def extract_diameter_from_raw(raw: str) -> tuple[str, str]:
    match = re.search(r"(\d+)\s*мм", raw, re.I)
    if match:
        mm = match.group(1)
        return raw, mm
    return raw, ""


def extract_diameter_from_model(model: str) -> tuple[str, str, bool]:
    model = clean_text(model).upper()
    if re.search(r"\b3OPC\b", model):
        return '3"', "76", True
    if re.search(r"\b4SRM?\b", model):
        return '4"', "100", True
    if re.search(r"\b6OPC\b|\b6SH\b", model):
        return '6"', "146", True
    return "", "", False


def parse_specs(card: BeautifulSoup) -> dict[str, str]:
    specs = {
        "title": "",
        "model": "",
        "series": "",
        "max_head_m": "",
        "nominal_head_m": "",
        "max_flow_original": "",
        "max_flow_l_min": "",
        "nominal_flow_original": "",
        "nominal_flow_l_min": "",
        "voltage": "",
        "power_kw": "",
        "diameter_raw": "",
        "diameter_mm": "",
    }

    content = card.select_one('.description-content-modern[itemprop="description"]')
    if not content:
        return specs

    for li in content.select("li"):
        line = clean_text(li.get_text(" ", strip=True))
        if not line or ":" not in line:
            continue

        label, value = [clean_text(part) for part in line.split(":", 1)]

        if label == "Наименование" and value:
            specs["model"] = value
        elif label == "Серия насоса" and value:
            specs["series"] = value
        elif label == "Максимальный напор" and value:
            match = re.search(r"(\d+(?:[.,]\d+)?)\s*м\b", value, re.I)
            if match:
                specs["max_head_m"] = match.group(1).replace(",", ".")
        elif label == "Номинальный напор" and value:
            match = re.search(r"(\d+(?:[.,]\d+)?)\s*м\b", value, re.I)
            if match:
                specs["nominal_head_m"] = match.group(1).replace(",", ".")
        elif label == "Максимальная производительность" and value:
            specs["max_flow_original"] = value
            specs["max_flow_l_min"] = normalize_flow_to_l_min(value)
        elif label == "Номинальная производительность" and value:
            specs["nominal_flow_original"] = value
            specs["nominal_flow_l_min"] = normalize_flow_to_l_min(value)
        elif label in {"Напряжение сети", "Напряжение"} and value:
            match = re.search(r"(\d{3})\s*[ВV]\b", value, re.I)
            if match:
                specs["voltage"] = match.group(1)
        elif label == "Мощность" and value:
            match = re.search(r"(\d+(?:[.,]\d+)?)\s*к?вт\b", value, re.I)
            if match:
                specs["power_kw"] = match.group(1).replace(",", ".")
        elif label == "Минимальный диаметр скважины" and value:
            specs["diameter_raw"] = value
            raw, mm = extract_diameter_from_raw(value)
            specs["diameter_raw"] = raw
            specs["diameter_mm"] = mm

    return specs


def find_title(card: BeautifulSoup) -> str:
    for selector in [
        "h1",
        "h2",
        ".product-title",
        ".item-title",
        "[itemprop='name']",
    ]:
        node = card.select_one(selector)
        if node:
            value = clean_text(node.get_text(" ", strip=True))
            if value:
                return value
    title = card.get("title", "")
    return clean_text(title)


def find_source_url(card: BeautifulSoup) -> str:
    for selector in [
        "a[href*='/product/']",
        "a[href*='/catalog/']",
        "a[href]",
    ]:
        node = card.select_one(selector)
        if node:
            href = node.get("href", "").strip()
            if href:
                return urljoin(BASE_URL, href)
    return ""


def parse_card(card: BeautifulSoup, source_url: str) -> ProductRow:
    title = find_title(card)
    specs = parse_specs(card)
    model = specs["model"] or extract_model(title)
    series = specs["series"]

    diameter_raw = specs["diameter_raw"]
    diameter_mm = specs["diameter_mm"]
    diameter_is_heuristic = False
    if not diameter_mm:
        diameter_raw, diameter_mm, diameter_is_heuristic = extract_diameter_from_model(model)

    status = "ok"
    if (
        not model
        or not specs["max_head_m"]
        or not specs["max_flow_l_min"]
        or not specs["voltage"]
        or not diameter_mm
    ):
        status = "missing_data"
    elif diameter_is_heuristic:
        status = "needs_check"

    return ProductRow(
        title=title,
        model=model,
        series=series,
        max_head_m=specs["max_head_m"],
        nominal_head_m=specs["nominal_head_m"],
        max_flow_original=specs["max_flow_original"],
        max_flow_l_min=specs["max_flow_l_min"],
        nominal_flow_original=specs["nominal_flow_original"],
        nominal_flow_l_min=specs["nominal_flow_l_min"],
        voltage=specs["voltage"],
        power_kw=specs["power_kw"],
        diameter_raw=diameter_raw,
        diameter_mm=diameter_mm,
        source_url=source_url,
        status=status,
    )


def is_product_url(url: str) -> bool:
    parsed = urlparse(url)
    path = parsed.path.lower()
    if parsed.query or parsed.fragment:
        return False
    if "/product/" not in path:
        return False
    return True


def is_sumoto_category_url(url: str) -> bool:
    parsed = urlparse(url)
    path = parsed.path.lower()
    return "sumoto" in path and "/product/" not in path


def is_pagination_href(href: str) -> bool:
    href_l = href.lower()
    return (
        "page=" in href_l
        or "pagen_" in href_l
        or "?pagen" in href_l
        or "/page/" in href_l
    )


def normalize_href(url: str) -> str:
    return url.split("#", 1)[0]


def is_skippable_href(href: str) -> bool:
    href_l = (href or "").strip().lower()
    if not href_l:
        return True
    return href_l.startswith(
        (
            "mailto:",
            "tel:",
            "javascript:",
            "#",
            "viber:",
            "whatsapp:",
            "tg:",
            "skype:",
        )
    )


def is_allowed_http_url(url: str) -> bool:
    parsed = urlparse(url)
    if parsed.scheme not in {"http", "https"}:
        return False
    if parsed.netloc not in {"official-sumoto.ru", "www.official-sumoto.ru"}:
        return False
    if re.search(r"\.(?:jpg|jpeg|png|gif|webp|svg|pdf|css|js|zip|rar)(?:$|\?)", parsed.path, re.I):
        return False
    return True


def extract_pagination_urls(category_html: str, current_url: str) -> list[str]:
    soup = BeautifulSoup(category_html, "html.parser")
    urls: list[str] = []
    seen: set[str] = set()

    blocks = soup.find_all(
        lambda tag: tag.name in {"div", "nav", "ul", "ol", "section"}
        and any(
            cls and any(key in cls.lower() for key in ("pagination", "pager", "pages", "nav"))
            for cls in tag.get("class", [])
        )
    )

    candidates = []
    for block in blocks:
        candidates.extend(block.select("a[href]"))

    if not candidates:
        candidates = soup.select("a[href]")

    for a in candidates:
        href = a.get("href", "").strip()
        if is_skippable_href(href):
            continue
        if not is_pagination_href(href):
            text = clean_text(a.get_text(" ", strip=True))
            if not text.isdigit():
                continue
        full = normalize_href(urljoin(current_url, href))
        if not is_allowed_http_url(full):
            continue
        if full not in seen:
            seen.add(full)
            urls.append(full)

    return urls


def link_relevant_to_sumoto(href: str, text: str, nearby: str) -> bool:
    blob = f"{text} {nearby} {href}".lower()
    return any(token in blob for token in ("sumoto", "насос"))


def discover_product_urls(session: requests.Session) -> tuple[list[str], int, list[str], Optional[str]]:
    seen_urls: set[str] = set()
    product_urls: list[str] = []
    visited_pages: set[str] = set()
    all_hrefs: list[str] = []
    category_html: Optional[str] = None

    for page_url in CATEGORY_PAGES:
        html = fetch(page_url, session)
        if category_html is None:
            category_html = html
        soup = BeautifulSoup(html, "html.parser")

        page_candidates = extract_pagination_urls(html, page_url)
        for candidate in page_candidates:
            if candidate not in visited_pages and candidate not in CATEGORY_PAGES:
                visited_pages.add(candidate)
        for a in soup.select("a[href]"):
            href = a.get("href", "").strip()
            text = clean_text(a.get_text(" ", strip=True))
            nearby = clean_text(a.parent.get_text(" ", strip=True)) if a.parent else ""
            if is_skippable_href(href):
                continue
            all_hrefs.append(href)
            full = normalize_href(urljoin(page_url, href))
            if not is_allowed_http_url(full):
                continue
            if not is_product_url(full):
                continue
            if not link_relevant_to_sumoto(href, text, nearby):
                continue
            if full not in seen_urls:
                seen_urls.add(full)
                product_urls.append(full)

    return product_urls, len(CATEGORY_PAGES), all_hrefs, category_html


def write_csv(rows: Iterable[ProductRow], out_path: Path) -> None:
    fieldnames = list(asdict(ProductRow("", "", "", "", "", "", "", "", "", "", "", "", "", "", "")).keys())
    with out_path.open("w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        for row in rows:
            writer.writerow(asdict(row))


def main() -> int:
    session = requests.Session()
    try:
        print("proxy enabled from local_proxy.txt" if PROXIES else "proxy disabled")

        product_urls, category_pages_found, all_hrefs, category_html = discover_product_urls(session)
        if not product_urls:
            if category_html is not None:
                CATEGORY_DEBUG_HTML.write_text(category_html, encoding="utf-8")
            LINKS_DEBUG.write_text("\n".join(all_hrefs), encoding="utf-8")
            print("links not found")
            print(f"debug html: {CATEGORY_DEBUG_HTML}")
            return 1

        rows: list[ProductRow] = []
        for url in product_urls:
            try:
                html = fetch(url, session)
                card = BeautifulSoup(html, "html.parser")
                rows.append(parse_card(card, url))
            except Exception:
                rows.append(
                    ProductRow(
                        title="",
                        model="",
                        series="",
                        max_head_m="",
                        nominal_head_m="",
                        max_flow_original="",
                        max_flow_l_min="",
                        nominal_flow_original="",
                        nominal_flow_l_min="",
                        voltage="",
                        power_kw="",
                        diameter_raw="",
                        diameter_mm="",
                        source_url=url,
                        status="missing_data",
                    )
                )

        write_csv(rows, OUTPUT_CSV)

        ok = sum(1 for row in rows if row.status == "ok")
        needs_check = sum(1 for row in rows if row.status == "needs_check")
        missing_data = sum(1 for row in rows if row.status == "missing_data")

        if len(product_urls) < 50:
            if category_html is not None:
                CATEGORY_DEBUG_HTML.write_text(category_html, encoding="utf-8")
            LINKS_DEBUG.write_text("\n".join(all_hrefs), encoding="utf-8")

        print(f"category pages found: {category_pages_found}")
        print(f"product urls found: {len(product_urls)}")
        print(f"rows written: {len(rows)}")
        print(f"ok: {ok}")
        print(f"needs_check: {needs_check}")
        print(f"missing_data: {missing_data}")
        return 0
    except Exception as exc:
        print(f"fatal: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
