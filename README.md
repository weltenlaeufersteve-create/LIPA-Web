# LIPA Web

NGO income/expense tracker (QuickBooks replacement) for Pepea. Plain PHP + MariaDB.

## Local setup (Laragon)
1. `composer install`
2. Copy `.env.example` to `.env`; set DB creds (Laragon default: user `root`, empty password).
   Create a `.env.testing` with `DB_NAME=lipa_test`.
3. Create databases and load schema:
   ```
   mysql -uroot -e "CREATE DATABASE lipa CHARACTER SET utf8mb4; CREATE DATABASE lipa_test CHARACTER SET utf8mb4;"
   mysql -uroot lipa < db/schema.sql
   ```
4. Create the first admin: `php bin/create-admin.php "Administrator" admin@pepea-africa.org <password>`
5. Laragon serves the project via a junction at `C:\laragon\www\lipa` → open http://lipa.test
   (In Laragon, document root resolves to the `public/` subfolder. Click **Reload** after first setup.)

## Tests
`composer test`  (runs PHPUnit against the `lipa_test` database)

## Deployment (DomainFactory)
- Subdomain `lipa.pepea-africa.org`, document root = `public/`.
- `git pull` over SSH, `composer install --no-dev`, load `db/schema.sql`, create `.env` outside `public/`.
- Create first admin via `php bin/create-admin.php`.
- Optionally seed starter categories: `php bin/seed-categories.php`.
- **Required PHP extensions:** `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`
  (`gd` + `zip` are needed by PhpSpreadsheet for the Excel export).
- Ensure `storage/receipts/` is writable by PHP and **not** served by the web root.

## Roles
- **admin** — full access incl. users, settings, categories.
- **editor** — record income, expenses, contacts, projects.
- **viewer** (accountant) — read everything and export; no edits.
