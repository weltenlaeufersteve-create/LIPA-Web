# LIPA Web — NGO Income/Expense Tracker — Design Spec

**Date:** 2026-06-29
**Project folder:** `C:\Tools\LIPA Web 26`
**Target:** `lipa.pepea-africa.org` (subdomain on DomainFactory)
**Status:** Approved design — pending implementation plan

---

## 1. Purpose

A web-based income/expense tracker for a Tanzanian NGO (Pepea), to **replace QuickBooks**
(too expensive for the NGO). It records income and expenses, manages donors and vendors,
and produces an Excel export from which the NGO's accountant performs the audit.

It is **not** a double-entry bookkeeping system. It is an income/expense register with
contacts, optional project tagging, multi-user access, and reporting — conceptually the
same pattern as the existing LIPA desktop app, rebuilt for the web.

### Scope decisions (confirmed during brainstorming)
- **Type:** Simple income/expense tracking (not full double-entry / no general ledger).
  The accountant does the formal audit from the export; the formal reports are hand-made.
- **Currency:** Base currency is **TZS**. Income may arrive in **USD** (rare) — recorded
  with original amount + exchange rate + computed TZS value. **Expenses are TZS only.**
- **Projects:** Optional project/category label on income and expenses. **No** strict
  per-grant budget tracking ("fund accounting light", level B).
- **Export:** Excel (`.xlsx`) with multiple sheets. Formal donor/financial reports are
  produced by hand by the accountant from this data.
- **Users/roles:** three roles — `admin`, `editor`, `viewer` (accountant).
- **UI language:** English (UK) throughout.
- **Receipts:** file upload (PDF/image) attached to each income and expense entry.
- **Responsive / mobile:** the app must be fully usable on a phone — the sidebar collapses
  to a top bar with a hamburger toggle, tables reflow/scroll, and forms/buttons are
  touch-friendly. Mobile-first CSS.

---

## 2. Architecture

### Approach: lean plain PHP (no heavy framework)
- Front controller (`public/index.php`) with a small custom router.
- PHP classes for models/data access; simple PHP templates for views (server-rendered).
- Composer **only** for targeted libraries:
  - `phpoffice/phpspreadsheet` — Excel export
  - `vlucas/phpdotenv` — `.env` config loading
- **MariaDB via PDO**, prepared statements throughout (SQL-injection safe).
- Auth built in-house: `password_hash()` / `password_verify()`, PHP sessions,
  role checks via a middleware/guard function.
- Vanilla JS for interactivity; reuse LIPA's `theme.css` design system (tokens, light/dark,
  modals, toasts, confirm dialog) for the same high-quality look.

Rejected: Slim 4 (more deps than needed), Laravel (overkill for this size, harder to
deploy on managed hosting).

### Local/production parity note
- Local dev uses **MySQL 8.4** (bundled with Laragon); production is **MariaDB** on
  DomainFactory. We write **portable SQL** (standard datatypes, no MySQL-8-only features)
  so the same schema runs in both.

### Directory layout
```
LIPA Web 26/
├── public/                 # the ONLY web-exposed folder (subdomain docroot points here)
│   ├── index.php           # front controller / router
│   ├── assets/             # css (theme.css), js, logo
│   └── uploads/            # receipt files (see security note)
├── src/
│   ├── Database.php        # PDO connection (reads .env)
│   ├── Auth.php            # login, sessions, role guards
│   ├── Router.php          # minimal router
│   ├── Models/             # User, Contact, Project, Category, Income, Expense, Setting, Activity
│   └── Controllers/        # one per page area
├── views/                  # PHP templates (layout + per-page partials)
├── db/
│   ├── schema.sql          # full CREATE TABLE script (portable SQL)
│   ├── seed.sql            # starter categories + first admin user
│   └── migrations/         # nnn-description.sql, idempotent (ALTER ... pattern)
├── vendor/                 # composer (gitignored)
├── .env.example            # template; real .env lives OUTSIDE public/, gitignored
├── composer.json
└── docs/superpowers/specs/ # this spec
```

