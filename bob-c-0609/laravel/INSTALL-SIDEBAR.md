# Add BOB C to the 0609 backend sidebar

The 0609 host uses `layouts.adminpanel`. This pack cannot edit that file from this repo because the full Laravel app is not here.

## 1. Register routes

In `app/Providers/RouteServiceProvider.php` or `bootstrap/app.php`, load:

```php
require base_path('routes/bob_c.php');
```

Or add this include at the bottom of `routes/web.php`:

```php
require __DIR__ . '/bob_c.php';
```

## 2. Sidebar tab

Paste this into `resources/views/layouts/adminpanel.blade.php` in the sidebar `<ul>` / `<nav>`:

```blade
<li class="nav-item {{ request()->is('bob-c*') ? 'active' : '' }}">
    <a class="nav-link" href="{{ url('/bob-c') }}">
        <span>BOB C</span>
    </a>
</li>
```

If the sidebar is a plain list of links:

```blade
<a href="{{ url('/bob-c') }}" class="{{ request()->is('bob-c*') ? 'active' : '' }}">BOB C</a>
```

If you uploaded the Hostinger folder instead of merging Laravel:

```html
<a href="/bob-c/">BOB C</a>
```

## 3. Env

```env
XAI_API_KEY=
XAI_MODEL=grok-3
XAI_BASE_URL=https://api.x.ai/v1
BOB_C_REQUIRE_AUTH=true
```

## 4. Cache

```bash
php artisan migrate
php artisan route:clear
php artisan config:clear
php artisan view:clear
```

Open `https://0609.readies.biz/bob-c`
