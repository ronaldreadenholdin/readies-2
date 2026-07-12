# Readies / Okepay — inventory of YOUR work (correct labels)

This list separates real work by provider.  
**Do not treat anything stamped “AfrPay” from the mislabel packs as AfrPay product.**

---

## 1. FBLS — P003
- Pre-flight harness (simple + expanded)
- Webhook middleware / sandbox routes in harness package
- Codes: **P003**

## 2. Xcore — P004
- Europe pre-flight coverage (3DS, name rules, signature)
- Codes: **P004**

## 3. Flamingo (dual board origin)
- First reusable dual-connection provider board
- Then expanded to 84 checks per connection
- Later generalized for other providers
- Path evolution: Flamingo → reusable board → CashForo model

## 4. CashForo — OR001 + OB003 (real dual product model)
- **OR001** Onramp / card → USDT/USDC → Readies
- **OB003** Open Banking
- Final complete dual board (84+84, work box, Bob, go-live)
- CashForo public docs evidence notes
- CashForo adaptor stubs + API document request docs
- Hostinger upload package of the **CashForo** provider board

## 5. OB Fena
- Separate Open Banking Fena local test board
- Not CashForo, not Flamingo, not AfrPay

## 6. Next PSP starter — P005
- Blank starter harness for the next PSP
- Codes: **P005** (placeholder)

## 7. Shared PSP platform work
- PspAdaptorInterface + DTOs + factory
- PspTestHarnessService / Bob recommendations
- Complete harness zip (`readies-psp-test-harness-complete`)
- Operational harness branch / connect UI work

---

## Codes (correct)

| Code | Belongs to |
|------|------------|
| P003 | FBLS |
| P004 | Xcore |
| P005 | Next PSP starter (placeholder) |
| OR001 | **CashForo** onramp (card→crypto) |
| OB003 | **CashForo** open banking |
| OB Fena board | **Fena** only |

**AfrPay:** no verified product type/code in this repo yet.

---

## Mislabeled (agent error — DO NOT use as AfrPay)

These were CashForo/Flamingo patterns renamed “AfrPay”:
- `afrpay-provider-test-package*`
- `afrpay-full-set-all-docs*`
- `afrpay-live-api*`
- `afrpay-hostinger-*`
- `afrpay-or001-only-test-board*`

They are **not** checked AfrPay product APIs.

---

## Where the real boards live

Compare pack:  
https://github.com/ronaldreadenholdin/readies-2/raw/okepayafrpay-full-set-docs-2c8a/psp-five-test-boards.zip  

CashForo final dual board:  
`psp-five-test-boards/05-cashforo-final-complete.html`  

Main build branch for harness/boards:  
`okepaypre-flight-test-html-763a`
