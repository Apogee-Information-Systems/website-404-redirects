# Website 404 Redirects

Log public 404 paths and manage 301 redirects from a database table — for Laravel 11 and 12.

## Features

- **Log** unmatched public `GET`/`HEAD` URLs into `website_404_redirects` with hit counts and first/last seen timestamps.
- **Redirect** paths that have `redirect_to` set via early middleware (default **301**), before routing/controllers run.
- **Optional Filament admin** to review hits, set/clear redirects, and ignore noise paths.
- **Host-agnostic authorization** via the `RedirectAdminAuthorizer` contract.

## Requirements

- PHP ^8.2
- Laravel ^11.0 or ^12.0
- Optional: [Filament](https://filamentphp.com/) ^3.0 or ^4.0 for the admin UI

## Installation

```bash
composer require apogee/website-404-redirects
```

Laravel auto-discovers `Website404RedirectsServiceProvider` via Composer `extra.laravel.providers`.

Publish config (optional — package merges defaults automatically):

```bash
php artisan vendor:publish --tag=website-404-redirects-config
```

Run migrations:

```bash
php artisan migrate
```

If the `website_404_redirects` table already exists (e.g. you extracted from an in-app module), `migrate` is a no-op for that migration.

## Default behaviour

The service provider registers:

- Config merge and migration loading
- `RedirectWebsite404s` middleware (prepended globally and on the `web` group)
- `LogWebsite404Hit` on `NotFoundHttpException` via the exception handler
- Cache invalidation when redirect rows change

**Most apps need no `bootstrap/app.php` changes.** The package auto-wires middleware and 404 logging on install.

### Manual registration (Laravel 11 / 12)

Use this only if you disable auto-registration, need strict middleware ordering, or run a custom HTTP kernel setup.

In `bootstrap/app.php`:

```php
use Apogee\Website404Redirects\Http\Middleware\RedirectWebsite404s;
use Apogee\Website404Redirects\Listeners\LogWebsite404Hit;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// ...

->withMiddleware(function (Middleware $middleware): void {
    // Global prepend so redirects apply before routing (matched routes and route-not-found paths).
    $middleware->prepend(RedirectWebsite404s::class);
})
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
        return app(LogWebsite404Hit::class)->handle($e, $request);
    });
});
```

If you register manually, remove or adjust the auto-registration in a fork, or ensure you do not double-register middleware/listeners.

## Optional Filament admin

Install Filament in your app, then register the plugin on your panel (e.g. `AdminPanelProvider`):

```php
use Apogee\Website404Redirects\Filament\Website404RedirectsFilamentPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(Website404RedirectsFilamentPlugin::make());
}
```

### Authorization

The Filament resource calls `RedirectAdminAuthorizer::canManageRedirects($user)`. The package binds a no-op implementation that denies everyone until you override it.

In `AppServiceProvider::register()`:

```php
use Apogee\Website404Redirects\Contracts\RedirectAdminAuthorizer;
use Illuminate\Contracts\Auth\Authenticatable;

$this->app->bind(RedirectAdminAuthorizer::class, function () {
    return new class implements RedirectAdminAuthorizer
    {
        public function canManageRedirects(?Authenticatable $user): bool
        {
            return $user !== null && (bool) ($user->is_admin ?? false);
        }
    };
});
```

Replace the `is_admin` check with your own policy (roles, gates, `is_sysadmin`, etc.).

## Configuration

Published file: `config/website-404-redirects.php`

| Key | Default | Description |
|-----|---------|-------------|
| `enabled` | `true` | Master switch (`WEBSITE_404_REDIRECTS_ENABLED`) |
| `table` | `website_404_redirects` | Database table name |
| `normalize_lowercase` | `true` | Lowercase path segments so `/Foo` and `/foo` share one row |
| `max_path_length` | `512` | Max stored path length |
| `default_redirect_status` | `301` | HTTP status for redirects |
| `redirect_methods` | `GET`, `HEAD` | Methods that trigger DB redirects |
| `log_methods` | `GET`, `HEAD` | Methods that can be logged as 404 hits |
| `log_matched_route_patterns` | `[]` | Also log controller `abort(404)` when path matches (`Str::is`) |
| `exclude_patterns` | see config | Paths never logged or redirected (`Str::is`, no leading slash) |
| `allow_external_redirects` | `false` | Allow `redirect_to` as `https://…` URLs |
| `allowed_external_hosts` | `[]` | Host allowlist when external redirects are enabled |
| `cache.store` | `null` | Optional cache store for active redirect map |
| `cache.key` | `website_404_redirects:active` | Cache key |
| `cache.ttl` | `3600` | Cache TTL (seconds) |

### `exclude_patterns`

`Str::is` patterns without a leading slash. Homepage `/` is matched using the empty string `''`.

Default exclusions include admin, API, health check, static assets, and Livewire. Customize after publishing config.

### `allow_external_redirects`

When `false` (default), `redirect_to` must be a site-relative path starting with `/`. When `true`, absolute URLs are allowed only if the host is listed in `allowed_external_hosts`.

### Path normalization

Paths are normalized on save and on incoming requests when `normalize_lowercase` is true:

- Leading slash required internally
- Trailing slashes removed (except `/`)
- Segments lowercased when enabled

**Changing normalization rules is a breaking change** — treat as a major semver bump (see `CHANGELOG.md`).

## Development

Requires `ext-pdo_sqlite` for the test suite.

```bash
composer validate --strict
composer install
vendor/bin/phpunit
```

Tests use Orchestra Testbench with SQLite `:memory:` (`phpunit.xml.dist`).

## License

MIT — see [LICENSE](LICENSE).

## Links

- [GitHub Issues](https://github.com/apogee-gr/website-404-redirects/issues)
- [Changelog](CHANGELOG.md)
