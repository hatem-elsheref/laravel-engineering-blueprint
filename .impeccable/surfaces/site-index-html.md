---
version: 1
slug: "site-index-html"
primary_target: "site/index.html"
related_targets: ["site/ar/index.html"]
---

# Docs site (site/)

Scope: static HTML/CSS docs site for best/laravel-engineering-blueprint, GitHub Pages, English (site/) and Arabic RTL (site/ar/). Mode: Read.
Reader: Laravel developer adopting the package; job: install → choose profile → generate → look up any command/option/config/rule in seconds.
Structure (owner): guide pages in reading order (Getting started, Profiles, Generators, CRUD (Blade + API), Tenancy, Features & settings, Languages, Upgrading) + one Reference page.
Owner's anti-goals: marketing landing, generic docs-template look, broken Arabic, heavy pages.

## Direction contract

THESIS: The docs are a card catalog drawer: every topic a guide card behind a labelled tab, every command an index card you pull and read in place. Refuses the default three-column docs template with a hero and feature tiles.

OWN-WORLD: Oak-brown drawer front for the site header and nav (brass-plate label), manila card stock for content cards with a single red header rule and faint blue ruling for body lines, typewriter-black ink, one stamp red for marks. Index cards have a punched rod hole; guide cards carry raised tabs whose positions step across the drawer. System font stacks only (owner: no web fonts / heavy pages): a system serif for guide-card labels, system sans for prose, the code mono only for commands and code, Arabic via system Naskh stacks.

STORY: The reader sees the drawer label and tabs, pulls the card for the step they're on, reads exact commands and stamped outcomes (GENERATED, REFUSED, SKIPPED), follows "see also" cross-references to sibling cards, and copies the command.

FIRST VIEWPORT: Drawer-front header (brass label: package name + version) over a row of stepped tabs (the guide sections). Below, the Getting started guide card fills the measure: one-line purpose, then the install command as a typed card line, then the three first commands. Reference tab sits rightmost.

FORM: Card catalog drawer, #6 of my ordered list (assigned by the roll; my top pick, an engineering standards document, was offered and not chosen).
seed: none (key only: rerun with --from b561daa4d9c56ef3e5e40f2873905c46)

RAISES: from the centre-rail reference-setting challenger (competitive on clarity): hairline precision — every rule, tab edge and stamp border at one device pixel; states as printed marks, never colour alone.
