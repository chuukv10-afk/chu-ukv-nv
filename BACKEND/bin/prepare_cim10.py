#!/usr/bin/env python3
"""Prepare CIM-10 FR dataset for CHU UKV import (dedupe + filter)."""

from __future__ import annotations

import gzip
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "assets" / "referentiel" / "cim_hierarchie_code.json.gz"
OUTPUT = ROOT / "assets" / "referentiel" / "cim10_import.jsonl.gz"


def normalize_chapitre(raw: str | None) -> str | None:
    if not raw:
        return None
    return raw.strip().strip("[]")[:20] or None


def main() -> int:
    if not SOURCE.is_file():
        print(f"Source introuvable: {SOURCE}", file=sys.stderr)
        return 1

    with gzip.open(SOURCE, "rt", encoding="utf-8") as handle:
        rows = json.load(handle)

    print(f"Lignes source: {len(rows)}")

    best: dict[str, dict] = {}
    for row in rows:
        code = (row.get("code") or "").strip().upper()
        if not code:
            continue
        if row.get("tr") not in {"0", "3"}:
            continue

        year = int(row.get("time_i") or row.get("anseqta") or 0)
        current = best.get(code)
        if current is None or year >= current["_year"]:
            best[code] = {
                "_year": year,
                "code": code,
                "libelle": (row.get("lib_long") or row.get("lib_court") or "").strip(),
                "chapitre": normalize_chapitre(row.get("chapitre")),
                "lib_chapitre": (row.get("lib_chapitre") or "").strip() or None,
                "tr": row.get("tr"),
            }

    entries = sorted(best.values(), key=lambda item: item["code"])
    print(f"Codes retenus (tr 0/3, dédoublonnés): {len(entries)}")

    max_len = max(len(item["libelle"]) for item in entries)
    print(f"Longueur max libellé: {max_len}")

    with gzip.open(OUTPUT, "wt", encoding="utf-8") as handle:
        for item in entries:
            payload = {
                "code": item["code"],
                "libelle": item["libelle"],
                "chapitre": item["chapitre"],
                "lib_chapitre": item["lib_chapitre"],
                "tr": item["tr"],
            }
            handle.write(json.dumps(payload, ensure_ascii=False) + "\n")

    print(f"Fichier généré: {OUTPUT}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
