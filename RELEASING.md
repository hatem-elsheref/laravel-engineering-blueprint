# Releasing

Everything up to the tag is prepared in the repository. These steps need your accounts and decisions.

## Before the first release (once)

1. **License.** `composer.json` declares `MIT`. Confirm it, then add a `LICENSE` file with the copyright holder's name (e.g. `MIT License — Copyright (c) 2026 <name>`).
2. **Package name.** `best/laravel-engineering-blueprint` needs the `best` vendor on Packagist. If it is taken, pick another vendor and replace it everywhere: `grep -rl "best/laravel-engineering-blueprint" --exclude-dir=vendor .`
3. **Repository.** Create the GitHub repository, then:
   ```bash
   git remote add origin git@github.com:<owner>/laravel-engineering-blueprint.git
   git push -u origin main
   ```
   Replace `OWNER` in the link at the bottom of `CHANGELOG.md`.
4. **Packagist.** Submit the repository URL at https://packagist.org/packages/submit and enable the GitHub hook (auto-update on push).

## Every release

1. `composer check` and `bin/e2e` are green locally; CI is green on `main`.
2. `Blueprint::VERSION` (`src/Blueprint.php`) and `blueprint.manifest.json` have the new version (a test keeps them equal), and `CHANGELOG.md` has its section (a test checks it).
3. `UPGRADE.md` covers anything that changes generated code, config or behavior.
4. Tag and push:
   ```bash
   git tag -a v1.0.0 -m "1.0.0"
   git push origin v1.0.0
   ```
5. Create the GitHub release from the tag, using the `CHANGELOG.md` section as the notes.
6. Check the published package in a fresh app:
   ```bash
   bin/new-app /tmp/release-check --profile=saas --locales=en,ar --source=packagist
   ```
