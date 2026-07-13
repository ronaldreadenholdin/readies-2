# AfrPay — CORRECTED identity (from owner)

## What AfrPay actually is
- **3 geos with different costs**
  1. Europe
  2. Kazakhstan
  3. Tunisia
- Finished and tested **before** Flamingo was ever introduced
- Owner correction (2026-07-13): the mix-up came **after the AfrPay test** — later agents stamped Flamingo/CashForo patterns onto AfrPay and burned recovery time/money

## What AfrPay is NOT
- **Not** Flamingo
- **Not** CashForo
- **Not** OR001 onramp / card→crypto
- **Not** OB003 open banking
- **Not** any renamed Flamingo/CashForo dual board, Hostinger pack, or “live API” zip currently labeled `afrpay-*` in this repo

## Timeline (owner)
1. AfrPay built and finished (3 geos: Europe / Kazakhstan / Tunisia, different costs).
2. AfrPay tested and adjusted.
3. **Only after that** was Flamingo given.
4. After the AfrPay test, agents mixed identities and wrongly rebuilt AfrPay from Flamingo/CashForo templates.

## Status in this repo (`readies-2`) — 2026-07-13
- Kazakhstan / Tunisia / 3-geo cost model: **NOT FOUND** in:
  - all git history on this repo
  - available cloud-agent transcripts (`bc-c5c1bf1c`, `bc-a7e19e66`, `bc-48a69062`, this run)
  - sibling repos checked (`rds` empty stub, `okepay-2` empty)
- Real AfrPay final onboarding doc + adjusted API after test: **not present here** (lost elsewhere or never committed to `readies-2`).

## DO NOT USE as AfrPay (mislabeled packs on this branch)
These are Flamingo/CashForo renames. Discard for AfrPay work:

- `afrpay-full-set-all-docs.zip` / `afrpay-full-set-all-docs/`
- `afrpay-live-api-after-test-approval.zip` / `afrpay-live-api/`
- `afrpay-hostinger-public-html.zip` / `afrpay-hostinger-upload*`
- `afrpay-or001-only-test-board*`
- `afrpay-provider-test-package*` (preflight branch)
- `psp-five-test-boards/05b-afrpay-named-final.html`
- `psp-complete-test-board/afrpay-named-variant.html`

## Do not rebuild from Flamingo/CashForo
Any AfrPay rebuild must start only from AfrPay’s own **Europe / Kazakhstan / Tunisia** materials (docs, costs per geo, endpoints) confirmed by the owner — never from CashForo OR001/OB003 or Flamingo boards.
