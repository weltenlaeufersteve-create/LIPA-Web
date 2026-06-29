# LIPA Web — Resume Notes

_Last updated: 2026-06-29 (end of session)_

## Where we are
**Live in production** at **https://lipa.pepea-africa.org** (serverprofis cPanel). `master`
is the source of truth and is fully deployed. 54 PHPUnit tests green.

## Shipped so far
- **Plans 1–4 (MVP):** auth + roles (admin/editor/viewer), Users/Contacts/Projects/Categories,
  Income (TZS/USD snapshot) + Expenses (TZS) with filters + receipt uploads, Dashboard,
  multi-sheet Excel export, Settings, Activity log.
- **Plan 5 — Accounts:** Cash & Bank accounts with per-account opening balances, Transfers
  between accounts, required account on income/expense, per-account balances on dashboard +
  Excel; consolidated tabbed admin (Organisation·Accounts·Categories·Users).
- **Plan 6 — Project (donor) statement:** Reports → pick project + period → printable
  statement (Print → Save as PDF). Opening/received/spent/closing, TZS.
- **GUI polish:** NGO-first branding (logo/name in sidebar + login, "Powered by LIPA —
  tagline"), rounded main-menu card, fixed top-right theme toggle, grouped nav, sortable
  tables (click headers), slimmer income/expense lists, New buttons on the filter line.
- **Account filter** on income/expense lists; filter bar split into two rows.

## Open backlog (agreed, not built)
- **Period lock** (lock an audited month/year against edits) + **automatic nightly backup**
  (cron `mysqldump` on the server). — the next agreed item.
- Later: budgets vs actual, recurring entries, dashboard charts, donation receipts,
  per-user prefs. A **full GUI redesign with Claude Design** is planned for later.
- See `docs/superpowers/BACKLOG.md`.

## Deploy an update
Local: branch → build → user verifies at localhost → merge to `master` → push. Then:
```
ssh pepeaempowerment@195.30.85.70    # key ~/.ssh/serverprofis_lipa (passphrase stripped)
cd ~/lipa_app && git pull && composer install --no-dev
php bin/migrate.php   # only if the change touched the schema
```
Docroot symlinks persist. Secrets live ONLY in local `.deploy-secrets` (gitignored).

## Run locally (Windows + Laragon)
Prefix shell commands:
```
export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"
```
- MySQL: manual `mysqld --datadir=C:\laragon\bin\mysql\mysql-8.4.3-winx64\data` if down; DBs `lipa` + `lipa_test`.
- Serve: `php -S 0.0.0.0:8000 -t public` → http://localhost:8000
- Local admin: `admin@pepea-africa.org` / `Pepea2026!`
- Tests: `composer test`.

## Working agreement
Always brainstorm features first; build on a branch; **user verifies locally before deploy**.
English for this project.
