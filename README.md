# readies-2

High-risk payment gateway — PSP integration workspace.

## PSP Pre-Flight Test Harness

`pre-flight-test.html` is a self-contained (no backend required) interface used to
validate a Payment Service Provider (PSP) integration **in the test environment
before a clone of the same interface is promoted to the live backend**.

It mirrors the live payment flow (incoming PSP API payload → Readies payment page →
backend + webhook output) and runs detailed checks across:

- Incoming PSP API (auth header, endpoint, required fields, idempotency, operator preset switching)
- Payment-page output (checkout route, amount/currency rendering, redirects, 3DS, error mapping)
- Backend and ledger (route, status normalization, atomic ledger write, retry policy, observability)
- Webhooks and security (webhook route, signature verification, replay protection, dedupe, PSP certificate)
- Go-live readiness (env separation, deploy target, rollback plan, 100%-green approval gate)

Presets are included for **P003 – FBLS** and **P004 – XCore**, plus an editable
**custom next customer** preset. The "Push green build to Hostinger" go-live button
stays locked until every check is green.

The same file is served by the live backend's Laravel route (`/pre-flight-test`,
via `PspSandboxController::preFlightTest()` which returns `base_path('pre-flight-test.html')`),
so the test-environment page and the live page are byte-for-byte identical.

### Run it locally (test environment)

No build step and no PHP required — it is a static HTML file.

```bash
# From the repository root
python3 -m http.server 8000
```

Then open:

```text
http://127.0.0.1:8000/pre-flight-test.html
```

Suggested walkthrough:

1. Click **Run full pre-flight** to render every individual check with evidence and Bob guidance.
2. Use **Bob adjust** on flagged (Readies-owned) checks, or **Apply Bob local adjustments** to fix them all.
3. Use **Mark PSP confirmed** on PSP-owned last-resort items once the PSP supplies its certificate/allow-list.
4. When readiness reaches 100%, the **Push green build to Hostinger** go-live gate unlocks.
5. Switch PSP via the **Customer / PSP preset** selector (FBLS, XCore, or a saved custom customer) and re-run.
