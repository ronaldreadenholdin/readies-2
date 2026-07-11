# Readies PSP Pre-Flight Test Harness - Complete Package

This folder contains the complete PSP test harness package prepared for handoff.

It includes:

- Full PSP harness service with detailed checks.
- FBLS / P003 coverage.
- Xcore / P004 Europe coverage.
- Xcore-specific checks for first/last name validation, webhook contract, signature contract, 3DS, and restricted countries.
- HMAC-SHA256 webhook signature verification middleware.
- PSP config file for webhook secrets.
- Laravel controller and routes.
- Blade dashboard.
- Static preview pages.
- Migration for storing test harness results.

## Folder contents

```text
app/
  Http/
    Controllers/
      PspSandboxController.php
    Middleware/
      VerifyPspWebhook.php
  Models/
    PspProvider.php
  Services/
    BobRecommendationService.php
    PspTestHarnessService.php
config/
  psp.php
database/
  migrations/
    2026_07_07_000000_create_psp_test_harnesses_table.php
public/
  index.html
  pre-flight-test.html
  pre-flight-test/index.html
resources/
  views/
    psp_sandbox/index.blade.php
routes/
  web.php
standalone/
  pre-flight-test.html
```

## Which file to open

- Original recovered test: `public/pre-flight-test/index.html`
- Polished current dashboard: `standalone/pre-flight-test.html`
- Reusable provider test board: `standalone/provider-test-board.html`
- Next PSP starter: use the root repository file `next-psp-start-here.html` or copy it into this package before handoff.

For the next PSP, open the starter file, change the PSP name/code/region/signature header, then click **Run Full Test**.

For any provider, open `standalone/provider-test-board.html`. It has two configurable connection tests. Current Flamingo values are:

1. Connection 1 / Onramp: `OR001`
2. Connection 2 / Open Banking: `OB003`

## Setup in an existing Laravel project

1. Extract this ZIP.
2. Copy the folders into the root of the Laravel project:

```text
app/
config/
database/
public/
resources/
routes/
```

3. Add webhook secrets to `.env`:

```env
FBLS_WEBHOOK_SECRET=replace_with_fbls_shared_secret
XCORE_WEBHOOK_SECRET=replace_with_xcore_shared_secret
```

4. Run:

```bash
composer dump-autoload
php artisan migrate
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

5. Open:

```text
/psp-sandbox
/pre-flight-test
```

## Static preview only

If Laravel is not ready yet, open one of these files directly:

```text
standalone/pre-flight-test.html
public/pre-flight-test/index.html
```

Or serve the folder:

```bash
python3 -m http.server 8765 --directory public
```

Then open:

```text
http://127.0.0.1:8765/pre-flight-test/
```

## Xcore / P004 notes

The harness includes Xcore checks for:

- Provider identified as Xcore / P004.
- Required merchant/API fields.
- Webhook payload includes transaction status, amount, currency, BIN/last4 or masked PAN.
- Signature contract exists and is HMAC-SHA256 compatible.
- First name and last name must be Latin letters, minimum 2 characters, not email values.
- Europe 3DS readiness.
- Restricted country confirmation.

## Webhook HMAC-SHA256 verification

Middleware file:

```text
app/Http/Middleware/VerifyPspWebhook.php
```

Config file:

```text
config/psp.php
```

Example protected webhook routes are included in:

```text
routes/web.php
```

## Important

This package is intended as a complete handoff folder. It is not a full Laravel framework installation with `artisan` and `vendor/`.
Use it by copying these files into the existing Readies Laravel application, or keep using the static HTML preview until the Laravel app is ready.
