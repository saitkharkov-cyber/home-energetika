#!/usr/bin/env python3
"""Specialized read-only parser for the Vinko catalog source."""

from __future__ import annotations

import argparse
import csv
import json
import os
import re
import sys
import tempfile
import time
from dataclasses import asdict, dataclass
from datetime import datetime
from decimal import Decimal, InvalidOperation, ROUND_HALF_UP
from pathlib import Path
from typing import Iterable
from urllib.parse import urljoin, urlparse

try:
    import requests
    from bs4 import BeautifulSoup, Tag
except ImportError as exc:
    raise ImportError(
        "Missing parser dependency. Install 'requests' and 'beautifulsoup4'."
    ) from exc


SOURCE_NAME = "Vinko"
EXIT_OK = 0
EXIT_VALIDATION = 1
EXIT_OUTPUT_CONFLICT = 2
EXIT_SOURCE_FAILURE = 3
EXIT_PARTIAL = 4
EXIT_INTERNAL = 5

USER_AGENT = "home-energetika-vinko-parser/1.0"
RETRYABLE_STATUS_CODES = {429, 500, 502, 503, 504}
STATUS_NAMES = (
    "ok",
    "needs_review",
    "missing_required",
    "parse_error",
    "rejected",
)
REQUIRED_REASON_CODES = {
    "missing_title",
    "missing_model",
    "missing_source_url",
    "missing_head",
    "missing_flow",
}


class ParserArgumentParser(argparse.ArgumentParser):
    """Return the project-defined validation exit code for CLI errors."""

    def error(self, message: str) -> None:
        self.exit(EXIT_VALIDATION, f"{self.prog}: error: {message}\n")


class ValidationError(Exception):
    """Raised for validated runtime inputs that argparse cannot express."""


class SourceFailure(Exception):
    """Raised after a page has exhausted permitted network retries."""


class ListingStructureError(Exception):
    """Raised when a page contains no recognizable catalog cards."""


class CardParseError(Exception):
    """Raised for a recoverable parse failure of one card."""


@dataclass(frozen=True)
class RunConfig:
    output: Path
    base_url: str
    pages: int
    proxy_file: Path | None
    timeout: float
    retries: int
    delay: float
    overwrite: bool


@dataclass
class VinkoRecord:
    source_name: str
    source_base_url: str
    source_page_url: str
    source_product_url: str
    source_card_id: str
    source_title: str
    source_model: str
    source_max_head_label: str
    source_max_head: str
    normalized_max_head_m: str
    source_max_flow_label: str
    source_max_flow: str
    normalized_max_flow_l_min: str
    source_voltage_label: str
    source_voltage: str
    normalized_voltage: str
    source_power_label: str
    source_power: str
    normalized_power_kw: str
    source_diameter_evidence: str
    normalized_diameter_mm: str
    diameter_evidence_type: str
    record_status: str
    review_reason: str


@dataclass(frozen=True)
class LabelValue:
    label: str
    value: str


def positive_int(name: str, minimum: int, maximum: int):
    def parse(value: str) -> int:
        try:
            parsed = int(value)
        except ValueError as exc:
            raise argparse.ArgumentTypeError(
                f"{name} must be an integer"
            ) from exc
        if not minimum <= parsed <= maximum:
            raise argparse.ArgumentTypeError(
                f"{name} must be between {minimum} and {maximum}"
            )
        return parsed

    return parse


def bounded_float(name: str, minimum: float, maximum: float):
    def parse(value: str) -> float:
        try:
            parsed = float(value)
        except ValueError as exc:
            raise argparse.ArgumentTypeError(
                f"{name} must be a number"
            ) from exc
        if not minimum <= parsed <= maximum:
            raise argparse.ArgumentTypeError(
                f"{name} must be between {minimum} and {maximum}"
            )
        return parsed

    return parse


def http_url(value: str) -> str:
    parsed = urlparse(value)
    if parsed.scheme not in {"http", "https"} or not parsed.netloc:
        raise argparse.ArgumentTypeError(
            "URL must use http or https and include a host"
        )
    return value


