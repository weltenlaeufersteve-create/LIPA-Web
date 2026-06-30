# LIPA Web — Organisation Statement — Design Spec

**Date:** 2026-06-29
**Status:** Approved design — pending implementation plan

## Purpose
A whole-**organisation** Income & Expenditure / funds statement for a date range, as a
printable HTML page (Print → Save as PDF) — the counterpart to the per-project statement.
For boards, donors, and government. TZS only, no schema change.

## Scope decisions (confirmed)
- **Funds statement** (Option A): reconciles **Opening → Income/Expenses → Closing**.
- **Summary only** (no line items — the Excel export covers detail).
- Sections: summary box + Income by category + Expenses by category + By project +
  Balances by account.
- Print-to-PDF HTML, standalone page (bypasses the app shell), like the project statement.
- Period via From/To dates. Access: all roles (admin/editor/viewer).

## How the figures reconcile (existing data only)
Using the existing model helpers (`whereClause` supports `date_from`/`date_to`):
- **Opening** = Σ `Account::balance(id, dayBefore(from))` over **active** accounts
  (`dayBefore(from)` = `(new DateTime(from))->modify('-1 day')->format('Y-m-d')`).
- **Income** = `Income::totalTzs([date_from,date_to])`; **Expenses** = `Expense::totalTzs(...)`;
  **Net** = income − expenses.
- **Closing** = Σ `Account::balance(id, to)` over active accounts. Because transfers net to
  zero across accounts, **Closing = Opening + Net** (reconciles).
- **income_by_category** = `Income::byCategory(period)`; **expense_by_category** =
  `Expense::byCategory(period)`.
- **by_project** = merge `Income::byProject(period)` + `Expense::byProject(period)` by project
  name (NULL → '—'), each row income/expense/balance.
- **balances** = list of active accounts with `Account::balance(id, to)` (closing balance per
  account, as at the end date).

## Components

### `App\Reports\OrgStatement`
- `build(string $from, string $to): array` → keys:
  ```
  from, to, opening, income, expenses, net, closing,
  income_by_category, expense_by_category,
  by_project   // [ ['name'=>?string,'income'=>float,'expense'=>float,'balance'=>float], … ]
  balances     // [ ['name'=>string,'balance'=>float], … ]  (active accounts, closing as at `to`)
  ```
  Unit-tested: opening/income/expenses/net/closing reconcile (incl. an account opening
  balance + an income before the period landing in Opening, and income/expense inside the
  period; assert Closing == Opening + Net).

### `ReportController`
- `orgStatement(): string` — `Auth::requireRole('admin','editor','viewer')`; reads
  `date_from`/`date_to` from `$_GET`; validates dates; calls `OrgStatement::build()`; renders
  the standalone print view via output buffering of `views/reports/org_statement.php` (NOT
  `render()`); returns the HTML. Invalid dates → short message with a link back to `/reports`.

### Views
- `views/reports/org_statement.php` — standalone HTML doc (own `<!DOCTYPE>`, inline print CSS
  reused from the project statement): org header (`Setting::all()` — name/logo, address,
  email, Tax ID, Reg. No), period, summary box (opening/income/expenses/net/closing), then the
  four summary tables. "Print / Save as PDF" button hidden via `@media print`. `number_format($v,2)`.
- `views/reports/index.php` — add a third section **"Organisation statement"**: a `GET` form to
  `/reports/org-statement` (`target="_blank"`) with From/To (default: current year → today)
  and an **Open statement** submit.

### Route (`public/index.php`)
- `GET /reports/org-statement` → `(new ReportController())->orgStatement()`.

## Testing
- `OrgStatementTest` — seed: an account with opening_balance; income before the period
  (→ Opening), income + expense inside the period; assert opening, income, expenses, net, and
  `closing == opening + net`; check `by_project`/`balances` shapes.
- Controller verified e2e on the dev server (page renders with correct figures; print button
  present; invalid dates → friendly message; logged-out → redirect to `/login`).

## Out of scope (v1)
- Line-item detail (use the Excel export).
- Foreign-currency / FX math (TZS only).
- Narrative/commentary, signatures.
