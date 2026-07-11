# Middleware registration

The package includes:

```text
app/Http/Middleware/VerifyPspWebhook.php
```

Register it as `verify.psp`.

## Laravel 10 and older style `app/Http/Kernel.php`

Add this to `$routeMiddleware`:

```php
'verify.psp' => \App\Http\Middleware\VerifyPspWebhook::class,
```

## Laravel 11/12 `bootstrap/app.php`

Add the alias in the middleware block:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'verify.psp' => \App\Http\Middleware\VerifyPspWebhook::class,
    ]);
})
```

After registering, clear route/config cache:

```bash
php artisan route:clear
php artisan config:clear
```
