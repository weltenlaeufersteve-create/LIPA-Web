# LIPA Web — Backlog

Prioritised feature ideas for small-NGO + accountant needs. Keep the app a **simple
cashbook** (not double-entry). Build order top-to-bottom.

## Shipped
- ✅ **Cash & Bank accounts + per-account opening balances + transfers** (covers old items
  1 & 2). Live 2026-06-29: accounts (admin, tabbed), required account on income/expense,
  Transfers action, per-account balances on dashboard + Excel (Transfers & By-account
  sheets). Also: consolidated tabbed admin area, grouped sidebar nav, fixed top-right
  theme toggle.

## Planned (agreed)
3. **Donor / project statement (PDF)** — printable "where did Donor X's / Grant Y's money
   go" statement for funders. Moderate.
4. **Period lock + automatic nightly backup** — lock an audited month/year against edits;
   cron `mysqldump` on the server keeping a few days. Small–moderate.

## Later / nice-to-have
- Budget vs actual (annual, per category or project)
- Recurring entries (monthly salaries, rent)
- Dashboard trend chart (income vs expenses by month; top donors/categories)
- Donation acknowledgement receipts (PDF)
- Auto voucher/receipt numbers on entries
- Per-user preferences (own default date range, etc.)

## Working agreement
Each feature: brainstorm → build on a branch → **user verifies locally** → merge to
`master` → deploy via `git pull` on the server.