def build_parser() -> ParserArgumentParser:
    parser = ParserArgumentParser(
        description="Read-only specialized parser for the Vinko catalog source."
    )
    parser.add_argument("--output", required=True, type=Path, metavar="PATH")
    parser.add_argument("--base-url", required=True, type=http_url, metavar="URL")
    parser.add_argument(
        "--pages",
        default=14,
        type=positive_int("pages", 1, 100),
        metavar="N",
    )
    parser.add_argument("--proxy-file", type=Path, metavar="PATH")
    parser.add_argument(
        "--timeout",
        default=30.0,
        type=bounded_float("timeout", 1.0, 120.0),
        metavar="SECONDS",
    )
    parser.add_argument(
        "--retries",
        default=2,
        type=positive_int("retries", 0, 5),
        metavar="N",
    )
    parser.add_argument(
        "--delay",
        default=1.0,
        type=bounded_float("delay", 0.0, 30.0),
        metavar="SECONDS",
    )
    parser.add_argument("--overwrite", action="store_true")
    return parser


def clean_text(value: str) -> str:
    value = value.replace("\xad", "").replace("&shy;", "").replace("\xa0", " ")
    return re.sub(r"\s+", " ", value).strip()


def format_decimal(value: Decimal) -> str:
    normalized = value.normalize()
    text = format(normalized, "f")
    return text.rstrip("0").rstrip(".") if "." in text else text


def parse_decimal(token: str) -> Decimal | None:
    try:
        return Decimal(token.replace(",", "."))
    except (InvalidOperation, AttributeError):
        return None


def contains_range_or_upper_bound(value: str) -> bool:
    return bool(
        re.search(r"\bдо\s*\d", value, re.IGNORECASE)
        or re.search(r"\d\s*[-–—]\s*\d", value)
    )


def normalize_head(value: str) -> tuple[str, set[str]]:
    text = clean_text(value)
    if not text:
        return "", set()
    if contains_range_or_upper_bound(text):
        return "", {"unsupported_unit"}
    match = re.fullmatch(r"(\d+(?:[.,]\d+)?)\s*м", text, re.IGNORECASE)
    if not match:
        return "", {"unsupported_unit"}
    parsed = parse_decimal(match.group(1))
    return (format_decimal(parsed), set()) if parsed is not None else ("", {"unsupported_unit"})


def normalize_flow(value: str) -> tuple[str, set[str]]:
    text = clean_text(value)
    if not text:
        return "", set()
    if contains_range_or_upper_bound(text):
        return "", {"unsupported_unit"}
    liters = re.fullmatch(
        r"(\d+(?:[.,]\d+)?)\s*(?:л|l)\s*/\s*мин(?:ут[аы])?",
        text,
        re.IGNORECASE,
    )
    if liters:
        parsed = parse_decimal(liters.group(1))
        return (format_decimal(parsed), set()) if parsed is not None else ("", {"unsupported_unit"})
    cubic = re.fullmatch(
        r"(\d+(?:[.,]\d+)?)\s*(?:м3|м\^3|м³)\s*/\s*ч(?:ас)?",
        text,
        re.IGNORECASE,
    )
    if cubic:
        parsed = parse_decimal(cubic.group(1))
        if parsed is not None:
            return format_decimal((parsed * Decimal(1000) / Decimal(60)).quantize(Decimal("1"), rounding=ROUND_HALF_UP)), set()
    return "", {"unsupported_unit"}


def normalize_voltage(value: str) -> tuple[str, set[str]]:
    text = clean_text(value)
    if not text:
        return "", set()
    if re.search(r"\d{3}\s*/\s*\d{3}|\d\s*[-–—]\s*\d", text):
        return "", {"ambiguous_voltage"}
    match = re.fullmatch(r"(\d{3})\s*[ВV]", text, re.IGNORECASE)
    if not match:
        return "", {"ambiguous_voltage"}
    return match.group(1), set()


