# CashForo public documentation evidence

Sources checked:

- `https://cashforo.com`
- `https://cashforo.com/aml`
- `https://cashforo.com/terms-and-conditions`

No public API reference was found at:

- `https://cashforo.com/docs`
- `https://cashforo.com/api`
- `https://cashforo.com/api-docs`
- `https://cashforo.com/developers`
- `https://cashforo.com/documentation`

Expected docs subdomains did not resolve or were not publicly accessible from this environment:

- `api.cashforo.com`
- `docs.cashforo.com`
- `developer.cashforo.com`
- `developers.cashforo.com`

## Confirmed from public pages

These items can be treated as documentation evidence:

- Provider legal name: `CashForo LTD`.
- Jurisdiction: Republic of Cyprus.
- Service category: payment gateway / payment processing services.
- Platform claim: interfaces with terminal equipment of banks, retail organizations, and enterprises to automate payment processing.
- Compliance: Cyprus and EU payment/AML/GDPR framework.
- AML/KYC: client identification, company documents, beneficial owners, screening, EDD, source of funds/source of wealth where needed.
- GDPR: merchant is Data Controller; CashForo acts as Data Processor.
- Financial operations: transactions, refunds, chargebacks, payouts, fees, rolling reserves, payout frequency/currency depending on risk/jurisdiction/banking.
- Termination/suspension risks: fraud, regulatory risk, suspicious activity, breach.

## Not confirmed by public pages

These still require API documentation or direct CashForo confirmation:

- Sandbox API endpoint.
- Production API endpoint.
- API version.
- Authentication method.
- Credential delivery/rotation.
- IP allow-list requirements.
- mTLS/certificate requirements.
- Rate limits.
- Timeout rules.
- Request/response schemas.
- Idempotency behavior.
- Error code catalog.
- Webhook event catalog and samples.
- Webhook signature algorithm/header/canonical payload.
- Onramp quote endpoint and quote expiry.
- Supported stablecoin assets for OR001, especially USDT and USDC.
- Exchange wallet creation and customer wallet ownership rules.
- USDT/USDC receipt confirmation.
- USDT/USDC-to-Readies swap rules and rate source.
- Merchant Readies payment and merchant buyback/redemption events.
- Open Banking consent/payment initiation/account ownership flow.

## Readies Onramp OR001 business flow to validate

The Onramp test is not a generic crypto-purchase test. It must validate the full Readies payment loop:

1. Customer pays the licensed onramper by credit/debit card.
2. Customer officially buys USDT or USDC from the onramper.
3. The USDT/USDC is sent to a customer wallet created on the Readies/Finexeble exchange.
4. Finexeble/Readies takes/swaps the USDT/USDC into Readies.
5. Current business rule: 1 Readies = EUR 0.10, so EUR 100 becomes 1000 Readies after the stablecoin step.
6. Customer pays the merchant/casino in Readies.
7. Merchant later sells Readies back to Finexeble/Readies.
8. Finexeble/Readies deducts commission for handling the payment.
9. Ledger, custody, reserves, settlement, refunds, chargebacks, redemptions, and commission reconcile end-to-end.

This flow still requires explicit legal/compliance approval. The test must not treat a merchant/casino as approved simply because the flow is crypto-based. The go-live gate should remain blocked until merchant category, jurisdiction, licensing status, stablecoin purchase/swap/redemption, custody, reserves, chargebacks, commission handling, and merchant buyback obligations are approved.