**Security note on uploads:** receipt files must not be directly browsable/executable.
Preferred: store uploads **outside** `public/` and stream them through an authenticated
PHP endpoint (`download.php?id=…`) that checks the session/role. If kept under `public/`,
disable script execution there (`.htaccess`) and serve only via the auth endpoint.

---

## 3. Data model (MariaDB / portable SQL)

All money stored as `DECIMAL(15,2)`. All ids `INT AUTO_INCREMENT PRIMARY KEY`.
Timestamps `DATETIME DEFAULT CURRENT_TIMESTAMP`.

### users
| column | type | notes |
|---|---|---|
| id | INT PK | |
| name | VARCHAR(120) | |
| email | VARCHAR(190) UNIQUE | login id |
| password_hash | VARCHAR(255) | `password_hash()` |
| role | ENUM('admin','editor','viewer') | |
| active | TINYINT(1) DEFAULT 1 | |
| created_at | DATETIME | |

### contacts (donors + vendors)
| column | type | notes |
|---|---|---|
| id | INT PK | |
| type | ENUM('donor','vendor') | |
| name | VARCHAR(190) | |
| email, phone | VARCHAR | optional |
| address | TEXT | optional |
| notes | TEXT | optional |
| active | TINYINT(1) DEFAULT 1 | |
| created_at | DATETIME | |

### projects
| column | type | notes |
|---|---|---|
| id | INT PK | |
| name | VARCHAR(190) | |
| code | VARCHAR(40) | optional short code |
| description | TEXT | optional |
| active | TINYINT(1) DEFAULT 1 | |
| created_at | DATETIME | |

### categories
| column | type | notes |
|---|---|---|
| id | INT PK | |
| type | ENUM('income','expense') | |
| name | VARCHAR(120) | |
| sort_order | INT DEFAULT 0 | |
| active | TINYINT(1) DEFAULT 1 | |

### income
| column | type | notes |
|---|---|---|
| id | INT PK | |
| date | DATE | |
| contact_id | INT NULL FK→contacts | the donor |
| project_id | INT NULL FK→projects | optional |
| category_id | INT NULL FK→categories | income categories |
| description | VARCHAR(255) | |
| currency | CHAR(3) DEFAULT 'TZS' | 'TZS' or 'USD' |
| amount_original | DECIMAL(15,2) | amount in `currency` |
| exchange_rate | DECIMAL(15,6) DEFAULT 1 | original→TZS rate (snapshot) |
| amount_tzs | DECIMAL(15,2) | stored TZS value (original × rate) |
| reference | VARCHAR(120) | receipt/transfer ref |
| receipt_path | VARCHAR(255) NULL | uploaded file |
| notes | TEXT | |
| created_by | INT FK→users | |
| created_at | DATETIME | |

### expenses
| column | type | notes |
|---|---|---|
| id | INT PK | |
| date | DATE | |
| contact_id | INT NULL FK→contacts | the vendor |
| project_id | INT NULL FK→projects | optional |
| category_id | INT NULL FK→categories | expense categories |
| description | VARCHAR(255) | |
| amount_tzs | DECIMAL(15,2) | TZS only |
| reference | VARCHAR(120) | receipt no. |
| receipt_path | VARCHAR(255) NULL | uploaded file |
| notes | TEXT | |
| created_by | INT FK→users | |
| created_at | DATETIME | |

### settings (key-value)
`setting_key VARCHAR(60) PK`, `setting_value TEXT`. Keys: `org_name`, `org_address`,
`org_email`, `logo`, `base_currency` (default `TZS`), `default_exchange_rate` (optional
convenience default for USD entry).

### activity_log
| column | type | notes |
|---|---|---|
| id | INT PK | |
| user_id | INT NULL FK→users | |
| action | VARCHAR(40) | e.g. create/update/delete/login/export |
| entity_type | VARCHAR(40) | income/expense/contact/… |
| entity_id | INT NULL | |
| description | VARCHAR(255) | |
| created_at | DATETIME | |

Auto-prune to a sane cap (e.g. 1000 newest) like LIPA's logs.

---

## 4. Pages & navigation

Sidebar (LIPA-style). Visibility by role in brackets.

