# PSP adapter conformance layer

## Inventory from this repository

- The Bob G catalog says `PspAdaptorInterface`, DTOs, CashForo stubs, a Laravel pre-flight harness, and HMAC middleware exist in the full 0609 host or prior Bob G branches.
- In this repository slice, those live Laravel PSP files were not present before this change. Only the catalog/audit notes and `pre-flight-test.html` were present.
- The checked-in `pre-flight-test.html` has 4 visible checkpoint rows: Auth & Connectivity, Webhook Handling, 3DS & Geo Strategy, and Signature Verification. It does not contain ~120 checks.
- No Neckermann references existed in this repository before this change. The new offline fixture uses a Neckermann-style test merchant reference so the gate can later point at that test site without changing its report contract.

## Normalized output contract

Every adapter method returns the same normalized shape:

```json
{
  "schema_version": "readies.psp.normalized.v1",
  "psp_code": "P003",
  "operation": "create_payment|payment_status|refund|webhook",
  "merchant_reference": "neckermann-test-order-1001",
  "payment_id": "fbls_pay_789",
  "psp_reference": "P003-TXN-001",
  "status": "pending|authorized|captured|settled|failed|declined|cancelled|refunded|skipped|unknown",
  "amount": {
    "value": "49.95",
    "currency": "EUR",
    "minor_units": 4995
  },
  "created_at": "2026-09-24T16:20:30Z",
  "updated_at": "2026-09-24T16:21:00Z",
  "decline": {
    "class": "none|soft|hard",
    "code": null,
    "message": null,
    "cascade_reason": null
  },
  "webhook_event": null,
  "metadata": {
    "cascade_eligible": true
  }
}
```

`PspNormalizedContract::validate()` enforces required keys, status and decline enums, uppercase 3-letter currency codes, decimal amount formatting, integer minor units, UTC `Z` timestamps, webhook signature status, and cascade metadata.

## Adapter pattern

- Converters implement `PspConverterInterface`; all PSP-specific field maps and `requiredFields()` live there.
- `AbstractPspAdaptor` implements `PspAdaptorInterface`, owns transport, verifies webhooks, and soft-skips missing required fields with `missing_required_field:<field>`.
- `PspAdapterRegistry` is the only lookup path for PSP adapters. `eligibleForCascade()` returns true only for a 100% conformance report.
- `FblsP003Adaptor` is the template PSP because FBSL/FBLS P003 is the only provider with checked-in pre-flight evidence here. The full live FBSL adapter code was not present.

## Cascade pre-collection contract

`CascadeRequirementsResolver` takes an ordered cascade such as `["P001", "P002", "P003"]` and returns the union of every converter's `requiredFields()`, including which PSP and cascade index needs each field. Checkout must validate the payment request against that union before hop 1 fires.

Rules:

- Missing union fields are reported before any PSP attempt.
- The cascade must not discover required customer data mid-route.
- Each converter declares `createPayloadFieldMap()` so the adapter sends only the fields that PSP maps. Extra data gathered for downline PSPs must not leak to upstream PSPs.
- If a field is still missing at a hop, `AbstractPspAdaptor` soft-skips with `missing_required_field:<field>` instead of silently stopping.
- `ConversionKillerChecker::checkDeclaredFieldDependencies()` flags converters that map an internal field without declaring it in `requiredFields()`.

## Webhook-driven cascade contract

`PspWebhookDrivenCascadeOrchestrator` is event-driven: it starts one hop and then waits for a webhook or a queued timeout job to resume the cascade. It does not sleep or block inside the request.

Rules:

- A synchronous `pending` or `processing` response never advances to the next PSP.
- The next hop only starts after a failed webhook or after the PSP wait timeout expires and `getPaymentStatus()` returns a final failed/declined status.
- If timeout plus status query still returns pending or unknown, the order is marked `awaiting_final_status` and does not cascade, to avoid double-charge risk.
- A late success webhook for an earlier hop is flagged as `late_success_possible_double_charge` with idempotency keyed by `merchant_reference`.
- Automatic cascade attempts are capped by `max_attempts`, default `3`; hard decline still stops immediately.
- After three confirmed failures, `PaymentRecoveryService` creates a secure expiring single-use pay-by-link token and builds a neutral recovery email. Sending is behind a flag that defaults off.
- Audit rows record attempt time, webhook/status event, waited milliseconds, cascade reason, and outcome.

## Conversion-killer categories

The gate checks and reports:

- `unit/cents`
- `currency`
- `rounding`
- `status enum`
- `missing requiredField`
- `date/timezone`
- `ID case/whitespace`
- `null vs empty`
- `signature/encoding`
- `soft/hard decline misclass`
- exact golden normalized output drift
- undeclared converter dependencies that would break checkout pre-collection
- cascaded without confirmed final failure
- missing webhook within timeout when status is still not final

Each failed issue row includes PSP code, endpoint, check id/name, internal and PSP field paths, expected and actual values, category, root cause, code location, severity, and suggested fix.

## Running offline

```bash
php bob-c-0609/laravel/bin/run-psp-conformance.php P003 /tmp/psp-runs
```

The command writes timestamped JSON and Markdown under the output directory. It uses recorded fixtures only; no real PSP calls, no real keys, and no live cascade/routing changes.

## Current P003 result

P003 passes the normalized create/status/refund/webhook golden comparisons and declared-dependency checks. It is still not eligible for cascade because the existing pre-flight evidence has 2 flagged rows: Webhook Handling and Signature Verification. Score from the current fixture set is 53/55 = 96.36%.
