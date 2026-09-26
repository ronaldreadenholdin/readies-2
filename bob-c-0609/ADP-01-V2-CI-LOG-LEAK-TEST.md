# ADP-01:v2 CI log-leak test

Status: DRAFT. This test definition becomes FROZEN only when ADP-01:v2 receives GM approval.

The CI log-leak test fails the build if PAN, CVV/CVC, expiry, or track-data patterns reach any log sink while running adapter fixture suites. It is a contract-level test, not a PSP-specific test.

## Required sinks

The detector must scan:

- Laravel log channels: single, daily, stack, and stderr;
- audit-table text fields captured during fixture runs;
- captured stdout and stderr;
- exception report messages and context.

No network calls are allowed. Fixture suites must use recorded, redacted inputs only.

## Detector strategy

The CI scanner uses two detection layers:

1. The same card-data detector used by the shared masker.
2. An independent regex layer so a masker bug cannot hide itself.

PAN detection must require:

- 13-19 digits;
- optional spaces or dashes between digits;
- Luhn validity.

CVV detection must include labelled 3-4 digit values under names such as `cvv`, `cvc`, `cvv2`, `securityCode`, `cardSecurityCode`, and `cvn`.

Track detection must include track 1 and track 2 patterns:

- `%B...^...^...?`
- `;...=...?`

## Canary self-test

The suite includes a negative self-test that injects a public scheme test PAN and a labelled CVV into a captured log string. The test must fail detection if the detector does not flag both canaries.

Allowed public test PAN canaries include:

- `4111 1111 1111 1111`
- `5555 5555 5555 4444`

These values are public scheme test numbers only. No real card data may be used.

## Build behavior

CI is green only when:

- every converter fixture suite runs with log capture enabled;
- all captured sinks scan clean after masking;
- the negative self-test proves the detector catches the canary;
- no raw signature material is logged by verification tests;
- no sink contains an unmasked PAN, labelled CVV, expiry, or track-data value.

The initial repository implementation is a skeleton: Python unittest invokes PHP scripts when PHP is available, and those PHP scripts exercise the detector contract without making PSP calls.
