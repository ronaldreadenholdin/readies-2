# AfrPay Go-Live — AFTER test approval (urgent)

This package is the **adjusted live API**, not the test board.

## Fast path to live

1. Drop `app/`, `config/psp_adaptors.php`, and `routes/afrpay.php` into the Laravel host.
2. Register routes: `require base_path('routes/afrpay.php');`
3. Copy `.env.example` keys into host `.env` with AfrPay sandbox credentials.
4. For sandbox wiring only (before formal unlock):
   ```bash
   AFRPAY_OR001_FORCE_SANDBOX_CALLS=true
   AFRPAY_OB003_FORCE_SANDBOX_CALLS=true
   ```
5. Run pre-flight until **100% green**.
6. Unlock live:
   ```bash
   AFRPAY_TEST_APPROVED=true
   AFRPAY_OR001_TEST_APPROVED=true
   AFRPAY_OB003_TEST_APPROVED=true
   AFRPAY_LIVE_ENABLED=true
   AFRPAY_TEST_APPROVED_BY=your.name
   AFRPAY_TEST_APPROVED_AT=2026-07-12T12:00:00Z
   ```
   Or `POST /api/afrpay/go-live/approve` with `{ "approved_by": "...", "enable_live": true }` and apply returned env lines.
7. Point AfrPay webhooks to:
   - `POST /webhooks/afrpay/OR001`
   - `POST /webhooks/afrpay/OB003`
8. Switch `AFRPAY_*_BASE_URL` to **production** URLs.
9. Restart PHP-FPM / workers / queue.

## Live API endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/afrpay/status` | Gate + connection status |
| POST | `/api/afrpay/OR001/payments` | Create onramp payment |
| GET | `/api/afrpay/OR001/payments/{ref}` | Status |
| POST | `/api/afrpay/OR001/refunds` | Refund |
| POST | `/api/afrpay/OB003/payments` | Create open-banking payment |
| GET | `/api/afrpay/OB003/payments/{ref}` | Status |
| POST | `/api/afrpay/OB003/refunds` | Refund |
| POST | `/api/afrpay/go-live/approve` | Emit unlock env after green tests |
| POST | `/webhooks/afrpay/{OR001\|OB003}` | Provider webhooks |

## Create payment body example

```json
{
  "merchant_reference": "ORDER-10001",
  "customer_reference": "CUST-500",
  "amount_minor": 10000,
  "currency": "EUR",
  "success_url": "https://merchant.example/success",
  "failure_url": "https://merchant.example/failure",
  "idempotency_key": "ORDER-10001-OR001",
  "customer": { "email": "customer@example.com" },
  "metadata": {
    "target_asset": "USDT",
    "exchange_wallet_reference": "wallet_123"
  }
}
```

## Path overrides

If AfrPay docs use different paths than `/v1/onramp/payments`, set the `AFRPAY_*_CREATE_PATH` / `STATUS_PATH` / `REFUND_PATH` env vars. No code change required.

## Safety

Live provider HTTP calls are **blocked** until:

- `AFRPAY_LIVE_ENABLED=true`
- connection test approved (`AFRPAY_OR001_TEST_APPROVED` / `AFRPAY_OB003_TEST_APPROVED` or global `AFRPAY_TEST_APPROVED`)
- `base_url` + `api_key` present

Exception: `FORCE_SANDBOX_CALLS=true` for pre-approval wiring only.
