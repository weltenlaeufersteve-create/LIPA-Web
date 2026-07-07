# Receipt Printing & Statement Appendix — Design

**Date:** 2026-07-07
**Status:** Approved (user)

## Goal

Make uploaded receipts usable for audit/donor purposes in two ways:

1. **Per booking** — add a **Print** action next to the existing **View receipt** link on
   income and expense forms, opening a print-ready page.
2. **In statements** — append the period's expense receipts to the **Project statement** and
   the **Org (Income & Expenditure) statement**: photo receipts embedded (two-up, print with
   the report) and PDF receipts listed as a single-column reference table.

This closes a real gap: today receipts are reachable **only** one-by-one from a booking's edit
form (`views/expenses/form.php:48`), never aggregated for a period.

## Background (current state)

- Receipts (PDF/JPG/PNG, ≤10 MB) are stored in `storage/receipts/` via `App\ReceiptStorage`,
  filename saved on the row as `receipt_path`.
- Served **inline** to logged-in users via `GET /expenses/:id/receipt` and
  `GET /income/:id/receipt` (`ExpenseController::receipt` / `IncomeController::receipt`).
- `Expense::all($filters)` returns `SELECT e.*` → includes `id` and `receipt_path`.
- `ProjectStatement::build` already returns `expense_lines = Expense::all($period)`.
  `OrgStatement::build` returns only aggregates — it must additionally load the period's
  expense rows for the appendix.
- Statements are HTML pages the user turns into PDF via the browser's **Print / Save as PDF**.

## Design

### Part A — Per-booking "Print"

New route + controller method on **both** Income and Expense:

- `GET /expenses/:id/receipt/print` → `ExpenseController::receiptPrint(int $id)`
- `GET /income/:id/receipt/print` → `IncomeController::receiptPrint(int $id)`

Behaviour (shared, factored into `App\ReceiptStorage`):

- **Image (jpg/jpeg/png):** return a minimal HTML page showing the receipt full-width
  (`<img>`), which calls `window.print()` on load (via `onload`). Title = the booking's
  file/reference so the print header is meaningful.
- **PDF:** redirect (`302`) to the existing inline receipt route
  (`/expenses/:id/receipt`) — the browser's built-in PDF viewer handles printing. We do
  **not** try to auto-trigger print for PDFs (unreliable across browsers).
- **Missing file / no receipt:** `404` (same guard as `receipt()`).

Shared helper (keeps controllers DRY):

```php
// App\ReceiptStorage
public static function printResponse(string $basename, string $inlineUrl, string $title): void
```

Emits either the auto-printing HTML wrapper (image) or a `Location:` redirect (pdf).
Controllers pass `$row['receipt_path']`, the inline URL for that id, and a title.

Form change — in `views/expenses/form.php` and `views/income/form.php`, where "View receipt"
is shown, append: ` &middot; <a href="/<entity>/<id>/receipt/print" target="_blank">Print</a>`.

### Part B — Statement appendix: photo receipts (two-up)

New pure helper, unit-tested:

```php
// App\Reports\ReceiptAppendix
/** @return array{images: array<int,array>, pdfs: array<int,array>} */
public static function fromExpenses(array $expenseLines): array
```

- Skips rows with empty `receipt_path`.
- Splits by extension: `pdf` → `pdfs`, everything else → `images`.
- Sorts each list ascending by `date`.
- Returns the two lists; callers already have all display fields (`id`, `date`,
  `contact_name`, `category_name`, `amount_tzs`, `receipt_path`).

Wire into both builders:

- `ProjectStatement::build` — reuse the already-loaded `Expense::all($period)`; add
  `'receipt_images' => ...['images']`, `'receipt_pdfs' => ...['pdfs']`.
- `OrgStatement::build` — load `Expense::all($period)` (org-wide, i.e. no project filter) and
  add the same two keys.

View section (both `statement.php` and `org_statement.php`), rendered only when
`receipt_images` is non-empty:

```
<section class="receipt-appendix">
  <h3>Appendix — receipt photos</h3>
  <div class="receipt-grid">   <!-- 2 columns -->
    for each image:
      <figure>
        <figcaption>{date} · {vendor} · {category} · {amount TZS}</figcaption>
        <img src="/expenses/{id}/receipt" alt="Receipt {date}">
      </figure>
  </div>
</section>
```

`print.css`:
- `.receipt-appendix { break-before: page; }` — appendix starts on a fresh page.
- `.receipt-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }`
- `.receipt-grid figure { break-inside: avoid; }` — no photo split across pages.
- `.receipt-grid img { width: 100%; height: auto; }`

### Part C — Statement appendix: PDF receipts (single-column list)

Rendered only when `receipt_pdfs` is non-empty, directly after the photo appendix:

```
<section class="receipt-pdf-list">
  <h3>Appendix — PDF receipts on file</h3>
  <table>
    <thead><tr><th>Date</th><th>Vendor</th><th>Category</th>
               <th class="num">Amount (TZS)</th><th>Receipt</th></tr></thead>
    <tbody> per pdf:
      <td>{date}</td><td>{vendor}</td><td>{category}</td>
      <td class="num">{amount}</td>
      <td><a href="/expenses/{id}/receipt">view</a></td>
    </tbody>
  </table>
</section>
```

Full width, single column (kept single-column deliberately: 5 columns two-up is too cramped
on A4). Link is clickable on screen; on paper the row itself documents that the receipt is on
file.

## Scope / non-goals

- Appendix covers **expense** receipts only (the audit-relevant side); income receipts remain
  reachable per booking. Not building income receipts into the appendix (YAGNI).
- No server-side PDF merging (would need a new dependency); PDFs stay referenced, not embedded.
- Receipts embed via the authenticated file route — they render while the user is logged in,
  which is always true when they generate the PDF themselves. No public exposure.
- No image resizing for receipts (unlike activity photos); originals are used.

## Testing

- **Unit:** `ReceiptAppendix::fromExpenses` — splits by extension, sorts by date, drops rows
  without `receipt_path`, handles empty input. (`tests/ReceiptAppendixTest.php`)
- **Unit/integration:** `OrgStatement::build` now returns `receipt_images` / `receipt_pdfs`
  keys for a period with mixed receipts.
- Manual: open a booking → Print (image auto-prints, PDF opens in viewer); render both
  statements with mixed receipts and Save as PDF; confirm photos print two-up and PDF list
  shows.

## Files

- Modify: `src/ReceiptStorage.php` (add `printResponse`)
- Modify: `src/Controllers/ExpenseController.php`, `src/Controllers/IncomeController.php`
  (add `receiptPrint`)
- Modify: `public/index.php` (2 routes)
- Modify: `views/expenses/form.php`, `views/income/form.php` (Print link)
- Create: `src/Reports/ReceiptAppendix.php`
- Modify: `src/Reports/ProjectStatement.php`, `src/Reports/OrgStatement.php`
- Modify: `views/reports/statement.php`, `views/reports/org_statement.php`
- Modify: `public/assets/css/print.css`
- Create: `tests/ReceiptAppendixTest.php`; modify `tests/OrgStatementTest.php`
