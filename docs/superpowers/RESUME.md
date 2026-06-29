# LIPA Web — Resume Notes

_Last updated: 2026-06-29_

## Where we are
**MVP complete.** All 4 plans built and tested (**42 PHPUnit tests green**). Everything is
committed and pushed to GitHub (`weltenlaeufersteve-create/LIPA-Web`).

Branches (stacked sequentially, each built on the previous):
- `master` — spec + Plan 1 doc only
- `plan-1-foundation-auth` — auth, roles, Users CRUD, theme
- `plan-2-master-data` — Contacts, Projects, Categories (+22 seeded categories)
- `plan-3-core-register` — Income (TZS/USD), Expenses (TZS), filters, receipt uploads
- `plan-4-dashboard-reports` — Dashboard, Excel export, Settings, Activity log ← **current branch**

## Open decisions (not yet done)
1. **Merge branches into `master`** (nothing merged yet).
2. **Deploy** to `lipa.pepea-africa.org` on DomainFactory (not deployed).
3. Optionally get `lipa.test` working locally via Laragon "Start All".

## How to run locally next time
The local toolchain is NOT on PATH for fresh shells. Prefix commands with:
```
export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"
```
1. **Start MySQL** (if not running): it was started manually as
   `mysqld --datadir=C:\laragon\bin\mysql\mysql-8.4.3-winx64\data --console` (root, no password).
   `lipa` + `lipa_test` databases exist. (Or just use Laragon "Start All".)
2. **Serve the app:** `php -S 0.0.0.0:8000 -t public` → http://localhost:8000
3. **Login:** `admin@pepea-africa.org` / `Pepea2026!` (change under Users).
4. **Run tests:** `composer test` (uses `lipa_test`).

## Notes
- PHP extensions enabled in `php.ini` and required in prod: `pdo_mysql, mbstring, openssl, fileinfo, gd, zip`.
- Receipts live in `storage/receipts/` (outside webroot, gitignored).
- `.env` (local) and `.env.testing` exist but are gitignored; templates in `.env.example`.
- Plan docs: `docs/superpowers/plans/`. Spec: `docs/superpowers/specs/`.
