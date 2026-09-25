# P001 Clisapay dry-run conformance

Connection: `ADP-01 / P001`  
Provider: Clisapay / JIXINGBAO TRADING PTE. LTD.  
Mode: dry run, no real PSP calls.

## Result

- Score: 9 / 26 = 34.62%
- Eligible for live: no
- Rule: eligible only at 100%; no overrides
- Suggested downline position: planned slot 3 if it reaches 100%, because known cost is high (MDR 5.5% + 0.30 USD fixed fee).

## Known facts used

- Geos: GLOBAL recorded; July note says AE (Visa) plus "DC" (Mastercard), unclear. USA coverage stopped; no date.
- Cards: Visa and Mastercard, 2D and 3DS. JCB promised but not confirmed.
- Verticals: forex, igaming, gambling.
- Pricing: MDR 5.5% (one source says 5%), plus 0.30 USD per transaction. Refund 1 USD. Chargeback 35 USD.
- Settlement: T+7 (T+5 offered), 1% settlement fee, settled in USDT.
- Reserve: 10% for 180 days, capped at 200k.
- Agreement signed: 3 June 2026.

## Failed / unknown checks

Provider must answer or change: geo scope, blocked countries, JCB support, 3DS rules, min/max amounts, processing currencies, cap period, API docs, sandbox/live key status in the 0609 vault, signed webhook sample, decline code map, required fields, contact.

Converter can close after provider materials arrive: converter mapping and normalized golden fixtures.

No unknown facts were inferred.
