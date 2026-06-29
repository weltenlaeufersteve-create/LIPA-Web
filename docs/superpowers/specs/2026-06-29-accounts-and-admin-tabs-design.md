# LIPA Web — Accounts, Opening Balances, Transfers & Admin Tabs — Design Spec

**Date:** 2026-06-29
**Status:** Approved design — pending implementation plan
**Branch:** `feature-accounts`

## Purpose
Add **Cash & Bank accounts** to LIPA so every income/expense belongs to an account, each
account carries an **opening balance** (the prior-year carry-over, per account), and money
moved between accounts is recorded as a **transfer** (not income/expense). This lets the
NGO and accountant see, per account: opening balance + income − expenses ± transfers =
current balance — and have it reconcile to the real bank/cash. Also consolidate the
admin-only pages under one tabbed **Settings** area.

Combines backlog items 1 (Opening balance, now per-account) and 2 (Cash & Bank accounts +
transfers). Stays a **simple cashbook** — no double-entry, no per-currency balances.

## Scope decisions (confirmed)
- **TZS-only balances.** A USD donation is still entered with original amount + rate and
  stored as `amount_tzs` (unchanged); accounts track **TZS** balances only. No true USD
  balance, no FX gain/loss.
- **Account required** on every new income & expense, default-selected to "Bank — TZS main".
- **Seed** two accounts: "Bank — TZS main" and "Petty cash". Existing entries get assigned
  to "Bank — TZS main" in the migration.
- **Transfers** are a first-class action (own table + nav item), excluded from income and
  expense totals.
- **Admin consolidation:** sidebar shows a single "Settings" (admin) link leading to a
  tabbed area: Organisation · Accounts · Categories · Users.
- Roles: Accounts CRUD = **admin**; Transfers create/edit/delete = **editor/admin**;
  viewing + reports/export = **all roles** (admin/editor/viewer).

## Data model

### New table `accounts`
| column | type | notes |
|---|---|---|
| id | INT PK | |
| name | VARCHAR(120) NOT NULL | e.g. "Bank — TZS main" |
| type | ENUM('bank','cash','other') NOT NULL DEFAULT 'bank' | display label |
| opening_balance | DECIMAL(15,2) NOT NULL DEFAULT 0 | TZS |
| opening_balance_date | DATE NULL | when the opening balance applies (informational) |
| active | TINYINT(1) NOT NULL DEFAULT 1 | |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | |

### New table `transfers`
| column | type | notes |
|---|---|---|
| id | INT PK | |
| date | DATE NOT NULL | |
| from_account_id | INT NULL FK→accounts ON DELETE SET NULL | |
| to_account_id | INT NULL FK→accounts ON DELETE SET NULL | |
| amount_tzs | DECIMAL(15,2) NOT NULL DEFAULT 0 | |
| description | VARCHAR(255) NULL | |
| created_by | INT NULL FK→users ON DELETE SET NULL | |
| created_at | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | |

### Altered tables
- `income`: add `account_id INT NULL`, FK→accounts ON DELETE SET NULL.
- `expenses`: add `account_id INT NULL`, FK→accounts ON DELETE SET NULL.

Migration file `db/migrations/001-accounts.sql` (idempotent — `CREATE TABLE IF NOT EXISTS`,
and `ALTER TABLE … ADD COLUMN` guarded so re-runs don't fail). It also seeds the two
accounts if the table is empty and backfills existing income/expense rows with the
"Bank — TZS main" id. `db/schema.sql` is updated to include the new tables/columns for
fresh installs.

## Balance calculation
`Account::balance(int $id, ?string $asOf = null): float` =
```
opening_balance
+ SUM(income.amount_tzs    WHERE account_id = id [AND date <= asOf])
- SUM(expenses.amount_tzs  WHERE account_id = id [AND date <= asOf])
+ SUM(transfers.amount_tzs WHERE to_account_id   = id [AND date <= asOf])
- SUM(transfers.amount_tzs WHERE from_account_id = id [AND date <= asOf])
```
`asOf` is optional (used by reports for "as at" balances; dashboard uses no date filter =
current balance). Opening-balance total across accounts feeds the dashboard.

## Components

### Models
- `App\Models\Account` — `create/all/find/update/delete` (+ `active` list), and
  `balance(id, asOf=null)`, `balancesAll(asOf=null)` (id→[name,balance]).
- `App\Models\Transfer` — `create/all(filters)/find/update/delete`, list LEFT JOINs the
  from/to account names; filters by date range.
- `Income`/`Expense` models gain an `account_id` field in create/update + the joined
  `account_name` in `all()`.

### Controllers
- `AccountController` (admin) — CRUD under the Settings tabs.
- `TransferController` (view: all; write: editor/admin) — list + form in main nav.
- `IncomeController`/`ExpenseController` — add account dropdown (default Bank) + validation
  (account required); store/update set `account_id`.
- `DashboardController` — add "Balances by account" + opening-balance-aware totals.
- `SettingController` — Organisation tab (unchanged form), now rendered inside the tab shell.

### Views
- `views/admin/_tabs.php` — shared tab bar (Organisation · Accounts · Categories · Users),
  highlighting the active tab; included at the top of each admin page.
- `views/accounts/index.php`, `views/accounts/form.php`.
- `views/transfers/index.php`, `views/transfers/form.php`.
- Income/Expense forms + index: add Account select / column.
- Dashboard: "Balances by account" table.

### Navigation (`_shell.php`)
Main nav (role-aware): Dashboard · Income · Expenses · **Transfers** · Contacts · Projects ·
Reports · Activity log · **Settings** (admin, single link → Organisation tab). The separate
Categories/Users links are removed (now tabs).

### Excel export (`ExcelExport`)
- Income & Expenses sheets: add an **Account** column.
- New **Transfers** sheet (date, from, to, amount, description).
- New **By account** sheet: opening balance, income, expenses, transfers in/out, closing
  balance per account.

## Testing
- `AccountTest` — CRUD + `balance()` math (opening + income − expense + transfers in − out),
  and `balancesAll()`.
- `TransferTest` — CRUD + joined names + date filter; confirm transfers don't appear in
  `Income::totalTzs`/`Expense::totalTzs`.
- Extend `IncomeTest`/`ExpenseTest` for the `account_id` field round-trip.
- Controller behaviour verified e2e (role guards: account CRUD admin-only; transfers
  editor/admin; viewer read-only) via the dev server, as in prior plans.

## Out of scope (YAGNI)
- True multi-currency / per-currency account balances, FX gain/loss.
- Bank-statement import / auto-reconciliation.
- Splitting one entry across multiple accounts.
- Per-user settings.

## Open items for planning
- Exact tab-highlight mechanism (pass an `$activeTab` string to `_tabs.php`).
- Whether the dashboard date filter should also scope account balances (decision:
  account balances shown are **current/total**, not date-scoped; reports use `asOf`).
