# Changelog

All notable changes to `best/laravel-engineering-blueprint`. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-03

First stable release. Upgrading from a 0.x version: see [UPGRADE.md](UPGRADE.md).

### Added
- **Profiles** — `engineering:install --profile=basic|modular|saas|tenant-saas`: records the profile in `config/blueprint.php`, safe to re-run, guards downgrades (`--allow-downgrade`), backs up the config on `--force`, warns about modules a profile change breaks.
- **Generators** — `make:module` (`--api`, `--web`, `--central`), `make:dto`, `make:action`, `make:crud` (`--api`, `--web`, `--fields`, `--feature`): JSON API and Blade pages on one shared backend (model, migration, factory, requests, data, actions, query, policy), Pest tests for every endpoint, overridable stubs, everything validated before anything is written, rollback on failure.
- **Multi-tenancy** (`tenant-saas`, stancl/tenancy ^3.10) — setup during install, retry-safe `tenant:create`, `tenant`/`central` route groups, tenant-aware generators, `InteractsWithTenancy` testing helper, sessions scoped per tenant, database queue pinned to the central connection.
- **Features and settings** (`saas`, `tenant-saas`) — definitions in code, state per tenant, `feature:` route middleware, `RequireFeature` job middleware, `@feature` Blade directive, typed `Setting::get/set` with Gate abilities, platform-admin commands (`feature:*`, `features:sync`, `setting:*`).
- **Interface translations** — `__()` in all generated pages and package messages, Arabic for the package strings, per-request language (`Accept-Language`, `/locale/{code}`, `app.language` setting, app default), right-to-left layout, `blueprint.locales`.
- **Tooling** — `engineering:about`, `bin/new-app` (ready-to-work app per profile), `bin/e2e` (real apps for every profile), CI for PHP 8.3/8.4 × Laravel 12/13, PHPStan level 6, Pint.

## [0.9.0] - 2026-11-03
### Added
- `bin/new-app` starter generator; translated Laravel authorization and error-page messages.

## [0.8.0] - 2026-11-03
### Added
- Blade CRUD (`make:crud --web`), interface translations, whole-package security pass (session scoping, tenant database guards, array element validation).
### Changed
- API CRUD routes are named `api.<uri>.*`; `features`/`settings` config default to `null` (derived from `shared_path`).

## [0.7.0] - 2026-11-03
### Added
- Features and settings.

## [0.6.0] - 2026-11-03
### Added
- Multi-tenancy with stancl/tenancy.

## [0.5.0] - 2026-11-03
### Added
- `make:crud` (JSON API), `make:dto`, `make:action`, `make:module`, `engineering:install`, profiles, `engineering:about`.

[1.0.0]: https://github.com/hatem-elsheref/laravel-engineering-blueprint/releases/tag/v1.0.0
