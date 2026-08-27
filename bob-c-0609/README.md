# BOB C on 0609 — Ask Bob G

Adds a **BOB C** sidebar tab to the 0609.readies.biz backend.

**BOB C is an extension of Bob G.** It does not start a new agent. It loads Bob G’s existing work (harness, recommendations, adaptors, boards) and continues the open items.

See `bob-g-work/WORK-AUDIT.md` and `bob-g-work/catalog.json`.

This repository is **not** the full 0609 Laravel host. This pack is the drop-in for that host.

## What you get

| Path | Use |
|------|-----|
| `hostinger/public_html/bob-c/` | Upload this folder to Hostinger as `public_html/bob-c/` |
| `laravel/` | Merge into the existing 0609 Laravel app (sidebar + `/bob-c` route) |
| `standalone-demo/` | Local preview of the same admin UI |

After Hostinger upload, open:

```text
https://0609.readies.biz/bob-c/
```

After Laravel merge, open:

```text
https://0609.readies.biz/bob-c
```

## Fastest way onto 0609 (Hostinger)

1. Log in to Hostinger hPanel.
2. Open File Manager.
3. Open the site document root (`public_html`, or Laravel `public/`).
4. Create folder `bob-c` if it does not exist.
5. Upload **everything inside** `hostinger/public_html/bob-c/` into that folder.
6. Copy `.env.example` to `.env`.
7. Put the Grok/xAI key in `.env` as `XAI_API_KEY=...`
8. Set `BOB_C_ACCESS_TOKEN` to a long random string.
9. Open `https://0609.readies.biz/bob-c/?token=YOUR_TOKEN`

Then add a sidebar link in `layouts.adminpanel` that points to `/bob-c/` (see `laravel/INSTALL-SIDEBAR.md`).

## Connect Bob G

Bob G uses the xAI Grok API.

```env
XAI_API_KEY=xai-...
XAI_MODEL=grok-3
XAI_BASE_URL=https://api.x.ai/v1
BOB_C_ACCESS_TOKEN=change-me
```

Without `XAI_API_KEY`, BOB C still loads and answers from the built-in Readies/PSP helper so the tab works. With the key, answers come from Grok (Bob G).

## Laravel merge

Copy `laravel/app`, `laravel/config`, `laravel/database`, `laravel/resources`, and `laravel/routes` into the 0609 Laravel root. Register the routes and sidebar as documented in `laravel/INSTALL-SIDEBAR.md`. Then:

```bash
php artisan migrate
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

## Safety

- Backend/admin use only. Protect the page with `BOB_C_ACCESS_TOKEN` or Laravel `auth`.
- Bob G drafts code. A human must review before Hostinger deploy.
- Bob G will not enable live PSP traffic from chat.
- AfrPay is Europe / Kazakhstan / Tunisia. Do not treat AfrPay as CashForo or Flamingo.