def normalize_power(label: str, value: str) -> tuple[str, set[str]]:
    text = clean_text(value)
    if not text:
        return "", set()
    reasons: set[str] = set()
    context = clean_text(label).lower()
    if not re.search(r"(?:макс(?:имальная)?\.?\s+)?мощност[ьи](?:\s+двигател[ья])?|motor\s+power", context, re.I):
        reasons.add("unverified_power_semantics")
    if re.search(r"\b(?:p1|p2|input|output)\b", context, re.I):
        reasons.add("unverified_power_semantics")
    if contains_range_or_upper_bound(text):
        return "", reasons | {"unsupported_unit"}
    kilowatts = re.fullmatch(r"(\d+(?:[.,]\d+)?)\s*к(?:вт|w)", text, re.I)
    if kilowatts:
        parsed = parse_decimal(kilowatts.group(1))
        return (format_decimal(parsed), reasons) if parsed is not None else ("", reasons | {"unsupported_unit"})
    watts = re.fullmatch(r"(\d+(?:[.,]\d+)?)\s*(?:вт|w)", text, re.I)
    if watts:
        parsed = parse_decimal(watts.group(1))
        if parsed is not None:
            return format_decimal(parsed / Decimal(1000)), reasons
    return "", reasons | {"unsupported_unit"}


def normalize_physical_diameter(value: str) -> tuple[str, set[str]]:
    text = clean_text(value)
    if contains_range_or_upper_bound(text):
        return "", {"unsupported_unit"}
    millimeters = re.fullmatch(r"(\d+(?:[.,]\d+)?)\s*мм", text, re.I)
    if millimeters:
        parsed = parse_decimal(millimeters.group(1))
        return (format_decimal(parsed), set()) if parsed is not None else ("", {"unsupported_unit"})
    inches = re.fullmatch(r"(\d+(?:[.,]\d+)?)\s*(?:дюйм(?:а|ов)?|\")", text, re.I)
    if inches:
        parsed = parse_decimal(inches.group(1))
        if parsed is not None:
            return format_decimal(parsed * Decimal("25.4")), set()
    return "", {"unsupported_unit"}


def extract_model(title: str) -> str:
    model = clean_text(title)
    prefixes = (
        r"^Скважинный\s+погружной\s+насос\s+",
        r"^Погружной\s+скважинный\s+насос\s+",
        r"^Скважинный\s+насос\s+",
        r"^Погружной\s+насос\s+",
        r"^Насос\s+",
        r"^Скважинные\s+насосы\s+",
    )
    for prefix in prefixes:
        model = re.sub(prefix, "", model, flags=re.IGNORECASE)
    match = re.search(r"\bVINKO\s+(.+)$", model, re.IGNORECASE)
    return clean_text(match.group(1)) if match else model


def extract_title_and_url(card: Tag, page_url: str) -> tuple[str, str]:
    title_node = card.select_one(".item-all-title .item-title span[itemprop='name']")
    link_node = card.select_one(".item-all-title .item-title[href]")
    if title_node is None:
        title_node = card.select_one(".item-title span[itemprop='name']")
    if link_node is None:
        link_node = card.select_one("a.item-title[href]")
    title = clean_text(title_node.get_text(" ", strip=True)) if title_node else ""
    href = clean_text(str(link_node.get("href", ""))) if link_node else ""
    if not title:
        image = card.select_one("img.item_img[title]")
        title = clean_text(str(image.get("title", ""))) if image else ""
    if not href:
        image_link = card.select_one(".item-image a[href]")
        href = clean_text(str(image_link.get("href", ""))) if image_link else ""
    return title, urljoin(page_url, href) if href else ""


