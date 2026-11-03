# SETTINGS.md

Part of [Phase 0 — Architecture Foundation](./PHASE-0.md).

## 5.1 Storage

Each tenant database has a `settings` table.

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

## 5.2 Examples

```text
app.language
app.name
orders.auto_confirm
orders.default_status
inventory.allow_negative
```

## 5.3 Access

Read:

```php
Setting::get('app.language');
```

Write:

```php
Setting::set('orders.auto_confirm', true);
```

The settings service must:

- Read from the current tenant database.
- Convert stored values into the expected types.
- Support defaults.
- Clear cache after updates.
- Respect setting-specific permissions.

Tenant-specific settings must not be stored in central database configuration.
