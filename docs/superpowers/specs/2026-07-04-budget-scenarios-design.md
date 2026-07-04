# LIPA Web — Feature A: Production Budget Scenarios — Design Spec

**Date:** 2026-07-04
**Status:** Approved design — pending implementation plan
**Branch:** `feature-budget-scenarios`
**Source:** `BUDGET-HANDOFF.md` + `lipa-budget-mockup.html` + `lipa-budget-print-preview.html` (Downloads), reconciled with the user's decisions below. **Updated for multi-product scenarios (pottery case).**

## Purpose
A **planning tool** for income-generating vocational activities. A scenario answers: what does it cost to start, what does each product cost to make and sell, what is the monthly profit under pessimistic/realistic/optimistic sales, when is the (NGO-funded share of the) investment recovered, and which recurring NGO costs does the profit cover? First case: hard-soap production. The tool is **generic** and handles a **product mix**: one scenario = one production activity/workshop containing **one or more products** (soap = 1 product; a pottery workshop = several products at different prices).

## The firewall (non-negotiable)
LIPA is a **cashbook of actual transactions**. Budget scenarios are a **planning layer only**. They **must never**:
- write to or read as `income` / `expenses` / `transfers` / `accounts`;
- appear in account balances, donor/org statements, or the Excel export;
- be counted as real money anywhere.
Scenarios live in their own tables and their own screens. A test asserts no cross-writes; the screen and print both carry a "planning only" disclaimer.