| Page | Purpose | Roles |
|---|---|---|
| **Login** | Email + password (public) | — |
| **Dashboard** | KPIs for selected period: total income, total expenses, balance; recent activity; mini per-project summary | all |
| **Income** | List + add/edit modal; filter by date/donor/project/category | view: all · edit: editor, admin |
| **Expenses** | List + add/edit modal; filter by date/vendor/project/category | view: all · edit: editor, admin |
| **Contacts** | Donors & vendors (filter by type), add/edit | view: all · edit: editor, admin |
| **Projects** | Manage project labels | editor, admin |
| **Reports / Export** | Pick date range → generate `.xlsx` | all |
| **Categories** | Manage income/expense categories | admin |
| **Users** | Manage users + roles | admin |
| **Settings** | Org info, logo, base currency | admin |
| **Activity log** | Audit trail | viewer, admin |

### Role logic
- **viewer (accountant):** read everything + export; no create/update/delete.
- **editor:** CRUD on income, expenses, contacts, projects (+ everything viewer can do).
- **admin:** everything + users, settings, categories.

Enforced server-side on every write route (never rely on hidden UI alone).

---

## 5. Excel export

`phpoffice/phpspreadsheet`, one workbook with multiple sheets for a chosen date range:

1. **Overview** — totals: total income (TZS), total expenses (TZS), balance; counts.
2. **Income** — one row per income entry: date, donor, project, category, description,
   currency, amount original, exchange rate, amount TZS, reference.
3. **Expenses** — one row per expense: date, vendor, project, category, description,
   amount TZS, reference.
4. **Income by category** — summed.
5. **Expenses by category** — summed.
6. **By project** — income/expense/balance per project.

(Mirrors LIPA's multi-table CSV approach. The accountant builds the formal donor/financial
reports by hand from these sheets.)

---

## 6. Starter data (seed)

**Income categories:** Grants (Restricted), Grants (Unrestricted), Individual Donations,
Corporate Donations, Membership & Contributions, Bank/Interest Income, Other Income.

**Expense categories:** Salaries & Wages, Staff Benefits, Office Rent, Utilities,
Travel & Transport, Programme/Project Costs, Training & Workshops, Office Supplies,
Equipment, Professional Fees (Audit/Legal), Bank Charges, Communication,
Repairs & Maintenance, Fundraising Costs, Miscellaneous.

**First admin user:** created via seed (or a one-time setup script) with a known email and
a password the admin must change on first login. Categories are editable in-app afterwards.

---

## 7. Local development & deployment

### Local (already set up)
- **Laragon** installed: PHP 8.3.30, MySQL 8.4.3, Composer, Apache, HeidiSQL.
- Project served from `C:\laragon\www\lipa` → `http://lipa.test` (a symlink or junction
  from `C:\laragon\www\lipa` to `C:\Tools\LIPA Web 26` keeps the source in the user's
  workspace while Laragon serves it). Confirm symlink-vs-copy at implementation time.
- Run Laragon "Start All" to bring up Apache + MySQL.

### Deployment to DomainFactory
- Create subdomain `lipa.pepea-africa.org`, set **document root to `public/`**.
- Upload via Git (`git pull` over SSH) or SFTP.
- `composer install` once over SSH.
- Import `db/schema.sql` + `db/seed.sql` (phpMyAdmin or CLI).
- Create `.env` **outside** `public/` (DB credentials, app secret) — never committed.
- HTTPS enforced.

### Updates
- Edit locally → commit → `git pull` on server. Schema changes via numbered idempotent
  migration files in `db/migrations/`.

---

## 8. Out of scope (YAGNI for v1)
- Double-entry / general ledger, trial balance, balance sheet.
- Per-grant budgets and burn-down tracking.
- USD expenses; multi-currency beyond USD-income snapshot.
- Bank reconciliation / bank feed import.
- PDF report generation (accountant produces formal reports by hand).
- Email notifications, password-reset email flow (admin can reset in v1).
- Swahili UI (English UK only for v1).

---

## 9. Open items to confirm at planning time
- Symlink vs. copy for serving the workspace folder under Laragon.
- Exact first-admin bootstrap mechanism (seed row vs. interactive setup page).
- Whether `default_exchange_rate` setting is worth including or just enter rate per income.
