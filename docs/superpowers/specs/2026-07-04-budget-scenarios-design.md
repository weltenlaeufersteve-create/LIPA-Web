# LIPA Web — Feature A: Production Budget Scenarios — Design Spec

**Date:** 2026-07-04
**Status:** Approved design — pending implementation plan
**Branch:** `feature-budget-scenarios`
**Source:** `BUDGET-HANDOFF.md` + `lipa-budget-mockup.html` + `lipa-budget-print-preview.html` (Downloads), reconciled with the user's decisions below.

## Purpose
A **planning tool** for income-generating vocational activities. A scenario answers: what does it cost to start, what does one unit cost, what is the monthly profit under pessimistic/realistic/optimistic sales, when is the (NGO-funded share of the) investment recovered, and which recurring NGO costs does the profit cover? First case: hard-soap production — but the tool is **generic**: one scenario per product/service (soap, honey, baskets, tailoring…).

## The firewall (non-negotiable)
LIPA is a **cashbook of actual transactions**. Budget scenarios are a **planning layer only**. They **must never**:
- write to or read as `income` / `expenses` / `transfers` / `accounts`;
- appear in account balances, donor/org statements, or the Excel export;
- be counted as real money anywhere.
Scenarios live in their own tables and their own screens. A test asserts no cross-writes; the screen and print both carry a "planning only" disclaimer.

## Confirmed decisions
1. **Hybrid calculation.** Client-side JS gives a **live preview** (results update as you type); **server-side PHP (`ScenarioCalc`) is the single source of truth** — it computes on load, on save, and for the print page, and is what the tests cover. JS mirrors the same formulas purely for the live feel.
2. **One page** combines inputs **and** results (edit + live results together), plus a separate print page. Not a form→detail split.
3. **Generic + `unit_name`.** Each scenario stores a unit label (`bar`, `jar`, `loaf`, `litre`; default `unit`) so wording adapts: "Sale price per jar", "jars per batch", "cost per jar", "jars / month". Section headings stay generic. No product hardcoded.
4. **`funded_amount`** is a single scalar on the scenario (one partner-funding line), subtracted from start-up so break-even is on the NGO's own share (with a secondary "without funding" figure).
5. **Whole-number display** on screen and print (planning projections read cleaner); amounts still **stored** as `DECIMAL(15,2)`.
6. **Batch model:** unit cost = Σ per-batch materials ÷ `batch_yield`. `batch_yield = 1` ⇒ a plain per-unit product/service.
7. **Nav:** "Budget" sits **directly under Reports, in the same `.nav-group` box**.
8. **Roles:** view = all; create/edit = admin + coordinator (editor); accountant (viewer) = read-only — same guard pattern as Activities.

## Data model (2 tables + 1 child; idempotent migration + schema.sql)

### `budget_scenarios`
| column | type | notes |
|---|---|---|
| id | INT PK | |
| name | VARCHAR(190) NOT NULL | |
| description | TEXT NULL | |
| project_id | INT NULL FK→projects **ON DELETE SET NULL** | optional link |
| status | ENUM('draft','active','archived') NOT NULL DEFAULT 'draft' | |
| unit_name | VARCHAR(20) NOT NULL DEFAULT 'unit' | e.g. bar/jar/loaf |
| sale_price | DECIMAL(15,2) NOT NULL DEFAULT 0 | per unit, TZS |
| funded_amount | DECIMAL(15,2) NOT NULL DEFAULT 0 | partner funding, subtracted from start-up |
| batch_yield | INT NOT NULL DEFAULT 1 | units per batch (≥1) |
| units_low | INT NOT NULL DEFAULT 0 | pessimistic units/month |
| units_mid | INT NOT NULL DEFAULT 0 | realistic |
| units_high | INT NOT NULL DEFAULT 0 | optimistic |
| created_by | INT NULL FK→users ON DELETE SET NULL | |
| created_at, updated_at | DATETIME | |

### `budget_items`
| column | type | notes |
|---|---|---|
| id | INT PK | |
| scenario_id | INT NOT NULL FK→budget_scenarios **ON DELETE CASCADE** | |
| item_type | ENUM('one_time','per_batch','monthly_fixed') NOT NULL | |
| name | VARCHAR(190) NOT NULL | |
| amount | DECIMAL(15,2) NOT NULL DEFAULT 0 | |
| notes | VARCHAR(255) NULL | |
| sort | INT NOT NULL DEFAULT 0 | |

### `budget_allocations` ("profit pays for…")
| column | type | notes |
|---|---|---|
| id | INT PK | |
| scenario_id | INT NOT NULL FK→budget_scenarios **ON DELETE CASCADE** | |
| name | VARCHAR(190) NOT NULL | e.g. "Health insurance" |
| monthly_amount | DECIMAL(15,2) NOT NULL DEFAULT 0 | |
| sort | INT NOT NULL DEFAULT 0 | waterfall order |

CASCADE for items/allocations (meaningless without their scenario); SET NULL for `project_id`/`created_by` (matches the app convention).

