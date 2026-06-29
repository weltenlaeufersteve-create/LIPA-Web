# LIPA Web — Backlog

Prioritised feature ideas for small-NGO + accountant needs. Keep the app a **simple
cashbook** (not double-entry). Build order top-to-bottom.

## Planned (agreed)
1. **Opening balance** — carry a prior-year leftover into LIPA as a separate figure (not
   counted as income). Shown on the dashboard as its own line:
   `Opening balance + Income − Expenses = Balance`. Small. **← building now**
2. **Cash & Bank accounts (with transfers)** — tag every income/expense to an account
   (Bank TZS, USD, Petty cash, …); add a **Transfer** action (bank → cash) so withdrawals
   and their usage reconcile; per-account balances + statements. Moderate. **← building now**
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