## Confirmed decisions
1. **Hybrid calculation.** Client-side JS gives a **live preview** (results update as you type); **server-side PHP (`ScenarioCalc`) is the single source of truth** — it computes on load, on save, and for the print page, and is what the tests cover. JS mirrors the same formulas purely for the live feel.
2. **One page** combines inputs **and** results (edit + live results together), plus a separate print page. Not a form→detail split.
3. **Multi-product & generic.** A scenario is a production **activity** with a **list of products**. Shared at the scenario level: start-up costs, partner funding, monthly fixed costs, allocations, status, linked project. **Per product:** name, `unit_name` (bar/jar/bowl…), sale price, cost-to-make-one, and its own three monthly volumes. Revenue and variable cost **sum across the product mix**. Nothing is product-hardcoded.
4. **Cost per product is derived from its own materials.** Each product has a **materials-per-batch**
   list (`budget_product_materials`) + a **`batch_yield`**; `unit_cost = Σ materials ÷ batch_yield`,
   computed live in JS and cached on save. Different products carry different materials (pottery
   bowl vs vase). A flat-cost product = one material line, yield 1. Products render as **collapsible
   cards** (each with its nested materials table). *(This replaces the earlier "single cost number"
   idea after the user asked for the mockup's per-batch calculation, bound per product.)*
5. **`funded_amount`** is a single scalar on the scenario (one partner-funding line), subtracted from total start-up so break-even is on the NGO's own share (with a secondary "without funding" figure).
6. **Whole-number display** on screen and print (planning projections read cleaner); amounts are **stored** as `DECIMAL(15,2)`.
7. **Nav:** "Budget" sits **directly under Reports, in the same `.nav-group` box**.
8. **Roles:** view = all; create/edit = admin + coordinator (editor); accountant (viewer) = read-only — same guard pattern as Activities.

## Data model (2 tables + 2 children; idempotent migration + schema.sql)

### `budget_scenarios` — the activity (shared level)
| column | type | notes |
|---|---|---|
| id | INT PK | |
| name | VARCHAR(190) NOT NULL | e.g. "Pottery workshop — Karatu" |
| description | TEXT NULL | |
| project_id | INT NULL FK→projects **ON DELETE SET NULL** | optional link |
| status | ENUM('draft','active','archived') NOT NULL DEFAULT 'draft' | |
| funded_amount | DECIMAL(15,2) NOT NULL DEFAULT 0 | partner funding, subtracted from start-up |
| created_by | INT NULL FK→users ON DELETE SET NULL | |
| created_at, updated_at | DATETIME | |

*(No price/unit/volume on the scenario — those live on each product.)*

### `budget_products` — one row per product in the mix (**NEW**)
| column | type | notes |
|---|---|---|
| id | INT PK | |
| scenario_id | INT NOT NULL FK→budget_scenarios **ON DELETE CASCADE** | |
| name | VARCHAR(190) NOT NULL | e.g. "Decorative bowl" |
| unit_name | VARCHAR(20) NOT NULL DEFAULT 'unit' | bar / bowl / jar |
| sale_price | DECIMAL(15,2) NOT NULL DEFAULT 0 | per unit, TZS |
| unit_cost | DECIMAL(15,2) NOT NULL DEFAULT 0 | cost to make one |
| units_low | INT NOT NULL DEFAULT 0 | pessimistic units/month |
| units_mid | INT NOT NULL DEFAULT 0 | realistic |
| units_high | INT NOT NULL DEFAULT 0 | optimistic |
| notes | VARCHAR(255) NULL | |
| sort | INT NOT NULL DEFAULT 0 | |

### `budget_items` — shared costs (start-up + monthly fixed)
| column | type | notes |
|---|---|---|
| id | INT PK | |
| scenario_id | INT NOT NULL FK→budget_scenarios **ON DELETE CASCADE** | |
| item_type | ENUM('one_time','monthly_fixed') NOT NULL | *(no per_batch — materials are per-product `unit_cost`)* |
| name | VARCHAR(190) NOT NULL | |
| amount | DECIMAL(15,2) NOT NULL DEFAULT 0 | |
| notes | VARCHAR(255) NULL | |
| sort | INT NOT NULL DEFAULT 0 | |

### `budget_allocations` — "profit pays for…"
| column | type | notes |
|---|---|---|
| id | INT PK | |
| scenario_id | INT NOT NULL FK→budget_scenarios **ON DELETE CASCADE** | |
| name | VARCHAR(190) NOT NULL | e.g. "Health insurance" |
| monthly_amount | DECIMAL(15,2) NOT NULL DEFAULT 0 | |
| sort | INT NOT NULL DEFAULT 0 | waterfall order |

CASCADE for products/items/allocations (meaningless without their scenario); SET NULL for `project_id`/`created_by` (app convention).

## Calculation — `src/Budget/ScenarioCalc.php`
Pure static `compute(array $scenario, array $products, array $items, array $allocations): array` — no HTTP, no DB; fully unit-testable. Returns:
- `one_time_total` = Σ `one_time` item amounts
- `net_startup` = `max(one_time_total − funded_amount, 0)`
- `fixed_total` = Σ `monthly_fixed` item amounts
- **Per product** `p`: `margin_p = sale_price_p − unit_cost_p` (flag `margin_negative` if ≤ 0). For each case `low|mid|high`: `revenue_pc = units_pc × sale_price_p`, `variable_pc = units_pc × unit_cost_p`, `contribution_pc = units_pc × margin_p`.
- **Per case** `c` (totals across the mix): `revenue_c = Σ_p revenue_pc`, `variable_c = Σ_p variable_pc`, `profit_c = revenue_c − variable_c − fixed_total`; `break_even_months_c = profit_c > 0 ? net_startup ÷ profit_c : null` ("not recovered at this volume"); `break_even_unfunded_c = profit_c > 0 ? one_time_total ÷ profit_c : null` (secondary note).
- **Allocation waterfall** on the **mid** (realistic) `profit_mid`, walked in `sort` order: `remaining = max(profit_mid, 0)`; each allocation `coverage = amount > 0 ? min(remaining/amount, 1) : 0`, then `remaining = max(remaining − amount, 0)`; final `leftover = remaining` (→ "reserves"). Returns per-allocation `coverage_pct` + a summary note.
- Monetary outputs are floats rounded to 2 decimals (storage precision); **views** display with `number_format($v, 0)` (whole numbers). Volumes/units are integers; `break_even_months` shown to 1 decimal.

## Screens (server-rendered, existing components)

### 1. Scenario list — `GET /budget`
Ledger table: name, `status` badge (draft/active/archived), linked project, product count, **realistic** monthly profit (green/red), realistic break-even. "New scenario" (admin/editor). All roles view.

### 2. Combined edit + results — `GET /budget/new`, `GET /budget/:id`
One page, server-rendered with stored inputs **and** server-computed results:
- **Base:** name, description, project (select), status.
- **Products** — add/remove editable rows (item-row pattern): name, unit label, sale price, cost-to-make-one, and low/mid/high monthly volumes. A small **"batch helper"** control per product (materials total ÷ units per batch) fills the unit-cost field client-side; not persisted separately.
- **Start-up costs** (one_time items) with a `funded_amount` line and a computed "NGO share to recover".
- **Fixed costs / month** (monthly_fixed items).
- **Allocations** (name + monthly amount rows).
- **Results:** KPI cards — realistic monthly profit (accent **hero**), break-even, total realistic revenue; a **per-product table** (name, price, unit cost, margin, realistic units/mo, monthly contribution) so the mix is visible; the **three-cases** totals table (revenue / variable / fixed / profit / break-even; realistic column accent-tinted); and the **allocation coverage** bars (`.cat-bar` style, "Rent — 63% covered").
- **Live preview:** scoped JS mirrors `ScenarioCalc`, updating every result on input, purely client-side. **Save** (POST) persists all inputs (scenario + products + items + allocations) and re-renders authoritative server results. Accountant: inputs `disabled`, no Save, results shown.
- Save/delete log via `Activity::log(...)`; CSRF auto-injected.

### 3. Printable — `GET /budget/:id/print`
Standalone page (bypasses the shell, links `theme.css` + `print.css`, injects the org accent — same pattern as `reports/statement.php`): org header (name/TIN/No.), the products table, start-up (with funding → NGO share) and monthly fixed blocks, summary KPIs, the three-cases totals table, allocation coverage, an "Assumptions" line, and the **planning-only disclaimer**. Server-computed values only. All roles.

## Model, controller, routes, nav
- **`src/Models/BudgetScenario.php`** — `create/all/find/update/delete`; children managed inside (mirroring `ActivityItem::setExpenses`): `products(id)`, `items(id)`, `allocations(id)`, `setProducts(id, rows)`, `setItems(id, rows)`, `setAllocations(id, rows)`. `all()` joins `project_name`; the list view calls `ScenarioCalc` per scenario for product count + realistic profit/break-even.
- **`src/Controllers/BudgetController.php`** — `index|create|store|show|update|delete|print`. Guards: view `admin,editor,viewer`; write `admin,editor`. `/budget/:id` (show) is the combined edit+results page.
- **Routes** in `public/index.php` (lazy closures): `GET /budget`, `GET /budget/new`, `POST /budget`, `GET /budget/:id`, `POST /budget/:id`, `POST /budget/:id/delete`, `GET /budget/:id/print`.
- **Nav** (`_shell.php`): add **Budget** in the **same `.nav-group` as Reports, directly under it**. Visible to all roles.

## Testing
- **`ScenarioCalcTest`** (PHPUnit, pure): net startup (funded vs not, funded > total → 0); per-product margin (incl. ≤ 0); **multi-product** case totals (revenue/variable/profit summed across ≥2 products, incl. a loss-making product dragging the mix); single-product case (soap) still correct; break-even (profit ≤ 0 → null; funded vs unfunded); allocation waterfall order + partial coverage + leftover; 2-decimal rounding.
- **`BudgetScenarioTest`** (DatabaseTestCase): CRUD + `setProducts`/`setItems`/`setAllocations` round-trip and CASCADE cleanup.
- **Firewall test:** creating/saving a scenario writes nothing to `income`/`expenses`/`transfers`/`accounts` (row counts unchanged).
- **Controller/e2e:** role guards (editor writes, viewer 403 on write / 200 read-only, all view list + print); print renders with server-computed figures.

## Out of scope
Multi-currency (TZS only); "convert scenario to real expenses"; **Feature B** (per-category yearly budgets — separate branch/spec later); charts or JS chart libraries (CSS bars only); side-by-side scenario comparison; multiple funders per scenario; persisted per-product material breakdowns (the batch helper is entry-only).
