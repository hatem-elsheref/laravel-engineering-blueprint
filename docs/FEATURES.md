# FEATURES.md

Part of [Phase 0 — Architecture Foundation](./PHASE-0.md).

## 4.1 Purpose

Features control which application capabilities are available for each tenant.

A feature can be enabled or disabled independently for each tenant.

The platform administrator controls feature states.

Tenant owners can view feature states but cannot change them.

## 4.2 Feature Storage

Each tenant database contains a `features` table.

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
- Feature records are created during tenant setup.
- Feature definitions are maintained by application code.
- Tenant users cannot edit feature records.

## 4.3 Feature Definitions

Feature keys are defined in code.

Example:

```php
final class Features
{
    public const ORDERS = 'orders';
    public const INVENTORY = 'inventory';
    public const BARCODE = 'barcode';
    public const REPORTS = 'reports';
    public const AI = 'ai';
}
```

Feature names must not be scattered across the application.

## 4.4 Feature Access

Use one central feature service.

Example:

```php
Feature::enabled(Features::INVENTORY);
```

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

Backend authorization is required.

## 4.6 Platform Admin

Only authorized platform administrators can:

- View tenants.
- View tenant features.
- Enable a feature.
- Disable a feature.

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

New feature definitions must be added to existing tenants through a controlled update process.
