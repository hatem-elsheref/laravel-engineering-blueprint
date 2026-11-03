# SETTINGS.md

Part of [Phase 0 — Architecture Foundation](./PHASE-0.md).

## 5.1 Storage

The `settings` table lives in each tenant database (`tenant-saas`) or in the app database (`saas`). `engineering:install` creates its migration.

```text
settings
--------
id
key
value
created_at
updated_at
```

The `value` column must support simple and structured values.

A row exists only when a value was set. Defaults come from the definitions, so new tenants need no setting rows.

## 5.2 Definitions

Every setting key is defined in code, in one definitions class. `engineering:install` creates `app/Shared/Settings/Settings.php` (config `blueprint.settings`; null = `<shared_path>/Settings/Settings`).

```php
final class Settings
{
    public const APP_NAME = 'app.name';
    public const APP_LANGUAGE = 'app.language';
    public const ORDERS_AUTO_CONFIRM = 'orders.auto_confirm';

    public static function definitions(): array
    {
        return [
            self::APP_NAME => ['type' => 'string', 'default' => 'My App', 'editable_by' => 'tenant', 'ability' => 'manage-settings'],
            self::APP_LANGUAGE => ['type' => 'string', 'default' => config('app.locale', 'en'), 'editable_by' => 'tenant', 'ability' => 'manage-settings'],
            self::ORDERS_AUTO_CONFIRM => ['type' => 'bool', 'default' => false, 'editable_by' => 'platform'],
        ];
    }
}
```

| Field | Meaning |
|---|---|
| `type` | `string`, `int`, `float`, `bool` or `array` |
| `default` | Value returned when nothing is stored |
| `editable_by` | `tenant` (changed from app code) or `platform` (console only) |
| `ability` | Gate ability that allows a tenant user to change it (required for `tenant` settings) |

More key examples: `orders.default_status`, `inventory.allow_negative`.

## 5.3 Access

Read:

```php
Setting::get('app.language');
```

Write (only `editable_by: tenant` settings, through their Gate ability):

```php
Setting::set('app.language', 'ar');
```

Define the ability the generated settings use, for example in `AppServiceProvider::boot()`:

```php
// Example only: decide who may change tenant settings in your app (e.g. tenant owners).
Gate::define('manage-settings', fn (User $user) => $user->is_owner);
```

Platform settings are never set from app code. Platform admins use the console (the value is read as JSON when it parses, otherwise as plain text):

```bash
php artisan setting:list --tenant=acme
php artisan setting:set orders.auto_confirm true --tenant=acme
```

`--tenant` is required in `tenant-saas` and refused in `saas`.

The settings service must:

- Read from the current tenant database.
- Convert stored values into the expected types (a wrong type is refused).
- Support defaults.
- Clear cache after updates.
- Respect setting-specific permissions.

Tenant-specific settings must not be stored in central database configuration.
