# readies-2

`readies-2` is described on GitHub as a "High risk payment gateway".

## Cursor Cloud specific instructions

### Current repository state

- `main` currently contains only `README.md` and `pre-flight-test.html`. There is **no dependency manifest** (`composer.json`, `package.json`, etc.), no build tooling, and no automated test suite on `main`.
- The only runnable artifact today is `pre-flight-test.html` — a fully self-contained static "Readies PSP Pre-Flight Test Harness" page. All of its JavaScript logic is mocked/hard-coded, so it needs **no backend, no build step, and no dependencies**.

### Running the static harness (dev)

- Serve the repo root with any static file server, then open the page:
  - `python3 -m http.server 8000` → http://localhost:8000/pre-flight-test.html
  - (or `npx serve`, or open the file directly in a browser)
- Core functionality to exercise: click **Run Full Test** (renders mocked pass/flagged rows + a "Go Live" button), click **Go Live with P003** (success alert), and use the **Talk to Grok / Bob** chat (mocked reply). No network calls occur.

### Intended (future) stack

- Feature branches (e.g. `okepaypsp-test-harness-d53e`) add **Laravel (PHP)** fragments (`PspSandboxController`, `PspTestHarnessService`, `routes/web.php`, Blade views) that assume an existing Laravel host app (they extend `layouts.adminpanel`, reference `App\Models\PspProvider`, and extend the framework `Controller`). Those fragments are **not runnable standalone** from this repo and are not on `main`.
- **PHP and Composer are NOT installed** in the base image; `node` (v22) and `npm` (v10) are. If/when a Laravel app + `composer.json` lands, install PHP + Composer before `composer install` / `php artisan serve` will work.

### Update script

- The startup update script is intentionally guarded: it runs `npm install` only if `package.json` exists and `composer install` only if `composer.json` exists (and the tool is on `PATH`). This is a safe no-op today and begins installing dependencies automatically once a manifest is added.
