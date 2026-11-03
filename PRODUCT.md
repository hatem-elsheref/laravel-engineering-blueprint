# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Docs site: plain static HTML/CSS (no build step, no dependencies), served by GitHub Pages from the repository. English and Arabic (right-to-left) versions.

## Users

Laravel developers evaluating and adopting the package: they decide whether it fits their project, install it, pick a profile, and generate modules and CRUDs (Blade pages and/or JSON API). They read the docs while working in a terminal and an editor, copying commands and checking what a command generates or why it refused.

## Product Purpose

`best/laravel-engineering-blueprint` is a Laravel 12/13 package (PHP 8.3+) that turns one engineering standard into working code: profiles (`basic`, `modular`, `saas`, `tenant-saas`), generators (`make:module`, `make:dto`, `make:action`, `make:crud` with `--api` and/or `--web`), database-per-tenant multi-tenancy (stancl/tenancy), per-tenant features and settings, and interface translations (Arabic included, right-to-left). Success for the docs: a developer goes from `composer require` to a generated, tested CRUD without asking anyone, and can look up any command, option, config key or rule in seconds.

## Positioning

Not a collection of loose stubs: every generator validates input, refuses unsafe states with an exact message before writing anything, rolls back on failure, and emits Pest tests that pass in a real app (verified per profile by `bin/e2e` in CI). Blade and JSON API sides share one backend. Tenancy, feature gates and settings are wired into what the generators produce, not bolted on.

## Operating Context

- Install: `composer require best/laravel-engineering-blueprint` (Packagist pending; repository https://github.com/hatem-elsheref/laravel-engineering-blueprint, release 1.0.0), or `bin/new-app` to build a ready app.
- Readers copy shell commands and PHP snippets; exact command output and refusal messages matter.
- Source of truth for content: README.md, docs/*.md (ARCHITECTURE, NAMING, TENANCY, FEATURES, SETTINGS, CONSTITUTION), CHANGELOG.md, UPGRADE.md, and the commands' real behavior in src/.

## Capabilities and Constraints

- 13 Artisan commands, 4 profiles, config keys in `config/blueprint.php`, route naming (`api.<uri>.*`, `<uri>.*`, `central.` prefix), stub customization via `vendor:publish --tag=blueprint-stubs`.
- Docs must stay accurate to the code; examples use the package's real names (`Orders`, `Order`, `OrderData`, `CreateOrder`).
- Arabic version: same content, right-to-left, technical terms (commands, code, config keys) stay in English/LTR inside the Arabic text.
- No search backend (static hosting); no analytics or third-party scripts assumed.

## Brand Commitments

- Name: Laravel Engineering Blueprint (package `best/laravel-engineering-blueprint`). No logo or brand palette exists; none was specified.
- Voice (from existing docs): short, plain English, direct, tables where they help, no marketing superlatives.

## Evidence on Hand

- Real: 545 package tests, CI matrix (PHP 8.3/8.4 × Laravel 12/13) plus real-app runs for every profile, release 1.0.0, MIT license (Copyright 2026 Hatem Mohamed).
- Absent — do not fabricate: users/customers, testimonials, download counts, benchmarks, star counts.

## Product Principles

1. Exact over persuasive: show the real command, the real output, the real refusal.
2. Safe by default and say so: what is refused, why, and what to do instead.
3. One backend, many surfaces: Blade, API, tenants and languages are variations of the same generated code.
4. Docs match the code; anything not shipped is not documented as shipped.

## Accessibility & Inclusion

Arabic readers are first-class: full right-to-left layout, correct mixing of RTL prose with LTR code. Pages must work without JavaScript, keyboard-navigable, readable at 200% zoom, sufficient contrast.
