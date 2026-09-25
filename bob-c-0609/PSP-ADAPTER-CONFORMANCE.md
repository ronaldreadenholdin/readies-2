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

## Numbered adapter standards

Readies has seven adapter standards, `ADP-01` through `ADP-07`.

- `ADP-01` is active and is the Card PSP standard. It uses `readies.psp.normalized.v1`.
- `ADP-02` through `ADP-07` are planned placeholders with name `TBD`; their contracts are intentionally not invented here.
- Every provider connection is registered as `{adapter_number} / {provider_code}`, for example `ADP-01 / P003`.
- Each connection has its own converter that maps the provider API into that adapter standard's normalized contract.
- A connection is eligible only when its adapter conformance checklist is exactly `100%`. There are no code overrides.
- Failed checks generate open questions that identify whether the converter can close the gap or whether the provider must change/document something.

## Per-merchant PSP order and TADDY overrides

The live 0609 host already has the merchant PSP / Payment providers tab, but that code is only on `/var/www/html/adapter` and is not present in this repo. This PR does not create a duplicate list. It provides a drop-in module for the existing tab once server access is available.

`MerchantPspOrderService` suggests positions from eligibility, cost, and conformance. Cheaper eligible connections sort earlier; high-cost providers are pushed later as fallback candidates. Connections below 100% conformance are listed as `planned` only and cannot hold a live position.

Users with orchestration-owner authority, including TADDY/Gerardus, can override positions. `merchant_psp_overrides` records append-only audit rows with merchant, connection, from/to positions, reason, who overrode, and when. Drop-in Blade partials show suggested and actual positions side by side inside the existing merchant tab. Automatic hops remain capped at 3 before pay-by-link.

When actual position differs from the System/CODA suggestion, `MerchantPspOrderService::disagreements()` marks the merchant/connection as `Needs Gerardus discussion` with both positions and both reasons. The disagreement view sends no email or notification.

## P001 Clisapay dry run

`ADP-01 / P001` is registered as a dry-run Card PSP connection for Clisapay / JIXINGBAO TRADING PTE. LTD. The profile and report use only the provided facts and mark all other fields unknown or missing. The saved report is under `reports/p001-clisapay-dry-run-conformance.*`.

P001 is not eligible for live. Suggested downline position is planned slot 3 if it later reaches 100%, because known cost is high (MDR 5.5% plus 0.30 USD fixed fee). Provider-owned gaps include API docs, credentials in the 0609 vault, webhook signing, decline codes, required fields, and geo/currency clarifications. Converter-owned gaps can close only after provider materials arrive.

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

## Commercial eligibility contract

Each PSP profile uses snake_case commercial/go-live fields: `allowed_geos`, `blocked_countries`, `accepted_card_brands`, `accepted_card_types`, `three_ds_required`, `allowed_verticals`, `blocked_verticals`, `min_amount`, `max_amount`, `processing_currencies`, `settlement_currencies`, `mdr_percent`, `mdr_fixed`, `other_fees`, `settlement_days`, `rolling_reserve_percent`, `rolling_reserve_days`, `cap_amount`, `cap_period`, `api_docs_received`, `sandbox_keys_status`, `live_keys_status`, `signed_webhook_sample_received`, `decline_code_map_received`, `agreement_signed`, and `psp_code`.

`PspEligibilityFilter` skips ineligible PSPs before any hop is attempted. It checks BIN country, billing country, card brand/type, merchant vertical, amount min/max, and currency. Skips are audited as `ineligible:<field>`.

`PspCommercialProfile` and `ConversionKillerChecker::checkCommercialProfile()` mark any missing or unknown profile field as `commercial profile incomplete`, generate a blocker row, and add draft PSP questions to `open_questions`.

## Trusted returning customer prefill

`TrustedCustomerPrefillService` reads trusted customers through `TrustedCustomerRepositoryInterface` using a stable key, such as hashed email plus merchant id. The real data source is pluggable.

Rules:

- Prefill only when `consent_recorded` is true.
- Store only minimum personal fields, with `trusted_since` and `last_seen`.
- Personal data goes through `PersonalDataEncryptionInterface`; the in-memory implementation is only for offline fixtures/tests.
- PAN, CVV, full card number, and expiry are never stored or returned.
- Card reuse must use PSP-issued or network tokens behind `CardTokenVaultInterface`.
- Returned audit includes `prefilled_fields[]`, `prefill_source=trusted_customer`, and values stay editable.
- PSP data minimisation still applies through each converter's `createPayloadFieldMap()`.

## Waiting page contract

`hostinger/public_html/bob-c/waiting.html` is a lightweight hosted waiting page. It shows neutral text, warns customers not to close/refresh/go back, reuses an idempotency key from the URL or session storage, polls a same-origin status endpoint, and auto-continues to the next redirect/result URL.

