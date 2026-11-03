# FEATURES.md

Part of [Phase 0 — Architecture Foundation](./PHASE-0.md).

## 4.1 Purpose

Features control which application capabilities are available for each tenant.

A feature can be enabled or disabled independently for each tenant.

The platform administrator controls feature states.

Profiles: features exist in `saas` (one set for the whole app) and `tenant-saas` (one set per tenant).

Tenant owners can view feature states but cannot change them.

## 4.2 Feature Storage

The `features` table lives in each tenant database (`tenant-saas`) or in the app database (`saas`). `engineering:install` creates its migration (a tenant migration in `tenant-saas`).

```text
features
--------
id
key
enabled
created_at
updated_at
```

Constraints:

- `key` must be unique.
- `enabled` is boolean.
- Feature records are created during tenant setup (`tenant:create`). In `saas`, run `php artisan features:sync` once after migrating.
- Feature definitions are maintained by application code.
- Tenant users cannot edit feature records.

## 4.3 Feature Definitions

Feature keys are defined in code, in one definitions class. `engineering:install` creates `app/Shared/Features/Features.php` (config `blueprint.features`; null = `<shared_path>/Features/Features`).

Example:

```php
final class Features
{
    public const ORDERS = 'orders';
    public const REPORTS = 'reports';

    /**
     * Every feature key and its default state.
     *
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            self::ORDERS => true,
            self::REPORTS => false,
        ];
    }
}
```

Feature names must not be scattered across the application.

## 4.4 Feature Access

Use one central feature service.

Example:

```php
Feature::enabled(Features::REPORTS);
Feature::disabled(Features::REPORTS);
Feature::ensure(Features::REPORTS);   // throws when disabled
Feature::all();                        // key => bool
```

The `Feature` facade is read-only. An unknown key throws an error.

The service must:

- Read from the current tenant database.
- Return the current feature state.
- Cache results only when cache invalidation is reliable.
- Clear the relevant cache after an admin update.
- Fail safely when the tenant context is missing.
- Prevent tenant users from changing feature states.

## 4.5 Feature Protection

A disabled feature must not be accessible through:

- Navigation
- Web routes
- API routes
- Controllers
- Actions
- Background jobs
- Scheduled tasks
- Dashboard widgets

Hiding a button is not security.

Backend authorization is required. The package provides:

| Where | How |
|---|---|
| Routes | Middleware `feature:orders` (or `feature:a,b`: every listed feature must be on); 403 when off. `make:crud ... --feature=orders` adds it. |
| Jobs | Job middleware `new RequireFeature(Features::ORDERS)` (`Best\Blueprint\Features\RequireFeature`) in `middleware()` |
| Actions, controllers, scheduled tasks | `Feature::ensure(...)` |
| Navigation, widgets | Blade `@feature('orders') ... @endfeature` (display only) |

## 4.6 Platform Admin

Only authorized platform administrators can:

- View tenants.
- View tenant features.
- Enable a feature.
- Disable a feature.

Feature changes are made only through console commands:

```bash
php artisan feature:list --tenant=acme
php artisan feature:enable reports --tenant=acme
php artisan feature:disable reports --tenant=acme
```

`--tenant` is required in `tenant-saas` and refused in `saas`. The package refuses feature writes during HTTP requests, and in `tenant-saas` without an active tenant.

Feature changes must update the selected tenant database.

Do not store tenant feature states in the central database.

## 4.7 Tenant Owner

Tenant owners can:

- View available features.
- View enabled features.

Tenant owners cannot:

- Enable features.
- Disable features.
- Change feature definitions.
- Modify feature records through direct API requests.

## 4.8 Default Features

New tenants receive the default feature list during creation.

The initial default state is defined by the platform.

New feature definitions must be added to existing tenants through a controlled update process: `php artisan features:sync` (all tenants, or `--tenant=acme`). It adds missing keys with their default state and never changes existing ones.

In `saas` tests the `features` table starts empty, so every feature is off. Call `app(\Best\Blueprint\Features\FeatureAdmin::class)->sync()` or `->enable(...)` in `beforeEach`.
