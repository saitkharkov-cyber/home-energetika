import csv

out = []

with open("sumoto_import_ready_98.csv", encoding="utf-8-sig", newline="") as f:
    rows = list(csv.DictReader(f))

for r in rows:
    if r.get("match_status") != "matched":
        continue

    v = (r.get("voltage") or r.get("source_voltage") or "").strip()

    if v.startswith("220"):
        text = "220V"
    elif v.startswith("380"):
        text = "380V"
    else:
        continue

    product_id = int(r["product_id"])
    out.append(
        "REPLACE INTO oc_product_attribute "
        "(product_id, attribute_id, language_id, text) "
        f"VALUES ({product_id}, 15, 1, '{text}');"
    )

with open("sumoto_voltage_attribute_98.sql", "w", encoding="utf-8") as f:
    f.write("\n".join(out) + "\n")

print("written:", len(out), "sumoto_voltage_attribute_98.sql")