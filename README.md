# Laravel Engineering Blueprint (LEB)

Portable engineering kit for Laravel SaaS (modular monolith, tenancy, features, CRUD generators). **Not a Laravel application** — copy or run the installer into any project so Codex, Cursor, Antigravity, Claude Code, Gemini, and other agents share the same standards, skills, stubs, and docs.

Inspired by composable agent kits (e.g. Superpowers): one source tree, multiple install targets.

## What you get

| Component | Purpose |
|-----------|---------|
| `docs/` | **Phase 0** specs: `PHASE-0.md`, architecture, naming, tenancy, features, settings, constitution |
| `kit/skills/` | Agent skills (`laravel-engineering`, `leb-generate-crud`) |
| `kit/rules/` | Cursor rules + `AGENTS.md` / `CLAUDE.md` / `GEMINI.md` templates |
| `kit/stubs/` | Generator templates (for future `make:module` / `make:crud`) |
| `rules.md` | Full Arabic/English engineering guide (source narrative) |
| `bin/leb-install` | Install or refresh kit files in a target project or global skills dir |

## Quick install

From this repo (or after copying the folder anywhere):

```bash
# Project-local (recommended): skills, rules, docs, stubs under ./laravel-engineering-blueprint/
./bin/leb-install /path/to/your/laravel-app

# Also wire Cursor + cross-agent entrypoints in the project root
./bin/leb-install /path/to/your/laravel-app --link-agents

# Global skills only (~/.cursor/skills, ~/.agents/skills, ~/.claude/skills)
./bin/leb-install --global

# Preview
./bin/leb-install /path/to/your/laravel-app --dry-run
```

### Component flags

```bash
./bin/leb-install TARGET --only skills,rules
./bin/leb-install TARGET --skip stubs
```

Default install puts a **`laravel-engineering-blueprint/`** directory inside the target project (safe to commit). Skills are **symlinked** from there into agent skill paths when you use `--link-agents` or `--global`.

## Agent harnesses

| Harness | What gets installed |
|---------|---------------------|
| **Cursor** | `.cursor/rules/*.mdc`, `.cursor/skills/laravel-engineering/` (symlink), optional `AGENTS.md` |
| **Claude Code** | `~/.claude/skills/` or project skills via symlink; `CLAUDE.md` snippet |
| **Codex / Copilot CLI** | `~/.agents/skills/` alias + project `AGENTS.md` |
| **Antigravity / Gemini** | Same skills path + `GEMINI.md` pointer to constitution |
| **Any agent** | Read `docs/CONSTITUTION.md` + `AGENTS.md` |

Later: package as a Cursor/Codex plugin (see `blueprint.manifest.json`).

## Roadmap (this repo)

1. **Now** — Kit files, docs, stubs, installer (you are here).
2. **Phase 1** — Laravel package or app skeleton with module registration.
3. **Phase 3** — Artisan commands that consume `kit/stubs/`.

## License

Use freely in your projects; adjust `docs/` and rules to your team.
