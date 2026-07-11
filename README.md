# readies-2

## PSP API adaptor interface

Use these files for the real PSP API/adaptor work:

- `app/Contracts/PspAdaptorInterface.php`
- `app/Services/Psp/PspAdaptorFactory.php`
- `app/Services/Psp/Adaptors/CashForoOnrampAdaptor.php`
- `app/Services/Psp/Adaptors/CashForoOpenBankingAdaptor.php`
- `config/psp_adaptors.php`
- `docs/psp-adaptor-interface.md`
- `docs/cashforo-onramp-or001.md`
- `docs/cashforo-openbanking-ob003.md`
- `docs/cashforo-api-document-request.md`

The adaptors are separate:

- `OR001` = CashForo Onramp
- `OB003` = CashForo Open Banking

The adaptor interface is ready, but exact live API mapping still needs the CashForo API documentation: endpoints, auth, request/response schemas, webhook samples, and signature rules.

## PSP pre-flight test harness

The standalone FBLS (P003) and Xcore (P004) test page is available in these forms:

- Laravel route: `/pre-flight-test`
- Static hosting route: `/pre-flight-test/`
- Static file fallback: `/pre-flight-test.html`

## Starting the next PSP

Use these files:

- Original recovered test: `public/pre-flight-test/index.html`
- Next PSP starter copy: `next-psp-start-here.html`
- Reusable provider test board: `provider-test-board.html`
- Polished current dashboard: `pre-flight-test.html`

For the next PSP, open `next-psp-start-here.html`, change the PSP name/code/region, and click **Run Full Test**.

For any provider, open `provider-test-board.html`. It has two configurable connection tests. Each connection runs an 84-point checklist. Current CashForo values are:

1. Connection 1 / Onramp: `OR001`
2. Connection 2 / Open Banking: `OB003`

For a quick local static test from this repository:

```bash
python3 -m http.server 8000 --directory public
```

Then open:

```text
http://127.0.0.1:8000/pre-flight-test/
```

Use the page in this order:

1. Click **Run Full Test**.
2. Click **Run Second Test**.
3. Click **Run Xcore P004 Test**.
4. Review all Bob recommendation sections.
5. Click **Go Live Check** after all tests have run.

## CashForo public documentation evidence

Public CashForo pages were checked and summarized in `cashforo-public-docs-evidence.md`. The board marks only those items green that are supported by the public site, AML policy, or terms. API endpoints, authentication, webhook samples, signature details, and schemas remain red until actual API documentation is provided.

## Readies Onramp OR001 model

Onramp OR001 validates the full stablecoin payment loop: customer pays licensed onramper by card -> customer officially buys USDT/USDC -> USDT/USDC is sent to a customer wallet on the Readies/Finexeble exchange -> Finexeble/Readies swaps USDT/USDC into Readies at the configured rule (currently 1 Readies = EUR 0.10, so EUR 100 becomes 1000 Readies) -> customer pays merchant/casino in Readies -> merchant sells Readies back to Finexeble/Readies -> commission is deducted for payment handling. The checklist blocks go-live until legal/compliance approves merchant category, gaming/casino jurisdiction/licensing status, stablecoin purchase/swap/redemption, custody, reserves, chargebacks, commission handling, and merchant buyback obligations.

## Payment Page Work Box

`provider-test-board.html` includes a Payment Page Work Box at the top of the board. Use it to write notes, provider answers, API details, or questions while testing without switching pages. The box saves locally in the browser and can append text into Provider Notes for Bob recommendations.