def label_value_from_node(node: Tag) -> LabelValue:
    line = clean_text(node.get_text(" ", strip=True))
    span = node.select_one("span")
    span_text = clean_text(span.get_text(" ", strip=True)) if span else ""
    if span_text:
        label = clean_text(line.replace(span_text, "", 1).rstrip(":"))
        return LabelValue(label or line, span_text)
    if ":" in line:
        label, value = line.split(":", 1)
        return LabelValue(clean_text(label), clean_text(value))
    return LabelValue(line, "")


def find_candidates(card: Tag, pattern: str) -> list[LabelValue]:
    candidates: list[LabelValue] = []
    for node in card.select(".description-must-icons .mi-desc"):
        candidate = label_value_from_node(node)
        if re.search(pattern, candidate.label, re.IGNORECASE):
            candidates.append(candidate)
    return candidates


def choose_candidate(candidates: list[LabelValue]) -> tuple[LabelValue, set[str]]:
    if not candidates:
        return LabelValue("", ""), set()

    def join_unique(values: Iterable[str]) -> str:
        seen: set[str] = set()
        result: list[str] = []
        for value in values:
            cleaned = clean_text(value)
            if cleaned and cleaned not in seen:
                seen.add(cleaned)
                result.append(cleaned)
        return " | ".join(result)

    label = join_unique(candidate.label for candidate in candidates)
    value = join_unique(candidate.value for candidate in candidates)
    values = {
        clean_text(candidate.value)
        for candidate in candidates
        if clean_text(candidate.value)
    }
    reasons = {"conflicting_values"} if len(values) > 1 else set()
    return LabelValue(label, value), reasons


def extract_diameter(card: Tag, model: str) -> tuple[str, str, str, set[str]]:
    diameter_nodes = find_candidates(card, r"диаметр")
    candidate, reasons = choose_candidate(diameter_nodes)
    if candidate.value:
        if "conflicting_values" in reasons:
            return candidate.value, "", "unknown", reasons
        label = candidate.label.lower()
        if re.search(r"подключ|соединен|резьб", label):
            return candidate.value, "", "connection_size", reasons
        if re.search(r"насос|корпус|наружн", label):
            normalized, normalized_reasons = normalize_physical_diameter(candidate.value)
            return (
                candidate.value,
                normalized,
                "physical_value",
                reasons | normalized_reasons,
            )
        return candidate.value, "", "unknown", reasons
    series_match = re.search(r"\b([346])STm?\b", model, re.IGNORECASE)
    if series_match:
        return series_match.group(0), "", "series_size", {"series_diameter_only"}
    return "", "", "unknown", reasons


def blank_record(base_url: str, page_url: str) -> VinkoRecord:
    return VinkoRecord(
        source_name=SOURCE_NAME,
        source_base_url=base_url,
        source_page_url=page_url,
        source_product_url="",
        source_card_id="",
        source_title="",
        source_model="",
        source_max_head_label="",
        source_max_head="",
        normalized_max_head_m="",
        source_max_flow_label="",
        source_max_flow="",
        normalized_max_flow_l_min="",
        source_voltage_label="",
        source_voltage="",
        normalized_voltage="",
        source_power_label="",
        source_power="",
        normalized_power_kw="",
        source_diameter_evidence="",
        normalized_diameter_mm="",
        diameter_evidence_type="unknown",
        record_status="parse_error",
        review_reason="unexpected_card_error",
    )


def finalize_status(record: VinkoRecord, reasons: set[str]) -> VinkoRecord:
    if not record.source_title:
        reasons.add("missing_title")
    if not record.source_model:
        reasons.add("missing_model")
    if not record.source_product_url:
        reasons.add("missing_source_url")
    if not record.normalized_max_head_m:
        reasons.add("missing_head")
    if not record.normalized_max_flow_l_min:
        reasons.add("missing_flow")

    if "unexpected_card_error" in reasons:
        status = "parse_error"
    elif "conflicting_values" in reasons:
        status = "rejected"
    elif reasons & REQUIRED_REASON_CODES:
        status = "missing_required"
    elif reasons:
        status = "needs_review"
    else:
        status = "ok"

    record.record_status = status
    record.review_reason = "" if status == "ok" else ";".join(sorted(reasons))
    return record


