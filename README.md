# LIPA Web

**LIPA — Income & Expenses for small NGOs.** A lightweight, web-based income/expense
tracker (a QuickBooks alternative) built for small non-profits. It is a simple **cashbook**
(not double-entry): record income and expenses, tag them to donors/vendors/projects/
categories, attach receipts, track activities, and export to Excel for the accountant.

Base currency is **TZS** (USD is supported on income with a snapshot exchange rate).
UI is English (UK) and mobile-responsive.

## Features
- **Income & expenses** with donor/vendor, project, category and account tagging; receipt uploads.
- **Accounts & transfers** — cash/bank accounts with opening balances; transfers between accounts
  (kept out of income/expense totals) and per-account balances.
- **Activities** — record NGO activities (date, description, up to 5 auto-resized photos), link the
  expenses each one incurred, and print an **Activity report** for any period.
- **Reports** — organisation statement, project/donor statement, and activity report as printable
  (Save-as-PDF) pages, plus a multi-sheet **Excel export**.
- **Roles** — admin / editor / viewer, enforced server-side.
- **Security** — session auth, CSRF protection on every form, uploads streamed via authenticated
  routes (never served directly from the web root).

## Tech stack
Plain **PHP 8.3** (no framework) with a small front controller + regex router, **PDO** against
**MariaDB/MySQL** (portable SQL, prepared statements everywhere), server-rendered PHP views with
vanilla CSS/JS. Composer deps: `phpoffice/phpspreadsheet` (Excel), `vlucas/phpdotenv`; PHPUnit for tests.

## Local setup
1. `composer install`
2. Copy `.env.example` to `.env` and set your DB credentials (local default: user `root`, empty
   password). Create a `.env.testing` with `DB_NAME=lipa_test`.
3. Create the databases and load the schema:
   ```
   mysql -uroot -e "CREATE DATABASE lipa CHARACTER SET utf8mb4; CREATE DATABASE lipa_test CHARACTER SET utf8mb4;"
   mysql -uroot lipa < db/schema.sql
   ```
4. Create the first admin: `php bin/create-admin.php "Administrator" you@example.org <password>`
5. Optionally seed starter categories: `php bin/seed-categories.php`
6. Serve the `public/` folder, e.g. `php -S 127.0.0.1:8000 -t public` → http://127.0.0.1:8000

## Tests
`composer test` — runs PHPUnit against the `lipa_test` database.

## Deployment
- Point the site's document root at `public/`; keep `.env`, `src/`, and `vendor/` **outside** the
  web root.
- Update with `git pull` + `composer install --no-dev`; run `php bin/migrate.php` when the schema
  changed (it is idempotent).
- **Required PHP extensions:** `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`
  (`gd` resizes activity photos; `gd` + `zip` are needed by PhpSpreadsheet).
- Ensure `storage/receipts/` and `storage/activity_photos/` are writable by PHP and **not**
  reachable from the web root.

## Roles
- **admin** — full access incl. users, settings, categories, accounts.
- **editor** — record income, expenses, contacts, projects, transfers, activities.
- **viewer** (accountant) — read everything and export/print; no edits.

## Changelog
Newest first.

### 2026-07
- **CSRF protection** across every form (synchroniser-token pattern, verified centrally before any
  POST) and a CSS consistency pass on the Activities views.
- **Activities module** — activity records with photos and linked expenses, plus a printable
  activity report for a period.
- **Accounts, transfers & statements** — cash/bank accounts with opening balances, inter-account
  transfers, and printable organisation and project/donor statements; account filters on lists.

### Initial release
- Session auth with admin/editor/viewer roles; CRUD for users, contacts (donors/vendors),
  projects and categories.
- Income (TZS/USD snapshot) and expenses (TZS) with filters and receipt uploads; dashboard KPIs;
  multi-sheet Excel export.
