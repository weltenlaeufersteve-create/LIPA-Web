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
src/Reports/ExcelExport.php       multi-sheet .xlsx builder
src/Reports/ProjectStatement.php  donor/grant statement figures (opening/received/spent/closing)
src/Models/             User, Contact, Project, Category, Income, Expense, Setting, Activity,
                        Account, Transfer
src/Controllers/        Auth, Dashboard, User, Contact, Project, Category, Income, Expense,
                        Setting, Report, Activity, Account, Transfer
views/                  layout.php (render()/e()/asset()), _shell.php, _filters.php,
                        admin/_tabs.php, reports/statement.php (standalone print), per-area templates
db/schema.sql           all tables (portable SQL)   db/seed.sql
bin/create-admin.php    bin/seed-categories.php    bin/migrate.php (idempotent schema upgrades)
tests/                  PHPUnit (DatabaseTestCase builds schema in lipa_test)
```

## Data model (10 tables)
`users` (role admin/editor/viewer), `contacts` (type donor/vendor), `projects`,
`categories` (type income/expense), `accounts` (type bank/cash/other + opening_balance +
opening_balance_date; TZS), `income` (currency TZS/USD + amount_original, exchange_rate,
amount_tzs; `account_id`; receipt_path), `expenses` (amount_tzs only; `account_id`;
receipt_path), `transfers` (from_account_id/to_account_id, amount_tzs — money moved
between accounts, NOT income/expense), `settings` (key-value), `activity_log`.
Money = `DECIMAL(15,2)`; base currency **TZS** (USD only on income, snapshot rate).
FKs `ON DELETE SET NULL`. **Account balances are TZS** (`Account::balance()` = opening +
income − expenses + transfers-in − transfers-out, optional `asOf` date).

## Accounts, transfers, statements
- **Accounts** = cash/bank accounts (admin-managed, under Settings tabs). Every income &
  expense **requires** an account (default Bank). Per-account opening balance carries the
  prior-year leftover. Dashboard + Excel show per-account balances.
- **Transfers** (own nav item, admin/editor) move money between accounts and reconcile
  per-account balances; excluded from income/expense totals.
- **Project (donor) statement:** Reports → pick project + period → standalone printable page
  (`reports/statement.php`, bypasses the app shell) → Print → Save as PDF. Model a grant/
  donor as a **Project**; tag its income AND the expenses paid from it to that project.
- **Filters** on income/expense lists: date range, project, category, **account**
  (shared `views/_filters.php`; `Income`/`Expense` `whereClause` supports all four).

## Roles
- **admin** — everything incl. Users, Settings, Categories, Accounts (consolidated under a
  tabbed Settings area: Organisation · Accounts · Categories · Users)
- **editor** — record income/expenses/contacts/projects/transfers
- **viewer** (accountant) — read everything + export/statements; no edits
Guards enforced **server-side** in every controller (never rely on hidden UI). Nav in
`_shell.php` is role-aware (grouped in a rounded card; Settings/Activity at the bottom).

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
Set the **opening balance** on each Account (Settings → Accounts → edit) — that's the
prior-year leftover per account, reflected in dashboard/statement balances.

## Branding / white-label
NGO-first: the sidebar + login show the **logo** (Settings → Organisation → logo) if set,
else the **NGO name** (`org_name`), else "LIPA". LIPA appears only as the favicon + a quiet
"Powered by LIPA — Income & Expenses for small NGOs" mark. Never hardcode "Pepea".
