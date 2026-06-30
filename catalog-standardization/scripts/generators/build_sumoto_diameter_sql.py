import csv
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent.parent

INPUT_CSV = BASE_DIR / "data" / "sumoto" / "validated" / "sumoto_import_ready_98.csv"
OUTPUT_SQL = BASE_DIR / "sql" / "generated" / "sumoto" / "sumoto_diameter_attribute_98.sql"

ATTRIBUTE_ID = 44


def sql_escape(value: str) -> str:
    return value.replace("\\", "\\\\").replace("'", "\\'")


with INPUT_CSV.open("r", encoding="utf-8-sig", newline="") as f:
    reader = csv.DictReader(f)

    with OUTPUT_SQL.open("w", encoding="utf-8", newline="") as out:

        out.write("-- Sumoto diameter import\n")
        out.write("-- Attribute: Диаметр насоса (мм)\n\n")

        count = 0

        for row in reader:

            product_id = row["product_id"].strip()
            diameter = row["diameter_mm"].strip()

            if not product_id or not diameter:
                continue

            out.write(
                "UPDATE oc_product_attribute\n"
                f"SET text = '{sql_escape(diameter)}'\n"
                f"WHERE product_id = {product_id}\n"
                f"  AND attribute_id = {ATTRIBUTE_ID};\n\n"
            )

            count += 1

        out.write(f"-- Total updates: {count}\n")

print(f"Generated {count} UPDATE statements.")
print(OUTPUT_SQL)