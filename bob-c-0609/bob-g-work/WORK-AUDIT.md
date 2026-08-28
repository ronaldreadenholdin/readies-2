# Bob G work audit — BOB C is the extension

BOB C is not a new agent. It is the 0609 sidebar for Bob G’s existing work.

## What Bob G already built

| Work | Where | Status |
|------|--------|--------|
| PSP adaptor interface + factory + DTOs | `okepaypre-flight-test-html-763a` `app/Contracts`, `app/Services/Psp` | Ready |
| CashForo OR001 + OB003 adaptors | same branch | Stubs. Live mapping blocked on API docs |
| Pre-flight harness (FBLS P003, Xcore P004) | `PspTestHarnessService.php`, `/psp-sandbox` | Ready |
| Bob recommendations | `BobRecommendationService.php` | Ready |
| Bob guidance / adjust / chat | `pre-flight-test.html` `buildBobAdvice`, `createBobResponse`, `applyLocalAdjustment` | Ready |
| 84+84 provider boards + work box | `provider-test-board.html` | Ready |
| Next PSP starter | `next-psp-start-here.html` | Ready |
| OB Fena board + Bob guidance | `okepayob-fena-test-board-6ac6` | Ready |
| HMAC webhook middleware | `VerifyPspWebhook.php` | Ready |

## Hard labels Bob G already corrected

- P003 = FBLS
- P004 = Xcore
- P005 = next starter
- OR001 = CashForo onramp
- OB003 = CashForo open banking
- AfrPay = Europe / Kazakhstan / Tunisia only. Not CashForo. Not Flamingo.

## What BOB C must do

1. Load this catalog.
2. Reuse Bob G functions (`generate`, `buildBobAdvice`, `createBobResponse`, go-live gate).
3. Continue open items. Do not rebuild boards, harness, or adaptors.
4. Keep sandbox/live gated the same way Bob G does: no go-live until checks are green.
