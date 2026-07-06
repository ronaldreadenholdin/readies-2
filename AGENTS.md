# readies-2

`readies-2` is a high-risk payment gateway workspace focused on PSP (Payment Service
Provider) integrations. The current deliverable is the **PSP Pre-Flight Test Harness**
(`pre-flight-test.html`) — a self-contained interface used to validate a PSP integration
in the test environment before an identical clone is promoted to the live backend.

## Cursor Cloud specific instructions

### What runs here

- `pre-flight-test.html` is a **static, self-contained** HTML/JS file. It has no build
  step and needs no backend, database, or PHP to run. Serve the repo root with a static
  server and open `/pre-flight-test.html` (see README for the exact command). This is the
  primary thing to run/demonstrate in this environment.
- The harness is entirely client-side: all checks, presets (FBLS/P003, XCore/P004, custom),
  metrics, "Bob" guidance, and the go-live gate run in the browser. There is no API to call,
  so it works fully offline.

### Intended live-backend stack (not runnable here yet)

- `app/`, `routes/`, and `resources/views/` fragments (present on some feature branches,
  e.g. the PSP harness branch) are **Laravel/PHP** and are meant to be dropped into an
  existing Laravel host app. They reference host-app pieces like `App\Models\PspProvider`
  and the `layouts.adminpanel` Blade layout, so they cannot run standalone in this repo.
- The Laravel controller serves the **same** `pre-flight-test.html` via
  `base_path('pre-flight-test.html')` (route `/pre-flight-test`). Keep the root file as the
  single source of truth so the test page and the live page stay identical.

### Toolchain availability

- `python3`, `node`, and `npm` are preinstalled (use `python3 -m http.server` to serve the harness).
- **PHP and Composer are NOT installed** in the base image. Install them yourself before any
  `composer install` / `php artisan ...` work if/when a full Laravel app lands here.

### Update script

- The startup update script is intentionally guarded: it only installs JS deps when a
  `package.json` exists and Python deps when a `requirements.txt`/`pyproject.toml` exists.
  This keeps startup a safe no-op while the repo is only static HTML, and starts installing
  automatically once a manifest is added.
