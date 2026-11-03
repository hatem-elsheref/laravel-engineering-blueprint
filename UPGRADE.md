# Upgrade guide

## General steps (any version)

1. `composer update best/laravel-engineering-blueprint`
2. Read the section for your target version below.
3. If you published stubs (`stubs/blueprint/`), compare them with the new package stubs: `php artisan vendor:publish --tag=blueprint-stubs --force` overwrites them (back up your edits first). The generators refuse stale stubs with a clear message rather than generating broken code.
4. `php artisan optimize:clear`, then run your test suite.

Already generated code is never changed by an upgrade; only new `make:*` output follows the new rules.

## 0.x → 1.0

Most 0.x apps upgrade without changes. Check these points:

| Area | What changed | What to do |
|---|---|---|
| API route names | `make:crud` names API routes `api.<uri>.*` (web pages keep `<uri>.*`). Older API routes are named `<uri>.*`. | Nothing for existing routes. Before adding web pages (`make:crud … --web`) for an entity whose API route is unnamed, add `->names('api.<uri>')` to that route (the command tells you if a name is taken). |
| Published `route.stub` | Must keep `->names('api.{{ uri }}')`. | Re-publish or add it. |
| `config/blueprint.php` | New keys `layout`, `locales`, `rtl_locales`, `features`/`settings` default `null` (derived from `shared_path`), `tenant_seeder`, `tenant_domain`. | Copy missing keys from the package config, or re-run `php artisan engineering:install` (keeps your profile; `--force` backs the file up first). |
| Array fields | New rules: at most 100 elements, each a string up to 255 characters. | Generated requests from 0.x keep their rules; tighten them by hand if you want the same protection. |
| tenant-saas sessions | Web sessions are tied to the tenant (or central app) where they started; reused elsewhere they get 403. | Users signed in on one tenant must sign in again on another — intended. |
| tenant-saas cache | Tenant cache needs a store with tags. | Use `CACHE_STORE=redis` (or memcached) in tenant-saas apps. |
| Settings `app.language` | The generated definition now defaults to `config('app.locale')`. | Optional: change `'default' => 'en'` in `app/Shared/Settings/Settings.php`. |
| Languages | Generated pages use `__()`. | Add `'locales' => ['en', 'ar']` to `config/blueprint.php` for more languages; translate `lang/<locale>.json`. |
