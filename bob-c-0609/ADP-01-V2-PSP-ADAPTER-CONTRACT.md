# ADP-01:v2 PSP adapter contract

Status: DRAFT. This contract becomes FROZEN only on GM approval.

ADP-01:v2 is a contract-only draft for card PSP adapters. It defines the normalized page result, normalized payment event, verification contract, fail-closed behavior, shared security utilities, and preflight meaning of green. This draft adds no live PSP code, no routing changes, no PSP calls, and no provider-specific mapping.

## Scope

- Every PSP converter remains independent and owns its own PSP field mapping.
- PSPs share only the frozen ADP-01 contract and contract-level security utilities.
- Provider-specific field names, credentials, merchant numbers, hostnames, production snapshots, and operational incident details are out of scope.
- Signature scheme names may be used only as generic examples, such as an HMAC-SHA256 header or MD5 over body fields.

## Version

- `schema_version`: `ADP-01:v2`
- Status: DRAFT
- Freeze condition: GM approval only

## Changelog from ADP-01:v1

1. Page output is now explicit. `PaymentPageResult` carries `next_action`, redirect fields, 3DS state, and display status. The payment page renders only this normalized result.
2. Statuses are aligned into one canonical status enum plus a separate `decline_class`. The proposed v2 status list is `pending`, `authorized`, `captured`, `settled`, `declined`, `cancelled`, `refunded`, `skipped`, `error`; this list is for GM approval. v1 `failed` becomes `declined` with `decline_class=soft`; v1 `unknown` is no longer emitted as a final status.
3. Unknown PSP codes fail closed: unknown becomes `pending`, then a status re-query, then `declined` with `decline_class=hard` if still unresolved. Unknown never triggers a blind retry or cascade.
4. Signature verification happens before conversion. Without a verified signature or signed/authenticated status poll, no state-changing `PaymentEvent` is emitted.
5. Converter verification is scheme agnostic. Each v2 converter exposes `verify()`; the key is selected from our merchant account, comparison is constant-time, failure is closed, the environment selects sandbox or production material, and signatures are never logged.
6. IDs and timestamps are tightened. `payment_id` is always ours, `psp_reference` is the PSP identifier, `received_at` is required UTC set by us, and missing provider fields are null rather than invented.
7. Stale P001 dry-run facts do not carry forward. P001 must be re-run against v2 after its independent converter exists; the converter remains null until then.
8. Secrets remain sourced from the 0609 vault. Env values are runtime copies only, never committed, hardcoded, or used as fallback literals. Keys are never logged. GM or owner may tighten this to vault-only.
9. Green means real evidence. Presence-only checks score zero; see the v2 preflight definition.

## Non-conforming v1 code to leave unchanged in this branch

This branch documents v2 and adds interfaces only. It does not rewrite v1 behavior.

- The v1 P003 template maps unknown status to a soft-decline path that can cascade. v2 requires unknown to become pending, then re-query, then hard decline if unresolved.
- The v1 abstract adaptor normalizes webhook payloads even when verification fails. v2 requires verification before conversion and emits no state-changing event when verification is false.
- v1 checker text assumes one raw-body HMAC style for signatures. v2 wording must remain scheme agnostic.
- v1 normalized output has no `PaymentPageResult`, `next_action`, redirect method, 3DS page result, or page display status.
- v1 includes `failed` and final `unknown` statuses. v2 replaces those with `declined` plus `decline_class`, or `error` with a structured `error_reason`.
- v1 preflight scoring accepts presence-style evidence. v2 green requires fixture-backed evidence and rule tests.

## Canonical types

### `PaymentPageResult`

Fields:

- `schema_version`: always `ADP-01:v2`
- `payment_id`: our payment or order identifier
- `attempt_id`: our attempt identifier
- `display_status`: `approved`, `pending`, `3ds_redirect`, `declined_soft`, `declined_hard`, or `error`
- `next_action`: `none`, `redirect`, `poll`, or `cascade`; set by the router, not by PSP mapping
- `redirect_url`: nullable; required when `next_action=redirect`
- `redirect_method`: `GET`, `POST`, or null
- `form_fields`: map of string to string, only for POST redirects and never card data
- `three_ds`: boolean
- `message_code`: `card_declined`, `try_other_card`, `insufficient_funds`, `verify_card`, `retry_later`, `fix_details:<field>`, or `contact_support`

