"""FTD vs trusted list. Same rules as the PHP/Laravel pack."""

from __future__ import annotations

import json
import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Any
from uuid import uuid4

FTD = "FTD"
TRUSTED = "trusted"
MATCH_ORDER = ("email", "phone", "card_first6_last4", "birthday", "full_name")
BIZ_VALUES = (
    "gambling",
    "gaming",
    "mlm",
    "food_supplements",
    "pharma",
    "forex",
    "digital_products",
    "other",
)
BIZ_ALIASES = {
    "casino": "gambling",
    "igaming": "gambling",
    "supplements": "food_supplements",
    "food_supplement": "food_supplements",
    "nutra": "food_supplements",
    "pharmacy": "pharma",
    "fx": "forex",
    "digital": "digital_products",
    "digital_product": "digital_products",
}


class TrustedList:
    def __init__(self, path: str | Path | None = None):
        self.path = Path(path) if path else None
        self.records: list[dict[str, Any]] = []
        if self.path and self.path.is_file():
            payload = json.loads(self.path.read_text())
            self.records = list(payload.get("records") or [])

    def keys(self, input_data: dict[str, Any]) -> dict[str, str | None]:
        return {
            "email": self._email(input_data.get("email")),
            "phone": self._phone(input_data.get("phone")),
            "card_first6_last4": self._card(input_data.get("card_first6"), input_data.get("card_last4")),
            "birthday": self._birthday(input_data.get("birthday")),
            "full_name": self._name(input_data.get("full_name") or input_data.get("name")),
        }

    def merchant(self, value: Any) -> str:
        raw = re.sub(r"[^a-z0-9_-]+", "-", str(value or "").strip().lower()).strip("-")
        return raw or "default"

    def classify(self, input_data: dict[str, Any]) -> dict[str, Any]:
        merchant = self.merchant(input_data.get("merchant"))
        keys = self.keys(input_data)
        records = [row for row in self.records if (row.get("merchant") or "default") == merchant]
        for field in MATCH_ORDER:
            value = keys.get(field)
            if not value:
                continue
            for record in records:
                if record.get(field) == value:
                    return {"status": TRUSTED, "matched_by": field, "record": record}
        return {"status": FTD, "matched_by": None, "record": None}

    def mark_paid(self, input_data: dict[str, Any]) -> dict[str, Any]:
        found = self.classify(input_data)
        record = dict(found["record"] or {"id": uuid4().hex[:16], "created_at": _now(), "successful_payments": 0})
        record["merchant"] = self.merchant(input_data.get("merchant"))
        for field, value in self.keys(input_data).items():
            if value:
                record[field] = value
        biz = self._biz(input_data.get("biz") or input_data.get("business"))
        if biz:
            record["biz"] = biz
        record["trusted"] = True
        record["successful_payments"] = int(record.get("successful_payments") or 0) + 1
        record["last_provider"] = str(input_data.get("provider") or "")[:32]
        record["last_paid_at"] = _now()
        record["updated_at"] = _now()
        self._upsert(record)
        return {"status": TRUSTED, "matched_by": "successful_payment", "record": record}

    def replace_from_csv(self, merchant: str, csv_text: str) -> dict[str, Any]:
        merchant = self.merchant(merchant)
        if merchant == "default":
            raise ValueError("Merchant name is required for an upload.")
        lines = csv_text.replace("\ufeff", "").splitlines()
        if not lines:
            raise ValueError("CSV needs a header row.")
        import csv
        from io import StringIO

        reader = csv.DictReader(StringIO(csv_text.replace("\ufeff", "")))
        imported: list[dict[str, Any]] = []
        skipped = 0
        for raw in reader:
            row = {str(k).strip().lower(): (v or "") for k, v in raw.items() if k}
            aliases = {"mail": "email", "mobile": "phone", "bin": "card_first6", "last4": "card_last4", "dob": "birthday", "name": "full_name", "business": "biz"}
            row = {aliases.get(k, k): v for k, v in row.items()}
            keys = self.keys(row)
            if not any(keys.values()):
                skipped += 1
                continue
            record = {
                "id": uuid4().hex[:16],
                "merchant": merchant,
                "trusted": True,
                "source": "merchant_upload",
                "successful_payments": 0,
                "created_at": _now(),
                "updated_at": _now(),
            }
            for field, value in keys.items():
                if value:
                    record[field] = value
            biz = self._biz(row.get("biz") or row.get("business"))
            if biz:
                record["biz"] = biz
            imported.append(record)
        self.records = [row for row in self.records if (row.get("merchant") or "default") != merchant] + imported
        self._write()
        return {
            "merchant": merchant,
            "imported": len(imported),
            "skipped": skipped,
            "trusted_count": len(imported),
            "rule": "This upload is now the whole trusted list for this merchant.",
        }

    def _upsert(self, record: dict[str, Any]) -> None:
        for index, row in enumerate(self.records):
            if row.get("id") == record.get("id"):
                self.records[index] = record
                self._write()
                return
        self.records.append(record)
        self._write()

    def _write(self) -> None:
        if not self.path:
            return
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.path.write_text(json.dumps({"records": self.records}, indent=2))

    def _email(self, value: Any) -> str | None:
        email = str(value or "").strip().lower()
        return email if email and "@" in email else None

    def _phone(self, value: Any) -> str | None:
        digits = re.sub(r"\D+", "", str(value or ""))
        return digits if len(digits) >= 8 else None

    def _card(self, first6: Any, last4: Any) -> str | None:
        bin6 = re.sub(r"\D+", "", str(first6 or ""))
        tail = re.sub(r"\D+", "", str(last4 or ""))
        return f"{bin6}{tail}" if len(bin6) == 6 and len(tail) == 4 else None

    def _birthday(self, value: Any) -> str | None:
        raw = str(value or "").strip()
        if not raw:
            return None
        for fmt in ("%Y-%m-%d", "%d-%m-%Y", "%d/%m/%Y", "%Y/%m/%d"):
            try:
                return datetime.strptime(raw, fmt).date().isoformat()
            except ValueError:
                continue
        return None

    def _biz(self, value: Any) -> str | None:
        raw = re.sub(r"[\s\-]+", "_", str(value or "").strip().lower())
        raw = re.sub(r"_+", "_", raw)
        biz = BIZ_ALIASES.get(raw, raw)
        if not biz:
            return None
        return biz if biz in BIZ_VALUES else biz[:64]

    def _name(self, value: Any) -> str | None:
        name = re.sub(r"\s+", " ", str(value or "").strip().lower())
        return name if name and " " in name else None


def _now() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()
