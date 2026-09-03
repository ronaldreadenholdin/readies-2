# Install FTD vs trusted into `/var/www/html/adapter`

Merchants do not upload. 0609 admin staff upload the list for a merchant.

Copy `app/`, `database/`, `resources/views/admin/ftd-trusted/`, `routes/ftd_trusted.php`, and `routes/admin-ftd-trusted.php` into `/var/www/html/adapter`.

In `routes/web.php`:

```php
require __DIR__ . '/ftd_trusted.php';
```

Inside the existing 0609 **admin auth** group (the same group that already wraps `layouts.adminpanel`):

```php
require __DIR__ . '/admin-ftd-trusted.php';
```

Add the sidebar item from `ftd-trusted/0609-admin-sidebar.blade.php` to the admin panel nav.

Then:

```bash
cd /var/www/html/adapter
php artisan migrate
php artisan route:clear
php artisan view:clear
```

Admin page: `/admin/ftd-trusted`

Call this after a provider payment succeeds, for every provider:

```php
app(\App\Services\TrustedListService::class)->markPaid($payload);
```

Call this before routing / risk:

```php
app(\App\Services\TrustedListService::class)->classify($payload);
```
