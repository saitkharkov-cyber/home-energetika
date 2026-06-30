import csv

out = []

with open("sumoto_import_ready_98.csv", encoding="utf-8-sig", newline="") as f:
    rows = list(csv.DictReader(f))

for r in rows:
    if r.get("match_status") != "matched":
        continue

    h = (r.get("max_head_m") or "").strip().replace(",", ".")

    if not h:
        continue

    value = float(h)
    if value.is_integer():
        text = f"{int(value)}м."
    else:
        text = f"{value:g}м."

    product_id = int(r["product_id"])

    out.append(
        "REPLACE INTO oc_product_attribute "
        "(product_id, attribute_id, language_id, text) "
        f"VALUES ({product_id}, 12, 1, '{text}');"
    )

with open("sumoto_head_attribute_98.sql", "w", encoding="utf-8") as f:
    f.write("\n".join(out) + "\n")

print("written:", len(out), "sumoto_head_attribute_98.sql")