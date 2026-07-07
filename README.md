# readies-2

Operational PSP pre-flight test harness for Readies payment integrations.

Supports **FBLS (P003)** and **Xcore (P004)** with live connectivity checks, webhook receiving, signature verification, and go-live readiness scoring.

## Quick start

```bash
npm install
npm start
```

Open **http://localhost:3000/** in your browser.

## What you can do

1. **Connect PSP** – Add FBLS or Xcore from a template, enter sandbox credentials (merchant ID, API key, secret, base URL).
2. **Run Tests** – Execute full pre-flight, second operational, and Xcore-specific test suites against your configuration.
3. **Webhooks** – Give the PSP the harness webhook URL, receive real callbacks, simulate test webhooks, and verify HMAC-SHA256 signatures.
4. **Go Live** – Run all suites and review blocking failures before production routing.

## Routes

| URL | Purpose |
|-----|---------|
| `/` | Main harness UI |
| `/pre-flight-test` | Same UI (legacy route) |
| `/api/health` | Health check |
| `/api/providers` | List / save PSP connections |
| `/api/providers/:id/test/:suite` | Run test suite (`full`, `second`, `xcore`, `connectivity`) |
| `/api/webhooks/:providerId` | Webhook receiver (POST from PSP) |

Add `?network=1` to test requests to ping the PSP base URL with a safe GET.

## Data storage

Provider configs and webhook logs are stored in `data/` (gitignored). No external database required.

## Environment

| Variable | Default | Description |
|----------|---------|-------------|
| `PORT` | `3000` | Server port |
| `HOST` | `0.0.0.0` | Bind address |

## Legacy static page

The original mock-only `pre-flight-test.html` at the repo root is superseded by this harness. Use `npm start` for the operational version.
