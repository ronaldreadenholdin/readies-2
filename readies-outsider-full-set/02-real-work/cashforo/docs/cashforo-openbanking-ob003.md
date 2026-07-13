# CashForo Open Banking adaptor - OB003

OB003 is separate from OR001 and must use a separate adaptor because the lifecycle, consent, bank status, settlement, and failure model are different.

## Required adaptor responsibilities

- Start bank consent/payment authorization.
- Redirect or embed the bank authorization flow.
- Track consent status.
- Confirm bank/account ownership where required.
- Initiate payment.
- Handle pending, authorized, settled, failed, rejected, expired, reversed states.
- Reconcile settlement and bank references.
- Handle reversals/refunds if supported.

## Required CashForo API details

These are still needed from CashForo before live mapping:

- Sandbox base URL.
- Production base URL.
- Authentication method.
- API version.
- Bank list endpoint.
- Consent start endpoint.
- Consent callback format.
- Payment initiation endpoint.
- Required request fields.
- Response fields.
- Provider payment reference.
- Webhook event list and samples.
- Signature header and HMAC canonical string.
- Status endpoint.
- Settlement report fields.
- Refund/reversal support.

## Status mapping target

Readies should normalize CashForo Open Banking statuses into:

- `created`
- `pending`
- `completed`
- `failed`
- `reversed`
- `unknown`

No live deployment until all provider-specific statuses are mapped.
