# Install FTD vs trusted into `/var/www/html/adapter`

Copy `app/`, `database/`, and `routes/ftd_trusted.php` into `/var/www/html/adapter`.

In `routes/web.php`:

```php
require __DIR__ . '/ftd_trusted.php';
```

Then:

```bash
cd /var/www/html/adapter
php artisan migrate
php artisan route:clear
```

Call this after a provider payment succeeds, for every provider:

```php
app(\App\Services\TrustedListService::class)->markPaid($payload);
```

Call this before routing / risk:

```php
app(\App\Services\TrustedListService::class)->classify($payload);
```
