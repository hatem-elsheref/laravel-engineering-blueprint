# This repository

**Laravel Engineering Blueprint** — the Laravel package `best/laravel-engineering-blueprint` plus an AI-agent kit (docs, skills, rules, installer). It is not a Laravel app.

| Path | What |
|------|------|
| `src/` | Package code (commands, features, settings, tenancy) |
| `config/blueprint.php` | Package config |
| `stubs/` | Generator stubs rendered by the commands |
| `tests/` | Pest tests |
| `docs/` | Phase 0 specs (approved; the design authority) |
| `kit/skills/`, `kit/rules/` | Agent skills, Cursor rules, agent entry templates |
| `bin/leb-install` | Installs the kit into a project |
| `bin/e2e` | Builds real Laravel apps for every profile and checks them |

When editing this repo:

- **Phase 0 authority:** `docs/PHASE-0.md` and the linked specs; amend there first, then sync `rules.md` if needed.
- Keep `rules.md` as narrative; approved design lives in `docs/*.md`.
- Run `composer check` (Pint, PHPStan level 6, Pest) before finishing a change. Run `bin/e2e` for changes to generators, install or tenancy.
- When a command's behavior changes, update `README.md`, the docs and the kit skills/rules to match.
- Run `./bin/leb-install /path/to/laravel-app --link-agents` to test kit installs.

For Laravel application work in another project, install the package and kit there and follow that project's `AGENTS.md`.
