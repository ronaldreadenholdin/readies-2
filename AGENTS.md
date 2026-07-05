# readies-2

`readies-2` is described on GitHub as a "High risk payment gateway".

## Cursor Cloud specific instructions

### Current repository state

- The default branch (`main`) currently contains **only** `README.md`. There is no application code, no dependency manifest (`composer.json`, `package.json`, etc.), no test suite, and no build tooling on `main` yet.
- Because there is no manifest to install from and no entrypoint, there is nothing to build or run on `main` today. Do not fabricate a run/build step; there is no application to serve until code is added.

### Intended stack (from existing work)

- The in-progress branch `okepaypsp-test-harness-d53e` adds **Laravel (PHP)** fragments: `app/Http/Controllers/PspSandboxController.php`, `app/Services/PspTestHarnessService.php`, `routes/web.php`, and a Blade view `resources/views/psp_sandbox/index.blade.php`. These files assume they are dropped into an existing Laravel host app (they extend `layouts.adminpanel`, reference `App\Models\PspProvider`, and extend the framework `Controller` base class), so the intended stack is Laravel/PHP.

### Toolchain availability

- `node` (v22) and `npm` (v10) are preinstalled.
- **PHP and Composer are NOT installed** in the base image. If/when a Laravel app lands, you must install PHP + Composer yourself before `composer install`, `php artisan serve`, `php artisan test`, etc. will work.

### Update script

- The startup update script is intentionally guarded: it only runs `composer install` when a `composer.json` exists (and Composer is on `PATH`), and only runs `npm install` when a `package.json` exists (and npm is on `PATH`). This keeps startup a safe no-op while the repo is empty and starts installing dependencies automatically once manifests are added.