`PaymentPageResult` must not carry raw PSP text.

### `PaymentEvent`

Fields:

- `schema_version`: always `ADP-01:v2`
- `event_id`: `sha256(psp_code|psp_reference|status|source)` and used as the idempotency key
- `psp_code`: provider code
- `merchant_account_id`: internal merchant account id, never the PSP merchant number
- `payment_id`: our payment or order identifier
- `attempt_id`: our attempt identifier
- `psp_reference`: PSP reference or null
- `source`: `webhook`, `return`, `poll`, or `rebook`
- `status`: canonical status
- `decline_class`: `none`, `soft`, or `hard`
- `error_reason`: nullable; includes `amount_mismatch`, `transport_error_before_acceptance`, `missing_redirect`, and `psp_config_error`
- `amount`: object with decimal string `value`, ISO 4217 `currency`, and integer `minor_units`
- `raw_code`: PSP code value
- `raw_message`: PSP message after card-data masking; never raw card data
- `normalized_reason`: stable internal reason
- `signature_verified`: boolean; true for any state-changing event
- `received_at`: required UTC `Z` timestamp set by us
- `psp_timestamp`: nullable UTC `Z` timestamp from PSP
- `cascade_reason`: nullable; rebook/cascade hops write a soft-decline event with a reason before opening the new attempt

Amount or currency mismatch against the attempt is always `status=error` with `error_reason=amount_mismatch`.

## Normative fail-closed rules

- `ADP-01:v2-R1`: Unknown code -> pending -> re-query -> hard decline. Never blind retry.
- `ADP-01:v2-R2`: No state change without a verified signature or signed/authenticated status poll. Verification bypasses are impossible outside local/testing environments.
- `ADP-01:v2-R3`: ACK the PSP only after the event is verified and persisted. Verification or storage failure returns non-2xx so the PSP retries.
- `ADP-01:v2-R4`: Re-query before giving up. A pending attempt older than the configured threshold with no event causes a status query that emits `PaymentEvent(source=poll)`, then timeout handling.
- `ADP-01:v2-R5`: Transport connects within 5 seconds, completes within 20 seconds, and has at most one retry. Retry is allowed only on connect failure and only with the same idempotency key.
- `ADP-01:v2-R6`: No card data in logs, ever. The shared masker is mandatory and the shared CI log-leak test enforces this.
- `ADP-01:v2-R7`: Sandbox and production are separated by base URL, keys, and merchant accounts. Test and preflight paths hard-fail if sandbox and production base URLs are equal.
- `ADP-01:v2-R8`: Cascade only on `declined` with `decline_class=soft`, or on transport `error` before the PSP accepted the order. Never cascade on hard declines, pending, or 3DS. The router reads `PaymentEvent.decline_class` and never parses raw PSP text.

## Verification contract

V2 converters expose `verify()` without assuming a specific signature scheme.

- The verification key is selected by our merchant account.
- The comparison is constant-time.
- Missing, malformed, or non-matching signatures fail closed.
- The runtime environment selects sandbox versus production verification material.
- Received and expected signatures are never logged.
- A failed verification may produce an audit record, but that audit record must not include signature material.

## Validator contract

The v2 validator rejects payloads that violate contract invariants, including:

- `schema_version` is not `ADP-01:v2`.
- A state-changing event has `signature_verified=false`.
- `next_action=redirect` without `redirect_url`.
- `redirect_method=POST` with non-string form fields.
- `received_at` or `psp_timestamp` is not UTC `Z`.
- Amount format is not a two-decimal string, currency is not uppercase ISO 4217 shape, or minor units are not an integer.
- `status=error` caused by amount or currency mismatch does not use `error_reason=amount_mismatch`.
- `decline_class=soft` is used with a non-declined status unless the source is a rebook/cascade handoff.

## Shared masker and CI leak test

The shared card masker and log-leak detector are contract-level security utilities. They sit outside provider namespaces, contain no PSP mapping, and are the only shared code PSP adapters may call before logging. See `ADP-01-V2-CARD-MASKER.md` and `ADP-01-V2-CI-LOG-LEAK-TEST.md`.

## Preflight definition

Green requires real, redacted response evidence, tests for every fail-closed rule, sandbox/production separation, committed converter code, and a passing shared masker/log-leak suite. Presence-only checks score zero. See `ADP-01-V2-PREFLIGHT.md`.
