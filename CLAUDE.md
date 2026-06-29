# LIPA Web — Claude Project Context

## What this is
**LIPA — Income & Expenses for small NGOs.** A web-based income/expense tracker that
replaces QuickBooks for small NGOs. First user: **Pepea** (a Tanzanian NGO). "LIPA" is the
product; "Pepea" is just the org using it (editable as `org_name` in Settings — never
hardcode it).

It is a **simple cashbook**, NOT double-entry: record income and expenses, tag them to
donors/vendors/projects/categories, attach receipts, and export to Excel for the
accountant (who does the formal audit/reports by hand). Communicate in **English** for this
project.

## Live deployment
- **URL:** https://lipa.pepea-africa.org (HTTPS forced)
- **Host:** serverprofis.de cPanel — `195.30.85.70`, SSH port 22, user `pepeaempowerment`,
  key `~/.ssh/serverprofis_lipa` (passphrase stripped for non-interactive use)
- **App code:** `/home/pepeaempowerment/lipa_app` (private, outside web root)
- **Docroot:** `/home/pepeaempowerment/lipa.pepea-africa.org` holds only symlinks
  (`index.php`, `assets`, `uploads` → `lipa_app/public`) + a front-controller `.htaccess`,
  so `.env`/`src`/`vendor` are NOT web-reachable.
- **DB:** MariaDB-compatible MySQL, database `pepeaempowerment_lipa`. Server PHP 8.3.31.
- **Deploy an update:** `ssh pepeaempowerment@195.30.85.70` → `cd ~/lipa_app && git pull && composer install --no-dev` (symlinks persist; run a DB migration too if the schema changed). Always merge to `master` first; production tracks `master`.
- Secrets (DB pass, key passphrase) live ONLY in local `.deploy-secrets` (gitignored).

## Tech stack
- **Plain PHP 8.3** (no framework). Front controller `public/index.php` + tiny regex `Router`.
- **PDO** against MariaDB/MySQL; **prepared statements everywhere**. Write **portable SQL**
  (runs on local MySQL 8.4 and prod MariaDB).
- Composer deps: `vlucas/phpdotenv`, `phpoffice/phpspreadsheet` (Excel), dev: `phpunit/phpunit`.
- Server-rendered PHP views; **vanilla** CSS/JS. Ported LIPA design tokens in
  `public/assets/css/theme.css` (light + brand-toned dark); layout in `app.css`.
- Mobile-first responsive (sidebar → hamburger < 768px); client-side dark/light toggle
  (localStorage `lipa_theme`); assets cache-busted via `?v=<mtime>` (`asset()` helper).

## Structure
```
public/index.php        front controller + all route registration
public/.htaccess        force HTTPS + front-controller rewrite
public/assets/          theme.css, app.css, app.js
src/Database.php        PDO singleton (reads .env)
src/Router.php          add()/dispatch(); NotFoundException
src/Auth.php            session login, role guards; ForbiddenException
src/ReceiptStorage.php  upload validation + storage (storage/receipts, outside webroot)
src/Reports/ExcelExport.php   multi-sheet .xlsx builder
src/Models/             User, Contact, Project, Category, Income, Expense, Setting, Activity
src/Controllers/        Auth, Dashboard, User, Contact, Project, Category, Income, Expense,
                        Setting, Report, Activity
views/                  layout.php (render()/e()/asset()), _shell.php, per-area templates
db/schema.sql           all tables (portable SQL)   db/seed.sql   db/migrations/ (numbered)
bin/create-admin.php    bin/seed-categories.php
tests/                  PHPUnit (DatabaseTestCase builds schema in lipa_test)
```

## Data model (8 tables)
`users` (role admin/editor/viewer), `contacts` (type donor/vendor), `projects`,
`categories` (type income/expense), `income` (currency TZS/USD + amount_original,
exchange_rate, amount_tzs; receipt_path), `expenses` (amount_tzs only; receipt_path),
`settings` (key-value), `activity_log`. Money = `DECIMAL(15,2)`; base currency **TZS**
(USD only on income, snapshot rate). FKs `ON DELETE SET NULL`.

## Roles
- **admin** — everything incl. Users, Settings, Categories
- **editor** — record income/expenses/contacts/projects
- **viewer** (accountant) — read everything + export; no edits
Guards enforced **server-side** in every controller (never rely on hidden UI). Nav in
`_shell.php` is role-aware.

## Conventions
- Routes: lazy controller instantiation in closures in `public/index.php`.
- Models are static-method classes returning assoc arrays.
- Controllers validate, then redirect (`header('Location: …'); exit`) or re-render with `$error`.
- Every write action calls `Activity::log(...)`.
- Currency display: `number_format($v, 2)`; base currency TZS.
- Dates stored `YYYY-MM-DD`.
- Receipts: PDF/JPG/PNG ≤10 MB in `storage/receipts/`, streamed via authed
  `/{income|expenses}/:id/receipt` route (never web-served directly).

## Local development (Windows + Laragon)
Tools are NOT on PATH for fresh shells — prefix commands:
```
export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"
```
- MySQL runs as a manual `mysqld` (root, empty password); DBs `lipa` + `lipa_test`.
  If down: start `mysqld --datadir=C:\laragon\bin\mysql\mysql-8.4.3-winx64\data` (or Laragon "Start All").
- Serve: `php -S 127.0.0.1:8000 -t public` → http://127.0.0.1:8000
- Local admin: `admin@pepea-africa.org` / `Pepea2026!`
- Tests: `composer test` (PHPUnit against `lipa_test`).
- php.ini extensions enabled (also required in prod): pdo_mysql, mbstring, openssl,
  fileinfo, gd, zip.

## Working agreement
**Always test locally and let the user confirm before deploying.** Flow: brainstorm →
build on a branch → user verifies locally → merge to `master` → `git pull` on the server.

## Backlog
See `docs/superpowers/BACKLOG.md`.

## Carrying over a prior-year balance (how-to)
Until the Opening-balance feature ships: add an **income** category "Opening balance
(carried over <year>)" and one Income entry dated 01 Jan of the new year for the leftover.