def parse_card(card: Tag, base_url: str, page_url: str) -> VinkoRecord:
    record = blank_record(base_url, page_url)
    try:
        record.source_card_id = clean_text(str(card.get("id", "")))
        record.source_title, record.source_product_url = extract_title_and_url(
            card, page_url
        )
        record.source_model = extract_model(record.source_title)

        if not card.select(".description-must-icons .mi-desc"):
            raise CardParseError("missing specification nodes")

        head, head_reasons = choose_candidate(
            find_candidates(card, r"макс\.\s*напор")
        )
        flow, flow_reasons = choose_candidate(
            find_candidates(card, r"макс\.\s*производ")
        )
        voltage, voltage_reasons = choose_candidate(
            find_candidates(card, r"напряжен")
        )
        power, power_reasons = choose_candidate(
            find_candidates(card, r"мощност|\bp1\b|\bp2\b")
        )

        record.source_max_head_label = head.label
        record.source_max_head = head.value
        if "conflicting_values" in head_reasons:
            record.normalized_max_head_m, normalized_head_reasons = "", set()
        else:
            record.normalized_max_head_m, normalized_head_reasons = normalize_head(
                head.value
            )
        record.source_max_flow_label = flow.label
        record.source_max_flow = flow.value
        if "conflicting_values" in flow_reasons:
            record.normalized_max_flow_l_min, normalized_flow_reasons = "", set()
        else:
            record.normalized_max_flow_l_min, normalized_flow_reasons = normalize_flow(
                flow.value
            )
        record.source_voltage_label = voltage.label
        record.source_voltage = voltage.value
        if "conflicting_values" in voltage_reasons:
            record.normalized_voltage, normalized_voltage_reasons = "", set()
        else:
            record.normalized_voltage, normalized_voltage_reasons = normalize_voltage(
                voltage.value
            )
        record.source_power_label = power.label
        record.source_power = power.value
        if "conflicting_values" in power_reasons:
            record.normalized_power_kw, normalized_power_reasons = "", set()
        else:
            record.normalized_power_kw, normalized_power_reasons = normalize_power(
                power.label, power.value
            )
        (
            record.source_diameter_evidence,
            record.normalized_diameter_mm,
            record.diameter_evidence_type,
            diameter_reasons,
        ) = extract_diameter(card, record.source_model)

        reasons = (
            head_reasons
            | flow_reasons
            | voltage_reasons
            | power_reasons
            | normalized_head_reasons
            | normalized_flow_reasons
            | normalized_voltage_reasons
            | normalized_power_reasons
            | diameter_reasons
        )
        return finalize_status(record, reasons)
    except Exception:
        return record


def extract_cards(listing_html: str) -> list[Tag]:
    soup = BeautifulSoup(listing_html, "html.parser")
    cards = list(soup.select(".catalog-item-card"))
    if not cards:
        raise ListingStructureError("html_structure_changed")
    return cards


def find_next_page_url(
    listing_html: str, current_page_url: str, page_number: int
) -> str | None:
    soup = BeautifulSoup(listing_html, "html.parser")
    rel_next = soup.select_one("a[rel='next'][href]")
    if rel_next:
        return urljoin(current_page_url, str(rel_next.get("href", "")))
    expected_label = str(page_number + 1)
    for link in soup.select("a[href]"):
        if clean_text(link.get_text(" ", strip=True)) == expected_label:
            return urljoin(current_page_url, str(link.get("href", "")))
    return None


def record_dedupe_key(record: VinkoRecord) -> tuple[str, ...]:
    if record.source_product_url:
        return ("url", record.source_product_url)
    if record.source_card_id:
        return ("card", record.source_card_id)
    return ("title-model", record.source_title, record.source_model)