The optional promo/ad slot defaults off and is only allowed on the waiting page, never in card-entry fields or iframes. The page includes a CSP note limiting loading to same-origin resources by default.

## Waiting-slot media library and merchant approval

Every animation, video, or ad used in waiting slots must be registered in `psp_waiting_media` with `media_id`, title, type, file URL or storage path, thumbnail, duration, format, file size, owner, rights/licence note, status, approval metadata, and checksum.

`merchant_media_consent` records per-merchant approval for a `media_id` and slot (`redirect_wait`, `cascade_wait`, `final_status_wait`). `WaitingMediaResolver` only returns requested media when it is registered, approved, and merchant-approved for that slot; otherwise it falls back to `pigeon_card_delivery`.

The seeded default is `Pigeon card delivery`, a platform-owned approved placeholder with TODO asset path and TODO licence/checksum notes. Ads require explicit merchant approval and remain off by default. Waiting status responses include `audit.media_id_shown` for payment-attempt audit.

## Paid impressions and conversion measurement

`psp_media_impressions` records one row per actually shown clip using `impression_id`, `media_id`, advertiser/owner, merchant id, slot, payment attempt id or merchant reference, shown timestamp, visible duration, completed/clicked flags, later payment outcome, and variant. It intentionally stores no customer personal data or card data.

`MediaImpressionService` counts an impression only when visible for at least the configurable minimum, default 2 seconds. It dedupes by `payment_attempt_id + slot` so refresh/back does not double count.

`psp_media_fee_rates` stores configured rates only: per impression, completed view, or click; currency; amount; optional merchant revenue share. `MediaFeeStatementService` can summarize monthly/period statements by advertiser or merchant.

`MediaVariantSelector` records variants (`none`, `default_animation`, or a specific media id) with configurable control-group percentage, default 0. `MediaConversionReportService` measures conversion and abandonment by variant, slot, and merchant; it does not claim uplift.

## Marketing tab

The BOB C Marketing tab is the admin surface for all waiting-slot marketing material: video clips, banner images, YouTube links, and adverts. Assets include a plain-language explanation, file or URL, thumbnail/preview, allowed slots, owner/advertiser, optional fee rate, status, date window, and merchant consent rules.

Safety rules stay in force:

- Marketing assets never render on card-entry pages.
- Ad slots are off by default.
- Unapproved, unregistered, or non-consented assets fall back to `pigeon_card_delivery`.
- YouTube links must render through `youtube-nocookie.com`, without autoplay with sound.
- Uploads are validated by type and size and must be stored outside the web root or in approved media storage.
- Placement history is append-only: each slot/merchant/site/page activation creates a new placement row instead of overwriting old records.

Marketing results have two windows: Now (today and last 24 hours) and History (daily/weekly over a selected date range). Results are split by asset, slot, merchant, and site and use only logged impression events. Empty states must be shown when there is no data.

## PSP credentials and the 0609 vault

Repository inventory: this repo contains Bob/Grok `XAI_API_KEY` references for BOB C and offline test placeholders, but it does not contain a PSP vault, encrypted PSP credential store, or `psp_credentials` table. The real PSP keys are expected to live only in the 0609 Laravel host vault on the VPS.

`PspCredentialProviderInterface` is the only PSP credential access path for adapters. `VaultPspCredentialProvider` is a TODO stub for the live 0609 vault and never reads PSP secrets from config, env dumps, monday.com, or request payloads. `FakePspCredentialProvider` exists only for offline fixtures/tests.

The conformance gate treats `live_keys_status` as `received` only when the credential provider resolves live credentials for that PSP. Reports expose only status metadata such as `received`, `missing`, `rotated`, `last_rotated_at`, and vault-entry links; they never print secret values.

`ConversionKillerChecker::checkHardcodedSecrets()` scans adapter/converter source for key-like literals and reports `hardcoded credential` blockers. The BOB C key-status page shows PSP, environment, status, last rotation, and vault link only.

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
- commercial profile incomplete

Each failed issue row includes PSP code, endpoint, check id/name, internal and PSP field paths, expected and actual values, category, root cause, code location, severity, and suggested fix.

## Running offline

```bash
php bob-c-0609/laravel/bin/run-psp-conformance.php P003 /tmp/psp-runs
```

The command writes timestamped JSON and Markdown under the output directory. It uses recorded fixtures only; no real PSP calls, no real keys, and no live cascade/routing changes.

## Current P003 result

P003 passes the normalized create/status/refund/webhook golden comparisons, declared-dependency checks, source secret scan, and most commercial-profile checks. It is still not eligible for cascade because the existing pre-flight evidence has 2 flagged rows and no live credential is resolved from the 0609 vault provider. Score from the current fixture set is 80/83 = 96.39%.
