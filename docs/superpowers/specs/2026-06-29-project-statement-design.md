# LIPA Web — Project (Donor) Statement — Design Spec

**Date:** 2026-06-29
**Status:** Approved design — pending implementation plan
**Branch:** `feature-donor-statement`

## Purpose
Produce a quarterly **donor/grant statement** the NGO can send to a funder (e.g. a German
Verein) or a government office. It answers, for one **project** (= one grant/donor) over a
**period**: *opening balance → funds received → expenditure → closing balance*, with line
detail. Output is a clean **print-to-PDF HTML page** (browser Print → Save as PDF) — no new
dependency. **TZS only.**

This implements backlog item 3, keyed off **Project** (one project per grant/donor), because
expenses carry a vendor, not a donor — only the project links "money in from the funder" to
"money spent for the funder's purpose."

## Scope decisions (confirmed)
- **Keyed off Project** (not donor contact).
- **TZS** amounts. Income lines also show the **original** currency/amount for context
  (e.g. €500) but all balances are TZS.
- **Print-to-PDF HTML** (standalone page + print stylesheet + "Print / Save as PDF" button).
  No server-side PDF library.
- **No schema change** — balances are computed from existing income/expense data.
- Period chosen via **From/To** dates (use it for any quarter).
- Access: **all roles** (admin/editor/viewer), like Reports/Export.

## How figures are computed
Using the existing `Income`/`Expense` filters (`project_id`, `date_from`, `date_to`):
- **Opening** = `Income::totalTzs([project_id, date_to = dayBefore(from)])` −
  `Expense::totalTzs([project_id, date_to = dayBefore(from)])`
- **Received** = `Income::totalTzs([project_id, date_from, date_to])`
- **Spent** = `Expense::totalTzs([project_id, date_from, date_to])`
- **Closing** = Opening + Received − Spent

`dayBefore(from)` = `(new DateTime(from))->modify('-1 day')->format('Y-m-d')`.

## Components

### `App\Reports\ProjectStatement`
- `build(int $projectId, string $from, string $to): array` — returns:
  ```
  [
    'project'   => array,            // the project row (or null if not found)
    'from'      => string, 'to' => string,
    'opening'   => float, 'received' => float, 'spent' => float, 'closing' => float,
    'income_lines'        => array,  // Income::all([project_id,date_from,date_to])
    'expense_by_category' => array,  // Expense::byCategory([project_id,date_from,date_to])
    'expense_lines'       => array,  // Expense::all([project_id,date_from,date_to])
  ]
  ```
  Unit-tested for the opening/received/spent/closing math (incl. an entry before the period
  that lands in Opening, and one inside that lands in Received/Spent).

### `ReportController`
- `statement(): string` — `Auth::requireRole('admin','editor','viewer')`; reads `project_id`,
  `date_from`, `date_to` from `$_GET`; validates (project exists, valid dates); calls
  `ProjectStatement::build()`; renders the standalone print view **without** the app shell
  (via output buffering of `views/reports/statement.php`, not `render()`); returns the HTML.
  On invalid input, returns a short message with a link back to `/reports`.

### Views
- `views/reports/index.php` — add a **"Project statement"** section: a `GET` form to
  `/reports/statement` with a **Project** `<select>` (`Project::all()`), **From**/**To** date
  inputs (default: current year start → today), and an **Open statement** submit
  (`target="_blank"`).
- `views/reports/statement.php` — a **complete standalone HTML document** (own `<!DOCTYPE>`,
  inline print CSS): org header (from `Setting::all()`), summary box, received table,
  expense-by-category table, expense detail table, footer with generated date. A
  "Print / Save as PDF" button (`onclick="window.print()"`) hidden via `@media print`.
  Uses `number_format($v,2)` for TZS; income lines show original currency + amount when
  `currency != 'TZS'`.

### Route (`public/index.php`)
- `GET /reports/statement` → `(new ReportController())->statement()`.

## Testing
- `ProjectStatementTest` — seed a project, an income before the period (→ Opening), an income
  and an expense inside the period (→ Received/Spent); assert opening/received/spent/closing
  and that `income_lines`/`expense_lines` contain the in-period rows.
- Controller verified e2e on the dev server: Reports page shows the form; opening the
  statement for a project + period renders the page with correct figures; a print button is
  present; invalid project id returns the friendly message.

## Out of scope (v1)
- Server-side PDF generation (rely on browser Print → Save as PDF).
- EUR/foreign-currency conversion math (TZS balances; original amount shown for context only).
- Combined multi-project / whole-org statements (Reports → Excel already covers totals).
- Narrative/commentary fields, signatures, logos beyond the org header.
- Per-project opening-balance field (computed from transactions instead).

## Open items for planning
- Default period for the form: current calendar year start → today (user narrows to a quarter).
- Whether to show the expense **detail** table by default (decision: yes, include it; it's
  what funders/governments expect — can be revisited if too long).
