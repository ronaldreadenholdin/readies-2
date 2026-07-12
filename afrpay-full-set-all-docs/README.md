# AfrPay Full Set — All Docs + Adaptor Interface

This package is the **AfrPay PSP connection full set** for Readies/Finexeble.

It is **not** the provider test board. It contains the API adaptor interface, DTOs, AfrPay connection stubs, config, and documentation templates needed to connect AfrPay.

## Contents

```text
README.md
docs/psp-adaptor-interface.md
docs/afrpay-api-document-request.md
docs/afrpay-onramp-or001.md
docs/afrpay-openbanking-ob003.md
config/psp_adaptors.php
app/Contracts/PspAdaptorInterface.php
app/DTO/*
app/Services/Psp/PspAdaptorFactory.php
app/Services/Psp/Adaptors/AbstractPspAdaptor.php
app/Services/Psp/Adaptors/AfrPayOnrampAdaptor.php
app/Services/Psp/Adaptors/AfrPayOpenBankingAdaptor.php
reference/   # prior CashForo reference pattern (optional)
```

## Connections

| Code | Type | Class |
|------|------|-------|
| OR001 | Onramp | AfrPayOnrampAdaptor |
| OB003 | Open Banking | AfrPayOpenBankingAdaptor |

## Required env

```bash
AFRPAY_ONRAMP_BASE_URL=
AFRPAY_ONRAMP_API_KEY=
AFRPAY_ONRAMP_WEBHOOK_SECRET=
AFRPAY_OPEN_BANKING_BASE_URL=
AFRPAY_OPEN_BANKING_API_KEY=
AFRPAY_OPEN_BANKING_WEBHOOK_SECRET=
```

## Important

Adaptor contracts and structure are ready. Map live AfrPay endpoint paths, auth, webhook events, and signature rules from AfrPay's official API docs before go-live (use `docs/afrpay-api-document-request.md`).
