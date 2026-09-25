# PSP merchant tab integration

Status: pending server access.

Gerardus confirmed the live 0609 Laravel host already has a per-merchant top tab named `PSP` or `Payment providers` under `/var/www/html/adapter`. That code is not present in this repository slice.

Do not create a duplicate merchant PSP list. Instead, when the live host is available:

1. Use `MerchantPspOrderService` to calculate suggested positions from eligibility, cost, and conformance.
2. Read the existing tab's actual positions as the orchestration-owner/TADDY positions.
3. Include `resources/views/psp/partials/merchant_psp_order_suggestions.blade.php` in the existing tab.
4. Include `resources/views/psp/partials/merchant_psp_disagreements.blade.php` in the same tab or a sub-panel.
5. Include `resources/views/psp/partials/merchant_psp_trials.blade.php` for new PSP trials.
6. Persist overrides and trial state changes to the append-only `merchant_psp_overrides` table.
7. Keep the 100% rule: below-100% connections are `planned` only and cannot hold live positions, trial or not.

The disagreement partial does not send email or notifications. It only surfaces rows marked `Needs Gerardus discussion`.

Trial config:

- `trial_min_transactions`: unset by default (`null`)
- `trial_max_days`: unset by default (`null`)

When both are unset, show `Trial length not set, needs Gerardus` and never auto-end the trial.

Example only, not a default: `trial_min_transactions=100` or `trial_max_days=14`.
