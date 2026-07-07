# Money Flow (Sankey) — Design

**Date:** 2026-07-08
**Status:** Approved (user)

## Goal

An **admin-only** Sankey diagram under Reports that visualises the money flow for a period:
income **sources** (left) → **accounts** (middle, with account-to-account transfers) → expense
**categories** (right). Ribbon width = amount (TZS). Green = money in, blue = transfer, red = out.

## Placement & access

- New report card **"Money flow (Sankey)"** on `/reports`, rendered **only for admins**
  (`App\Auth::is('admin')` in the view).
- Route `GET /reports/sankey` → `ReportController::sankey()` with **server-side**
  `Auth::requireRole('admin')` (defence in depth — never rely on the hidden card alone).
- Opens as a standalone in-app page (new tab) styled with the app tokens (`theme.css` +
  the org `--accent`), screen-oriented and theme-aware (light/dark like the app), with a
  "Print / Save as PDF" button and a Back link (mirrors the statement pages).
- Period via `date_from` / `date_to` query params; default = current calendar year
  (same as the other report cards).

## Data model — `App\Reports\MoneyFlow::build(string $from, string $to): array`

Pure aggregator (queried via `Database::pdo()`, prepared date-range params). Returns exactly
what the render JS needs:

```php
[
  'from' => $from, 'to' => $to,
  'nodes' => [ ['id'=>'src:Global Fund','label'=>'Global Fund','col'=>0], ... ],
  'links' => [ ['s'=>'src:Global Fund','t'=>'acc:Bank (TZS)','v'=>800000.0,'kind'=>'in'], ... ],
  'totals' => ['in'=>..., 'transfer'=>..., 'out'=>...],
]
```

Node id namespacing (avoids cross-column collisions): `src:<label>`, `acc:<label>`,
`exp:<label>`. Columns: 0 = income source, 1 = account, 2 = expense category.

Aggregation (all filtered to `date BETWEEN :from AND :to`):
- **Income (kind `in`):** group by source + account.
  Source = `contacts.name` when `contacts.type='donor'`, else **"Other income"** (covers
  no-contact income such as bank interest — consistent with the income-by-donor decision).
  Account = `accounts.name`, or **"Unassigned"** when null.
- **Transfers (kind `transfer`):** group by from-account → to-account (names, "Unassigned"
  when null). Skipped entirely if the transfers table has none in range.
- **Expenses (kind `out`):** group by account → category. Category = `categories.name`, or
  **"(uncategorised)"** when null.

Only groups with a positive summed amount produce a link; a node is emitted only if it is
referenced by at least one link. Money is fungible in a cashbook — the **account is the hub**,
this is not a 1:1 "donation X paid expense Y" claim (stated in the page footer).

## Rendering — `public/assets/js/sankey.js`

A small self-contained layout+render engine (no dependencies). Reads the payload from a
`<script type="application/json" id="sankey-data">` block on the page and draws inline SVG.

Layout (generic, any number of accounts):
- Three columns by `col`. Node value: col0 = Σ outgoing, col2 = Σ incoming, col1(account) =
  max(Σ in, Σ out) where in = income-in + transfer-in, out = expense-out + transfer-out.
- One width scale = min over columns of `(availableHeight − padding) / columnSum`, so ribbon
  widths are comparable across the whole diagram; a per-ribbon 2.5px floor keeps tiny flows
  visible.
- Ribbons: income col0→col1 (green `--pos`), expense col1→col2 (red `--neg`), transfer
  col1→col1 (blue) drawn as a gentle right-bowing loop between the two account nodes.
- Interactions: per-ribbon hover (highlight + tooltip "Source → Target · amount · kind"),
  others dimmed. A **table view** of every flow is rendered below the SVG (accessibility +
  print). Respects `prefers-reduced-motion`.
- Colours come from app tokens; the page defines `--flow-transfer` (light `#2a78d6`,
  dark `#3987e5`) — validated green/blue/red (CVD all-pass).

## View — `views/reports/sankey.php`

Standalone HTML page: pre-paint theme script (saved `lipa_theme`, like `_shell.php`),
`theme.css`, an inline style block for the Sankey specifics, the JSON payload, the SVG host,
the flow table, and `sankey.js` via `asset()`. Empty-period state: a friendly "No transactions
in this period" message instead of an empty chart.

## Testing

- **Unit (DatabaseTestCase) `tests/MoneyFlowTest.php`:**
  - income grouped source→account, donor named vs "Other income" bucket for non-donor.
  - transfers produce `transfer` links; none → no transfer links/nodes.
  - expenses grouped account→category, null category → "(uncategorised)".
  - nodes unique & correctly columned; only linked nodes emitted; date range respected.
- **Manual:** open `/reports/sankey` as admin locally (green/blue/red, hover, table, dark
  mode, print); confirm the card is hidden for a non-admin.

## Files

- Create: `src/Reports/MoneyFlow.php`, `views/reports/sankey.php`,
  `public/assets/js/sankey.js`, `tests/MoneyFlowTest.php`
- Modify: `src/Controllers/ReportController.php` (add `sankey()`), `public/index.php` (route),
  `views/reports/index.php` (admin-only card)

## Out of scope (for now)

Per-project / per-donor filtering of the flow, exporting the diagram as an image, and the
2-account "satellite" layout (generic stacking chosen instead).
