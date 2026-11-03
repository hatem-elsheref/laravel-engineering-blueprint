# Agent instructions — Laravel Engineering Blueprint

This project uses the **Laravel Engineering Blueprint (LEB)**: the package `best/laravel-engineering-blueprint` and its agent kit. Phase 0 specs are approved.

## Required reading (in order)

1. `laravel-engineering-blueprint/docs/PHASE-0.md`
2. `laravel-engineering-blueprint/docs/ARCHITECTURE.md`
3. `laravel-engineering-blueprint/docs/NAMING.md`
4. `laravel-engineering-blueprint/docs/TENANCY.md`
5. `laravel-engineering-blueprint/docs/FEATURES.md`
6. `laravel-engineering-blueprint/docs/SETTINGS.md`

Quick index: `laravel-engineering-blueprint/docs/CONSTITUTION.md`

If the bundle path differs, search for `CONSTITUTION.md` under `laravel-engineering-blueprint/`.

## Skills

When working on Laravel backend modules, CRUD, tenancy, or features:

- Invoke skill **laravel-engineering** before implementation.
- Invoke skill **leb-generate-crud** when creating modules, Data classes, Actions or CRUD.

(Cursor: skills under `.cursor/skills/` or personal `~/.cursor/skills/`. Claude/Codex: `~/.agents/skills/` or `~/.claude/skills/`.)

## Hard rules

- No business logic in controllers.
- All mutating input through FormRequest classes.
- Tenant data only inside tenant context; central DB is registry-only.
- Feature off = backend enforcement, not UI-only hiding.
- Simple English names; folders imply class role.

## Generate with the commands

Never hand-fill or copy stubs. Run the package commands:

```bash
php artisan make:module Orders --api --web
php artisan make:dto Orders OrderData --fields="name:string,quantity:int"
php artisan make:action Orders CancelOrder --data=OrderData --transaction
php artisan make:crud Orders Order --fields="name:string,quantity:int"          # JSON API (default)
php artisan make:crud Orders Order --fields="name:string,quantity:int" --web    # Blade pages
php artisan make:crud Orders Order --fields="name:string,quantity:int" --api --web
```

Run `php artisan engineering:install --profile=...` first if the project has no profile. To change generated code, run `php artisan vendor:publish --tag=blueprint-stubs` and edit `stubs/blueprint/*.stub`. `laravel-engineering-blueprint/stubs/` is a reference copy only.

Features and settings change only through console commands (`feature:enable`, `features:sync`, `setting:set`), never from app code (except `Setting::set()` for tenant-editable settings).

## User overrides

Direct user instructions in chat or project-specific docs override this file when they conflict.
