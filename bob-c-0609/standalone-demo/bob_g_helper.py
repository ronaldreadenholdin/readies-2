"""Local Bob G helper used when XAI_API_KEY is missing."""

from __future__ import annotations

import os
import re
from typing import Any


SYSTEM_PROMPT = """You are Bob G, a Grok-powered Readies / Okepay backend assistant.
You are used from the BOB C sidebar tab on https://0609.readies.biz.

Help authorized backend users:
1. Create Laravel functions, controllers, routes, services, jobs, migrations, and Blade views.
2. Integrate payment service providers (PSPs) into the Readies gateway.
3. Draft webhook handlers, signature checks, sandbox vs live gates, and go-live checklists.
4. Explain errors and propose reviewable code.

Known provider codes:
- P003 = FBLS
- P004 = Xcore
- P005 = next PSP starter
- OR001 = CashForo onramp (card → USDT/USDC → Readies)
- OB003 = CashForo open banking
- AfrPay = three geos with different costs: Europe, Kazakhstan, Tunisia. Real AfrPay API docs may be missing. Do not invent them. Do not treat AfrPay as CashForo or Flamingo.

Hard rules:
- Never print live secrets, passwords, or webhook secrets.
- Never enable live PSP traffic from chat.
- All generated code is a draft. A human must review before Hostinger deploy.
- Prefer Laravel + Hostinger public/ document-root patterns.
- Keep answers practical and specific. Include file paths when you generate code.
"""


def status() -> dict[str, Any]:
    connected = bool(os.environ.get("XAI_API_KEY"))
    return {
        "agent": "BOB C",
        "assistant": "Bob G",
        "mode": "bob-g-grok" if connected else "local-helper",
        "model": os.environ.get("XAI_MODEL", "grok-3") if connected else "readies-local-helper",
        "connected": connected,
        "site": os.environ.get("APP_URL", "https://0609.readies.biz"),
    }


def local_reply(message: str) -> str:
    lower = message.lower()

    if "afrpay" in lower:
        return (
            "AfrPay is a separate provider with three geos: Europe, Kazakhstan, and Tunisia. "
            "Do not reuse CashForo OR001/OB003 or Flamingo boards as AfrPay. If you do not have "
            "the original AfrPay onboarding/API, ask the owner for those files before generating live adaptors.\n\n"
            "I can still draft a Laravel stub that keeps the three geos separate, with sandbox-only flags, "
            "once you share the real endpoints."
        )

    if re.search(r"cashforo|or001|ob003", lower):
        return (
            "CashForo uses two products:\n"
            "- OR001 onramp (card → USDT/USDC → Readies)\n"
            "- OB003 open banking\n\n"
            "For a Laravel drop-in, create `CashForoOnrampAdaptor` and `CashForoOpenBankingAdaptor` "
            "behind `PspAdaptorInterface`, plus `/webhooks/cashforo/OR001` and `/webhooks/cashforo/OB003`. "
            "Keep `LIVE_ENABLED=false` until pre-flight is green.\n\n"
            "Connect Bob G with `XAI_API_KEY` if you want me to generate the full files from Grok."
        )

    if re.search(r"fbls|p003|xcore|p004|psp", lower):
        return (
            "Known Readies PSP codes:\n"
            "- P003 FBLS\n"
            "- P004 Xcore (Europe: 3DS, name rules, signatures)\n"
            "- P005 next starter\n\n"
            "On 0609 the existing harness lives at `/psp-sandbox` and should stay sandbox-only until "
            "checks are green. Tell me which provider and I will draft the Laravel controller, "
            "webhook middleware, and `.env` keys."
        )

    if re.search(r"function|controller|route|laravel|migrate", lower):
        return (
            "I can draft Laravel pieces for 0609:\n"
            "1. Route in `routes/web.php` or a dedicated routes file\n"
            "2. Controller under `app/Http/Controllers`\n"
            "3. Service under `app/Services`\n"
            "4. Blade view under `resources/views` extending `layouts.adminpanel`\n"
            "5. Migration if you need storage\n\n"
            "Describe the function you want (name, input, output, who can use it). "
            "Connect `XAI_API_KEY` to have Bob G / Grok write the full files."
        )

    return (
        "I am Bob G, used from the BOB C tab.\n\n"
        "I can help you:\n"
        "- create Laravel functions for the 0609 backend\n"
        "- integrate PSPs (FBLS, Xcore, CashForo, AfrPay geos, Fena)\n"
        "- draft webhooks, signatures, and go-live gates\n\n"
        "Grok is not connected yet. Add `XAI_API_KEY` to `public_html/bob-c/.env` to switch this tab "
        "from the local helper to live Bob G.\n\n"
        "Ask a specific task, for example: “Draft a Laravel webhook for FBLS P003”."
    )