def fetch_page(
    session: requests.Session,
    url: str,
    config: RunConfig,
    proxies: dict[str, str] | None,
) -> str:
    last_error: Exception | None = None
    for attempt in range(config.retries + 1):
        try:
            response = session.get(
                url,
                headers={"User-Agent": USER_AGENT, "Accept-Language": "ru-RU,ru;q=0.9"},
                timeout=config.timeout,
                proxies=proxies,
            )
            if response.status_code in RETRYABLE_STATUS_CODES:
                raise requests.HTTPError(
                    f"retryable HTTP {response.status_code}", response=response
                )
            response.raise_for_status()
            response.encoding = response.apparent_encoding or response.encoding or "utf-8"
            return response.text
        except (requests.ConnectionError, requests.Timeout, requests.HTTPError) as exc:
            last_error = exc
            response = getattr(exc, "response", None)
            retryable = isinstance(
                exc, (requests.ConnectionError, requests.Timeout)
            ) or (response is not None and response.status_code in RETRYABLE_STATUS_CODES)
            if not retryable or attempt >= config.retries:
                break
            time.sleep(config.delay * (2**attempt))
        except requests.RequestException as exc:
            last_error = exc
            break
    raise SourceFailure(str(last_error) if last_error else "network failure")


def load_proxy(proxy_file: Path | None) -> dict[str, str] | None:
    if proxy_file is None:
        return None
    if not proxy_file.exists() or not proxy_file.is_file():
        raise ValidationError("proxy file must exist and be a regular file")
    lines = proxy_file.read_text(encoding="utf-8").splitlines()
    if len(lines) != 1 or not lines[0].strip():
        raise ValidationError("proxy file must contain exactly one non-empty line")
    proxy_url = lines[0].strip()
    parsed = urlparse(proxy_url)
    if parsed.scheme not in {"http", "https"} or not parsed.netloc:
        raise ValidationError("proxy file must contain an http or https URL")
    return {"http": proxy_url, "https": proxy_url}


def validate_config(args: argparse.Namespace) -> RunConfig:
    output = args.output.expanduser()
    if output.exists() and output.is_dir():
        raise ValidationError("output path must not be a directory")
    if not output.parent.is_dir():
        raise ValidationError("output parent directory must already exist")
    if output.exists() and not args.overwrite:
        raise FileExistsError(output)
    return RunConfig(
        output=output,
        base_url=args.base_url,
        pages=args.pages,
        proxy_file=args.proxy_file.expanduser() if args.proxy_file else None,
        timeout=args.timeout,
        retries=args.retries,
        delay=args.delay,
        overwrite=args.overwrite,
    )


def write_csv_atomic(rows: Iterable[VinkoRecord], output: Path) -> None:
    temp_path: Path | None = None
    try:
        with tempfile.NamedTemporaryFile(
            mode="w",
            newline="",
            encoding="utf-8-sig",
            dir=output.parent,
            prefix=f".{output.name}.",
            suffix=".tmp",
            delete=False,
        ) as file:
            temp_path = Path(file.name)
            fieldnames = list(VinkoRecord.__dataclass_fields__)
            writer = csv.DictWriter(
                file,
                fieldnames=fieldnames,
                delimiter=",",
                quoting=csv.QUOTE_MINIMAL,
                lineterminator="\n",
            )
            writer.writeheader()
            for row in rows:
                writer.writerow(asdict(row))
            file.flush()
            os.fsync(file.fileno())
        os.replace(temp_path, output)
    except Exception:
        if temp_path is not None:
            temp_path.unlink(missing_ok=True)
        raise


def status_counts(records: Iterable[VinkoRecord]) -> dict[str, int]:
    counts = {status: 0 for status in STATUS_NAMES}
    for record in records:
        counts[record.record_status] += 1
    return counts


def iso_now() -> str:
    return datetime.now().astimezone().isoformat()


