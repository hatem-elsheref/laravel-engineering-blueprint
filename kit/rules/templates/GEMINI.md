# Gemini — Laravel Engineering Blueprint

Follow **`AGENTS.md`** and `laravel-engineering-blueprint/docs/CONSTITUTION.md`.

Use skills **laravel-engineering** and **leb-generate-crud** when editing Laravel backend code in this repo.

Generate code with the package commands (`make:module`, `make:dto`, `make:action`, `make:service`, `make:crud [--api] [--web]`); never hand-fill stubs. Customize stubs with `php artisan vendor:publish --tag=blueprint-stubs`.

User-facing text always uses `__()` with English keys; translations go in `lang/<locale>.json` (see `blueprint.locales`).