## Calculation — `src/Budget/ScenarioCalc.php`
Pure static function `compute(array $scenario, array $items, array $allocations): array` — no HTTP, no DB; fully unit-testable. Returns:
- `one_time_total` = Σ `one_time` amounts
- `net_startup` = `max(one_time_total − funded_amount, 0)`
- `batch_total` = Σ `per_batch` amounts
- `unit_cost` = `batch_total ÷ max(batch_yield, 1)`
- `fixed_total` = Σ `monthly_fixed` amounts
- `margin` = `sale_price − unit_cost` (result flags `margin_negative = margin <= 0`)
- For each case `low|mid|high` with `units`:
  - `revenue = units × sale_price`
  - `variable = units × unit_cost`
  - `profit = revenue − variable − fixed_total`
  - `batches = units ÷ batch_yield` (1 decimal for display)
  - `break_even_months = profit > 0 ? net_startup ÷ profit : null` (null ⇒ "not recovered at this volume")
  - `break_even_unfunded = profit > 0 ? one_time_total ÷ profit : null` (secondary note)
- **Allocation waterfall** on the **mid** (realistic) profit, walked in `sort` order: `remaining = max(mid_profit, 0)`; each allocation `coverage = amount > 0 ? min(remaining/amount, 1) : 0`, then `remaining = max(remaining − amount, 0)`. Final `leftover = remaining` (→ "reserves"). Returns per-allocation `coverage_pct` + a summary note.
- Monetary outputs are floats rounded to 2 decimals (storage precision); the **views** display them with `number_format($v, 0)` (whole numbers).

## Screens (server-rendered, existing components)

### 1. Scenario list — `GET /budget`
Ledger table: name, `status` badge (draft/active/archived), linked project, **realistic** monthly profit (green/red), realistic break-even. "New scenario" button (admin/editor). All roles can view.

### 2. Combined edit + results — `GET /budget/new`, `GET /budget/:id`
One page (`form-card` sections + results below), server-rendered with stored inputs **and** server-computed results:
- **Base:** name, description, project (select), status, `unit_name`.
- **Three cost sections** as add/remove editable row lists (same markup pattern as the activity expense picker / mockup `.items`): **Start-up costs** (one_time) with a `funded_amount` line + computed "NGO share to recover"; **Materials per batch** (per_batch) with a `batch_yield` line + computed "cost per {unit}"; **Fixed costs / month** (monthly_fixed).
- **Price & volume:** `sale_price`, `units_low/mid/high`.
- **Allocations** list (name + monthly_amount rows).
- **Results:** KPI cards (unit cost, margin [red if ≤0], realistic monthly profit = accent **hero**, break-even), the **three-cases** table (bars sold / batches / revenue / variable / fixed / profit / break-even; realistic column accent-tinted), and the **allocation coverage** bars (`.cat-bar` style, "Rent — 63% covered").
- **Live preview:** a scoped JS (`budget.js` or a guarded block in `app.js`) mirrors `ScenarioCalc` and updates every result on input, purely client-side. **Save** (POST) persists all inputs and re-renders authoritative server results. Accountant: inputs rendered `disabled`, no Save, results shown.
- Save/delete log via `Activity::log(...)`; CSRF auto-injected.

### 3. Printable — `GET /budget/:id/print`
Standalone page (bypasses the shell, links `theme.css` + `print.css`, injects the org accent — same pattern as `reports/statement.php`): org header (name/TIN/No.), the three cost blocks, summary KPIs, the three-cases table, allocation coverage, an "Assumptions" line, and the **planning-only disclaimer**. Server-computed values only. All roles.

## Model, controller, routes, nav
- **`src/Models/BudgetScenario.php`** — `create/all/find/update/delete`; child items + allocations managed inside (mirroring `ActivityItem::setExpenses`): `items(id)`, `allocations(id)`, `setItems(id, rows)`, `setAllocations(id, rows)`. `all()` joins `project_name`; the list view calls `ScenarioCalc` per row for realistic profit/break-even.
- **`src/Controllers/BudgetController.php`** — `index|create|store|show|edit|update|delete|print`. Guards: view `admin,editor,viewer`; write `admin,editor`. (`show` and `edit` are the same combined page; keep one method or alias.)
- **Routes** in `public/index.php` (lazy closures): `GET /budget`, `GET /budget/new`, `POST /budget`, `GET /budget/:id`, `GET /budget/:id/edit`, `POST /budget/:id`, `POST /budget/:id/delete`, `GET /budget/:id/print`.
- **Nav** (`_shell.php`): add **Budget** in the **same `.nav-group` as Reports, directly under it**. Visible to all roles.

## Testing
- **`ScenarioCalcTest`** (PHPUnit, pure): one_time/net startup (funded vs not, funded > total → net 0); unit cost via batch_yield (incl. yield = 1, empty materials → 0); margin (incl. ≤ 0); per-case profit (incl. loss); break-even (profit ≤ 0 → null; funded vs unfunded values); allocation waterfall order + partial coverage + leftover; 2-decimal rounding.
- **`BudgetScenarioTest`** (DatabaseTestCase): CRUD + `setItems`/`setAllocations` round-trip and CASCADE cleanup.
- **Firewall test:** creating/saving a scenario writes nothing to `income`/`expenses`/`transfers`/`accounts` (row counts unchanged).
- **Controller/e2e:** role guards (editor can write, viewer 403 on write / 200 read-only, all can view list + print); print page renders with server-computed figures.

## Out of scope
Multi-currency (TZS only); "convert scenario to real expenses"; **Feature B** (per-category yearly budgets — separate branch/spec later); charts or JS chart libraries (CSS bars only); side-by-side scenario comparison; multiple funders per scenario.