def summary(
    config: RunConfig,
    pages_fetched: int,
    records: list[VinkoRecord],
    proxy_used: bool,
    started_at: str,
    partial_run: bool,
    exit_code: int,
    run_reasons: Iterable[str] = (),
) -> dict[str, object]:
    return {
        "source": SOURCE_NAME,
        "base_url": config.base_url,
        "pages_requested": config.pages,
        "pages_fetched": pages_fetched,
        "records_total": len(records),
        "records_by_status": status_counts(records),
        "output_path": str(config.output.resolve()),
        "overwrite": config.overwrite,
        "proxy_used": proxy_used,
        "started_at": started_at,
        "finished_at": iso_now(),
        "partial_run": partial_run,
        "exit_code": exit_code,
        "run_reasons": sorted(set(run_reasons)),
    }


def run(config: RunConfig, proxies: dict[str, str] | None) -> tuple[
    list[VinkoRecord], int, bool, int, list[str]
]:
    session = requests.Session()
    records: list[VinkoRecord] = []
    pages_fetched = 0
    partial_run = False
    run_reasons: set[str] = set()
    visited_pages: set[str] = set()
    seen_records: set[tuple[str, ...]] = set()
    current_url = config.base_url

    try:
        for page_number in range(1, config.pages + 1):
            if current_url in visited_pages:
                partial_run = True
                break
            try:
                html = fetch_page(session, current_url, config, proxies)
                cards = extract_cards(html)
            except (SourceFailure, ListingStructureError) as exc:
                if isinstance(exc, ListingStructureError):
                    run_reasons.add("html_structure_changed")
                if pages_fetched == 0:
                    return [], 0, False, EXIT_SOURCE_FAILURE, sorted(run_reasons)
                partial_run = True
                break

            visited_pages.add(current_url)
            pages_fetched += 1
            for card in cards:
                record = parse_card(card, config.base_url, current_url)
                key = record_dedupe_key(record)
                if key in seen_records:
                    continue
                seen_records.add(key)
                records.append(record)

            next_url = find_next_page_url(html, current_url, page_number)
            if not next_url:
                break
            current_url = next_url
            if page_number < config.pages and config.delay:
                time.sleep(config.delay)
    finally:
        session.close()

    records.sort(
        key=lambda row: (
            row.source_product_url,
            row.source_card_id,
            row.source_title,
            row.source_model,
        )
    )
    parse_errors = any(row.record_status == "parse_error" for row in records)
    exit_code = EXIT_PARTIAL if partial_run or parse_errors else EXIT_OK
    return records, pages_fetched, partial_run, exit_code, sorted(run_reasons)


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    started_at = iso_now()
    config: RunConfig | None = None
    try:
        config = validate_config(args)
        proxies = load_proxy(config.proxy_file)
    except FileExistsError as exc:
        print(f"output exists: {exc}", file=sys.stderr)
        return EXIT_OUTPUT_CONFLICT
    except ValidationError as exc:
        print(f"validation error: {exc}", file=sys.stderr)
        return EXIT_VALIDATION

    try:
        records, pages_fetched, partial_run, exit_code, run_reasons = run(
            config, proxies
        )
        if exit_code == EXIT_SOURCE_FAILURE:
            print(
                json.dumps(
                    summary(
                        config,
                        pages_fetched,
                        records,
                        proxies is not None,
                        started_at,
                        False,
                        exit_code,
                        run_reasons,
                    ),
                    ensure_ascii=False,
                )
            )
            return exit_code
        write_csv_atomic(records, config.output)
        print(
            json.dumps(
                summary(
                    config,
                    pages_fetched,
                    records,
                    proxies is not None,
                    started_at,
                    partial_run,
                    exit_code,
                    run_reasons,
                ),
                ensure_ascii=False,
            )
        )
        return exit_code
    except Exception as exc:
        print(f"internal error: {exc}", file=sys.stderr)
        if config is not None:
            print(
                json.dumps(
                    summary(
                        config,
                        0,
                        [],
                        False,
                        started_at,
                        False,
                        EXIT_INTERNAL,
                        (),
                    ),
                    ensure_ascii=False,
                )
            )
        return EXIT_INTERNAL


if __name__ == "__main__":
    raise SystemExit(main())

