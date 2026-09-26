# ADP-01:v2 Preflight definition

Status: DRAFT. This definition becomes FROZEN only when ADP-01:v2 receives GM approval.

ADP-01:v2 Preflight green means real, fixture-backed evidence. Presence-only checks score zero and cannot contribute to the percentage.

## Green criteria

A PSP connection is green only when all criteria are true:

- Redacted real-response fixtures exist for success, soft decline, hard decline, 3DS, pending, unknown, bad signature, amount mismatch, timeout, malformed payload, duplicate webhook, and refund.
- There is a passing test for each normative fail-closed rule `ADP-01:v2-R1` through `ADP-01:v2-R8`.
- A non-production sandbox base URL is configured and differs from production.
- Converter code is committed to a repo.
- The shared masker and CI log-leak tests pass.
- The explicit owner go is recorded for the one PSP under test.

## Score

- `100%`: every green criterion is true.
- `0%`: any green criterion is false, unknown, presence-only, or unsupported by fixtures.

Intermediate percentages are not part of v2 green. A partial implementation may record which criteria are complete, but it is not eligible.

## Presence-only examples that score zero

- A URL string exists but is not proven to be a non-production sandbox URL.
- A key-status flag exists but no vault-backed test can verify the runtime credential path.
- A converter class exists but has no fixture-backed success, decline, 3DS, pending, unknown, bad-signature, mismatch, timeout, malformed, duplicate, and refund tests.
- A log file exists but no shared detector scan proves it is free of card data.

## One PSP at a time

Preflight remains scoped to one PSP under test. V2 docs and interfaces do not change live routing, cascade order, credential access, or deployment behavior.
