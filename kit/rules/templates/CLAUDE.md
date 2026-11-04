# Claude Code — Laravel Engineering Blueprint

Follow **`AGENTS.md`** in this project (same rules).

Skills: install **laravel-engineering** and **leb-generate-crud** from `laravel-engineering-blueprint/kit/skills/` into your skills path, or symlink via `./laravel-engineering-blueprint/bin/leb-install --link-agents`.

Constitution: `laravel-engineering-blueprint/docs/CONSTITUTION.md`.

Generate code with the package commands (`make:module`, `make:dto`, `make:action`, `make:service`, `make:crud [--api] [--web]`); never hand-fill stubs. Customize stubs with `php artisan vendor:publish --tag=blueprint-stubs`.

User-facing text always uses `__()` with English keys; translations go in `lang/<locale>.json` (see `blueprint.locales`).
