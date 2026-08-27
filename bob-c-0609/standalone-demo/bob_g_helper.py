"""BOB C helper that extends Bob G's existing work catalog."""

from __future__ import annotations

import json
import os
import re
from pathlib import Path
from typing import Any

CATALOG_PATHS = [
    Path(__file__).resolve().parents[1] / "bob-g-work" / "catalog.json",
    Path(__file__).resolve().parents[1] / "hostinger" / "public_html" / "bob-c" / "work" / "catalog.json",
]


def load_catalog() -> dict[str, Any]:
    for path in CATALOG_PATHS:
        if path.is_file():
            return json.loads(path.read_text())
    return {}


def catalog_summary(catalog: dict[str, Any] | None = None) -> dict[str, Any]:
    data = catalog or load_catalog()
    return {
        "agent": data.get("agent", "Bob G"),
        "extension": data.get("extension", "BOB C"),
        "rule": data.get("rule", "BOB C extends Bob G."),
        "completed": data.get("completed", []),
        "open": data.get("open", []),
        "providers": data.get("providers", []),
        "functions": ["list_work", "recommend", "advise", "respond", "go_live_gate"],
    }


def recommend(flagged_items: list[Any], psp_name: str = "PSP") -> str:
    if not flagged_items:
        return "All checks are green. No Bob recommendations needed."
    lines = [
        f"Dear {psp_name} Team,",
        "",
        "During the Readies PSP pre-flight check we found the following items that must be completed before live activation:",
        "",
    ]
    for index, item in enumerate(flagged_items, start=1):
        if isinstance(item, dict):
            category = item.get("category") or item.get("name") or "check"
            details = item.get("details") or item.get("message") or item.get("recommendation") or "Needs confirmation."
        else:
            category = str(item)
            details = "Needs confirmation."
        lines.append(f"{index}. {category}: {details}")
    lines.extend([
        "",
        "Please provide the missing documentation, sample payloads, signature details, or written approval so Readies can complete the go-live review.",
        "",
        "Thank you.",
    ])
    return "\n".join(lines)


def status() -> dict[str, Any]:
    connected = bool(os.environ.get("XAI_API_KEY"))
    return {
        "agent": "BOB C",
        "assistant": "Bob G",
        "extends": "Bob G",
        "mode": "bob-g-grok" if connected else "bob-g-workdesk",
        "model": os.environ.get("XAI_MODEL", "grok-3") if connected else "bob-g-workdesk",
        "connected": connected,
        "site": os.environ.get("APP_URL", "https://0609.readies.biz"),
        "work": catalog_summary(),
    }


def local_reply(message: str) -> str:
    catalog = load_catalog()
    lower = message.lower().strip()
    completed = catalog.get("completed", [])
    open_items = catalog.get("open", [])

    if re.search(r"work|what did|catalog|inventory|already built", lower):
        titles = [row.get("title", row.get("id")) for row in completed]
        open_titles = [row.get("title", row.get("id")) for row in open_items]
        return "BOB C extends Bob G. Already built:\n- " + "\n- ".join(titles) + "\n\nStill open:\n- " + "\n- ".join(open_titles)

    if "afrpay" in lower:
        return (
            "Recover AfrPay Europe / Kazakhstan / Tunisia materials. Blocked on: "
            "Owner onboarding + cost model + post-test API. AfrPay stays Europe / "
            "Kazakhstan / Tunisia. Do not copy CashForo OR001/OB003."
        )

    if re.search(r"cashforo|or001|ob003|adaptor", lower):
        return (
            "Bob G already created `PspAdaptorInterface`, `CashForoOnrampAdaptor` (OR001), "
            "and `CashForoOpenBankingAdaptor` (OB003). Those files are stubs with "
            "`API_DOCS_REQUIRED`. BOB C should map the real CashForo docs onto those adaptors, "
            "not write new ones."
        )

    if re.search(r"recommend|flagged|request list", lower):
        return recommend(
            [
                {"name": "Webhook sample", "details": "Need a signed webhook payload before go-live."},
                {"name": "Signature header", "details": "Confirm header name, secret, and canonical string."},
            ],
            "PSP",
        )

    if re.search(r"fbls|p003|xcore|p004|pre-flight|harness", lower):
        return (
            "Bob G already built `PspTestHarnessService` and `/psp-sandbox` for FBLS P003 and "
            "Xcore P004, plus `createBobResponse` / `buildBobAdvice` in `pre-flight-test.html`. "
            "Use those. BOB C only continues flagged checks, local adjustments, and the go-live gate."
        )

    if re.search(r"fena|ob-fena", lower):
        return (
            "Bob G already built the OB Fena board with `runFenaTest` and `showBobGuidance`. "
            "Continue webhook evidence, refund path, and settlement controls. Do not mix Fena with CashForo OB003."
        )

    if re.search(r"go live|golive|live traffic", lower):
        return "Go-live stays locked. Bob G will not enable live PSP traffic until every check is green."

    if re.search(r"function|controller|laravel|route", lower):
        return (
            "Reuse Bob G's Laravel pieces first: `PspSandboxController`, `BobRecommendationService`, "
            "`VerifyPspWebhook`, and `layouts.adminpanel` views. BOB C adds `/bob-c` as the sidebar "
            "extension. Only draft a new function if it is not already in the Bob G catalog."
        )

    return (
        "I am Bob G, used through BOB C.\n\n"
        "Ask me to continue existing work: recommendations, FBLS P003, Xcore P004, "
        "CashForo OR001/OB003 mapping, Fena, or the 0609 sidebar. I will not rebuild "
        "boards or adaptors that already exist."
    )
