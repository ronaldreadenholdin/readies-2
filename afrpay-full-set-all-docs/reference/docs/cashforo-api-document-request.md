# CashForo API documentation request

Dear CashForo Team,

Readies/Finexeble is preparing two separate connections:

- `OR001` - Onramp
- `OB003` - Open Banking

Please provide the following API documentation and samples for each connection.

## Shared requirements

1. Sandbox base URL.
2. Production base URL.
3. API version and changelog policy.
4. Authentication method.
5. Credential delivery and rotation procedure.
6. IP allow-list or mTLS/certificate requirements.
7. Rate limits and timeout rules.
8. Idempotency header/body field and duplicate request behavior.
9. Error code catalog.
10. Webhook event catalog.
11. Webhook retry policy.
12. Webhook signature header.
13. HMAC/canonical payload string.
14. Timestamp/replay-protection rules.
15. Status query endpoint.
16. Settlement/reconciliation report fields.

## OR001 Onramp requirements

1. Card-funded USDT/USDC purchase endpoint.
2. Quote request/response and quote expiry.
3. Supported assets (`USDT`, `USDC`) and networks.
4. Wallet destination field and validation rules.
5. 3DS flow examples.
6. Card authorization/capture lifecycle.
7. Purchase success/failure/pending webhook samples.
8. Refund and chargeback webhook samples.
9. Fees and final stablecoin amount fields.
10. Any required KYC fields and KYC status lifecycle.

## OB003 Open Banking requirements

1. Bank list endpoint.
2. Consent/authorization start endpoint.
3. Redirect/embedded authorization flow.
4. Account ownership verification fields.
5. Payment initiation endpoint.
6. Consent cancelled/expired examples.
7. Payment authorized/pending/settled/failed/reversed webhook samples.
8. Settlement timing and references.
9. Refund/reversal support.

## Go-live approval

Please also confirm:

- CashForo licensing coverage.
- Supported merchant categories and restricted categories.
- Casino/gaming merchant policy and jurisdiction restrictions.
- Compliance responsibilities between CashForo, Readies/Finexeble, merchant, and end customer.
- Written approval for sandbox-to-live activation.

Thank you.
