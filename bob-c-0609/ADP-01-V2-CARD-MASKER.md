# ADP-01:v2 shared card masker design

Status: DRAFT. This design becomes FROZEN only when ADP-01:v2 receives GM approval.

The card masker is a contract-level security utility shared by every PSP adapter. It contains no PSP mapping and must not live under any provider namespace.

## Why it is shared

PSP adapters do not share mapping code. They may share only:

- the frozen ADP-01 contract;
- contract-level security utilities required by that contract;
- tests that prove contract compliance.

The masker is shared because `ADP-01:v2-R6` requires one consistent log-safety boundary for all adapters. PSP-specific converters still decide how to map provider payloads into normalized contract fields; the masker only removes card data from data that is about to be logged, persisted to audit text, emitted to stdout/stderr, or reported in exceptions.

## Interface

`CardDataMaskerInterface::mask(mixed $value): mixed`

Required behavior:

- Idempotent: masking an already masked value returns an equivalent masked value.
- Never throws: invalid UTF-8, unexpected types, recursive-looking structures, and malformed query strings return a safe masked value.
- Structure-preserving where practical: arrays and objects keep their shape, with sensitive leaf values redacted.
- Provider-agnostic: no provider field names, no PSP routing decisions, and no PSP signature handling.

## Data covered

### PAN

- Detect 13-19 digit candidates with optional spaces or dashes.
- Confirm with Luhn before treating a number as PAN.
- Mask output to keep at most first 6 and last 4 digits.
- Preserve no more separators than needed for readability.
- Ignore non-Luhn numeric identifiers to reduce false positives.

### CVV and CVC

- Fully remove 3-4 digit values under key names such as `cvv`, `cvc`, `cvv2`, `securityCode`, `cardSecurityCode`, and `cvn`.
- Matching is case-insensitive and applies to nested arrays, objects, JSON strings, query strings, and form bodies.
- Output must not reveal length beyond a fixed replacement placeholder.

### Expiry

- Mask `MM/YY`, `MMYY`, and month/year key pairs when they are card-expiry fields.
- Standalone dates that are not keyed as card expiry should not be masked unless paired with a card context.

### Track data

- Mask track 1 and track 2 patterns, including `%B...^...^...?` and `;...=...?`.
- Do not attempt partial retention; track data is fully redacted.

### Nested and encoded structures

The masker must recurse through:

- nested arrays;
- stdClass-style objects;
- JSON strings;
- query strings;
- form bodies.

When a string cannot be parsed as JSON, query string, or form body, the masker still scans it for PAN, CVV-labelled values, expiry-labelled values, and track data.

## Output placeholders

Recommended placeholders:

- PAN: keep at most first 6 and last 4, with the middle replaced by `******`.
- CVV/CVC: `[REDACTED_CVV]`
- Expiry: `[REDACTED_EXPIRY]`
- Track data: `[REDACTED_TRACK]`

Implementations may choose equivalent fixed placeholders as long as the tests prove that no raw PAN, CVV, expiry, or track data remains.

## Test contract

Every implementation must prove:

- public scheme test PANs are masked only when Luhn-valid;
- non-Luhn numeric identifiers survive unchanged unless key-name rules require masking;
- CVV-like values are masked under sensitive key names and in nested structures;
- expiry values are masked under card-expiry key names;
- track 1 and track 2 data is fully redacted;
- JSON, query-string, and form-body inputs are handled;
- masking is idempotent;
- invalid UTF-8 and unexpected input types do not throw.
