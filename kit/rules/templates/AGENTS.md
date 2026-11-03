# Agent instructions — Laravel Engineering Blueprint

This project uses the **Laravel Engineering Blueprint (LEB)** kit.

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
- Invoke skill **leb-generate-crud** when scaffolding new entities from stubs.

(Cursor: skills under `.cursor/skills/` or personal `~/.cursor/skills/`. Claude/Codex: `~/.agents/skills/` or `~/.claude/skills/`.)

## Hard rules

- No business logic in controllers.
- All mutating input through FormRequest classes.
- Tenant data only inside tenant context; central DB is registry-only.
- Feature off = backend enforcement, not UI-only hiding.
- Simple English names; folders imply class role.

## Stubs

Generator templates: `laravel-engineering-blueprint/kit/stubs/`.

## User overrides

Direct user instructions in chat or project-specific docs override this file when they conflict.
