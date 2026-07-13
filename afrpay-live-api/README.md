# AfrPay Adjusted Live API (post test-approval)

Urgent delivery: real HTTP adaptors + go-live gate + payment/webhook routes.

This replaces the earlier stub that only returned `API_DOCS_REQUIRED`.

## What changed vs stubs

- `AfrPayOnrampAdaptor` / `AfrPayOpenBankingAdaptor` now **call AfrPay over HTTP**
- Flexible response field picking (`transaction_id` / `id` / `data.*`, etc.)
- Configurable paths + auth scheme via env
- `AfrPayGoLiveGate` blocks live traffic until tests are approved
- Controllers + routes ready for Hostinger / Laravel host

## Install

See `GO_LIVE.md`.

## Package layout

```text
.env.example
GO_LIVE.md
README.md
config/psp_adaptors.php
routes/afrpay.php
app/Contracts/PspAdaptorInterface.php
app/DTO/*
app/Http/Controllers/AfrPayPaymentController.php
app/Http/Controllers/AfrPayWebhookController.php
app/Http/Middleware/VerifyAfrPayWebhook.php
app/Services/Psp/AfrPayGoLiveGate.php
app/Services/Psp/PspAdaptorFactory.php
app/Services/Psp/Adaptors/AbstractPspAdaptor.php
app/Services/Psp/Adaptors/AfrPayOnrampAdaptor.php
app/Services/Psp/Adaptors/AfrPayOpenBankingAdaptor.php
docs/
```

## Connections

- **OR001** — Onramp (card → USDT/USDC → Readies flow)
- **OB003** — Open Banking (separate adaptor)

Fill AfrPay credentials in `.env`, green the pre-flight, then unlock live.
