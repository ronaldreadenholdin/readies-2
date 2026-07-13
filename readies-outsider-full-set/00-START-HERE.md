# Readies / Okepay — outsider full set (readable handoff)

**Audience:** outside agent or human continuing this work  
**Date:** 2026-07-13  
**Repo:** `github.com/ronaldreadenholdin/readies-2`  
**Owner:** Gerardus Steenbergen / ronald@readenholdingcorp.com

Read this file first, then `01-read-first/`.

---

## Hard rules (do not violate)

1. **AfrPay ≠ Flamingo ≠ CashForo.**
2. **AfrPay** = 3 geos with different costs: **Europe, Kazakhstan, Tunisia**. Finished before Flamingo. Real AfrPay docs/API are **missing from this repo** — see `03-afrpay-status/`.
3. **OR001 + OB003** belong to **CashForo** (onramp + open banking), not AfrPay.
4. **Bob is fired.** No Bob chat/recommendations on boards.
5. Anything historically named `afrpay-*` in git that looks like onramp/open-banking is **mislabeled** — see `04-MISLABELED-quarantine-do-not-use/`.

---

## Package map

| Folder | What it is |
|--------|------------|
| `01-read-first/` | Inventory + AfrPay identity correction |
| `02-real-work/cashforo/` | Real CashForo docs, adaptors, boards, Hostinger board |
| `02-real-work/flamingo-board-evolution/` | Flamingo dual-board origin (before CashForo) |
| `02-real-work/ob-fena/` | Separate Fena open-banking board |
| `02-real-work/shared-psp-platform/` | PspAdaptorInterface + factory |
| `02-real-work/harness-notes/` | Harness README notes |
| `03-afrpay-status/` | What AfrPay is + what is still missing |
| `04-MISLABELED-quarantine-do-not-use/` | Names of bad packs — do not treat as AfrPay |

---

## Codes

| Code | Belongs to |
|------|------------|
| P003 | FBLS |
| P004 | Xcore |
| P005 | Next PSP starter (placeholder) |
| OR001 | CashForo onramp |
| OB003 | CashForo open banking |
| AfrPay | No verified code in this repo yet |

---

## Branches to know

- `okepaypre-flight-test-html-763a` — main PSP harness / CashForo / Flamingo build work
- `okepayob-fena-test-board-6ac6` — OB Fena board
- `okepayafrpay-full-set-docs-2c8a` — this handoff branch (also contains mislabeled afrpay packs — ignore those)

---

## What an outsider should do next

**If continuing CashForo / Flamingo / FBLS / Xcore / Fena:**  
use `02-real-work/` — labels are correct.

**If recovering AfrPay:**  
do **not** invent from CashForo/Flamingo. Ask owner for the original Europe / Kazakhstan / Tunisia onboarding + cost model + post-test API. Those files are not in this package.
