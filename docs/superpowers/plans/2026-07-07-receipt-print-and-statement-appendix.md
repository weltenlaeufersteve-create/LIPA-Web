# Receipt Printing & Statement Appendix — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make uploaded receipts audit-usable — a per-booking Print action, plus a receipt appendix (photos two-up, PDFs listed) at the end of both the Project and Org statements.

**Architecture:** A pure helper (`ReceiptAppendix`) splits/sorts an expense-row list into image vs PDF receipts; both statement builders feed it their period expenses and expose two new view keys. Views render an image grid + a PDF reference table. A shared `ReceiptStorage::printResponse` powers a new print route on both Income and Expense controllers.

**Tech Stack:** Plain PHP 8.3, PDO/MariaDB, PHPUnit. Server-rendered PHP views, browser Print/Save-as-PDF. No new dependencies.

## Global Constraints

- Plain PHP, no framework; static-method models; `render()`/`e()`/`asset()` helpers.
- UK English UI copy.
- Appendix covers **expense** receipts only; income receipts stay reachable per booking.
- No image resizing (receipts use originals); no server-side PDF merge (PDFs referenced, not embedded).
- Receipts embed via the authenticated file routes `/expenses/:id/receipt` and `/income/:id/receipt`.
- Whole-number/`number_format(...,2)` money display, consistent with existing statement tables.
- Spec: `docs/superpowers/specs/2026-07-07-receipt-print-and-statement-appendix-design.md`.
- Local test runner: `"/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe" vendor/bin/phpunit`
  against the `lipa_test` DB (schema pre-loaded; `DatabaseTestCase` truncates).

---

### Task 1: `ReceiptAppendix` helper (split + sort)

**Files:**
- Create: `src/Reports/ReceiptAppendix.php`
- Test: `tests/ReceiptAppendixTest.php`

**Interfaces:**
- Produces: `App\Reports\ReceiptAppendix::fromExpenses(array $expenseLines): array`
  returning `['images' => array<int,array>, 'pdfs' => array<int,array>]`, each list
  sorted ascending by the row's `date`, rows without `receipt_path` dropped, split by
  file extension (`pdf` → pdfs, else images). Row shape is passthrough (whatever
  `Expense::all()` returns: `id`, `date`, `contact_name`, `category_name`, `amount_tzs`,
  `receipt_path`, …).

- [ ] **Step 1: Write the failing test**

```php
<?php
namespace Tests;
use App\Reports\ReceiptAppendix;

final class ReceiptAppendixTest extends \PHPUnit\Framework\TestCase
{
    private function rows(): array
    {
        return [
            ['id'=>1, 'date'=>'2026-03-10', 'receipt_path'=>'expense_1_aa.jpg'],
            ['id'=>2, 'date'=>'2026-03-02', 'receipt_path'=>'expense_2_bb.PDF'],
            ['id'=>3, 'date'=>'2026-03-05', 'receipt_path'=>''],            // no receipt
            ['id'=>4, 'date'=>'2026-03-01', 'receipt_path'=>'expense_4_cc.png'],
            ['id'=>5, 'date'=>'2026-03-08', 'receipt_path'=>'expense_5_dd.pdf'],
        ];
    }

    public function test_splits_by_extension_case_insensitively(): void
    {
        $out = ReceiptAppendix::fromExpenses($this->rows());
        $this->assertSame([4, 1], array_column($out['images'], 'id')); // png+jpg, date asc
        $this->assertSame([2, 5], array_column($out['pdfs'], 'id'));   // .PDF + .pdf, date asc
    }

    public function test_drops_rows_without_receipt(): void
    {
        $out = ReceiptAppendix::fromExpenses($this->rows());
        $ids = array_merge(array_column($out['images'], 'id'), array_column($out['pdfs'], 'id'));
        $this->assertNotContains(3, $ids);
    }

    public function test_empty_input(): void
    {
        $this->assertSame(['images'=>[], 'pdfs'=>[]], ReceiptAppendix::fromExpenses([]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter ReceiptAppendix`
Expected: FAIL — class `App\Reports\ReceiptAppendix` not found.

- [ ] **Step 3: Write minimal implementation**

```php
<?php
namespace App\Reports;

final class ReceiptAppendix
{
    /**
     * Split expense rows into image vs PDF receipts, each sorted ascending by date.
     * Rows without a receipt_path are dropped.
     *
     * @param array<int,array> $expenseLines
     * @return array{images: array<int,array>, pdfs: array<int,array>}
     */
    public static function fromExpenses(array $expenseLines): array
    {
        $images = [];
        $pdfs = [];
        foreach ($expenseLines as $r) {
            if (empty($r['receipt_path'])) {
                continue;
            }
            $ext = strtolower(pathinfo((string)$r['receipt_path'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $pdfs[] = $r;
            } else {
                $images[] = $r;
            }
        }
        $byDate = static fn(array $a, array $b): int => strcmp((string)$a['date'], (string)$b['date']);
        usort($images, $byDate);
        usort($pdfs, $byDate);
        return ['images' => $images, 'pdfs' => $pdfs];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php vendor/bin/phpunit --filter ReceiptAppendix`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Reports/ReceiptAppendix.php tests/ReceiptAppendixTest.php
git commit -m "feat: ReceiptAppendix helper — split expense receipts into photos/PDFs"
```

---

### Task 2: Expose appendix data from both statement builders

**Files:**
- Modify: `src/Reports/ProjectStatement.php`
- Modify: `src/Reports/OrgStatement.php`
- Test: `tests/OrgStatementTest.php`

**Interfaces:**
- Consumes: `ReceiptAppendix::fromExpenses()` (Task 1), `Expense::all($filters)`.
- Produces: both `build()` results gain `receipt_images` and `receipt_pdfs` keys
  (arrays as returned by the helper).

- [ ] **Step 1: Write the failing test** (append to `tests/OrgStatementTest.php`)

```php
    public function test_build_exposes_receipt_appendix_keys(): void
    {
        $acc = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $pid = Project::create(['name'=>'Water','code'=>'','description'=>'']);
        $pdo = Database::pdo();
        // one image receipt, one pdf receipt, one without receipt — all in period
        $pdo->exec("INSERT INTO expenses (date,account_id,project_id,amount_tzs,receipt_path) VALUES ('2026-02-05',$acc,$pid,100,'expense_a.jpg')");
        $pdo->exec("INSERT INTO expenses (date,account_id,project_id,amount_tzs,receipt_path) VALUES ('2026-02-06',$acc,$pid,200,'expense_b.pdf')");
        $pdo->exec("INSERT INTO expenses (date,account_id,project_id,amount_tzs) VALUES ('2026-02-07',$acc,$pid,300)");

        $d = OrgStatement::build('2026-02-01', '2026-02-28');
        $this->assertCount(1, $d['receipt_images']);
        $this->assertCount(1, $d['receipt_pdfs']);
        $this->assertSame('expense_a.jpg', $d['receipt_images'][0]['receipt_path']);
        $this->assertSame('expense_b.pdf', $d['receipt_pdfs'][0]['receipt_path']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php vendor/bin/phpunit --filter OrgStatement`
Expected: FAIL — undefined key `receipt_images`.

- [ ] **Step 3: Implement — OrgStatement** (`src/Reports/OrgStatement.php`)

Add the `use` and load the period's expenses for the appendix. Replace the `return [...]`
block so it includes the two new keys:

```php
use App\Reports\ReceiptAppendix;
```

```php
        $appendix = ReceiptAppendix::fromExpenses(Expense::all($period));

        return [
            'from' => $from, 'to' => $to,
            'opening'  => round($opening, 2),
            'income'   => $income,
            'expenses' => $expenses,
            'net'      => round($income - $expenses, 2),
            'closing'  => round($closing, 2),
            'income_by_category'  => Income::byCategory($period),
            'expense_by_category' => Expense::byCategory($period),
            'by_project' => $byProject,
            'balances'   => $balances,
            'receipt_images' => $appendix['images'],
            'receipt_pdfs'   => $appendix['pdfs'],
        ];
```

- [ ] **Step 4: Implement — ProjectStatement** (`src/Reports/ProjectStatement.php`)

Reuse the already-loaded expense rows (avoid a second query) and add the keys:

```php
use App\Reports\ReceiptAppendix;
```

```php
        $expenseLines = Expense::all($period);
        $appendix = ReceiptAppendix::fromExpenses($expenseLines);

        return [
            'project'  => $project,
            'from'     => $from,
            'to'       => $to,
            'opening'  => $opening,
            'received' => $received,
            'spent'    => $spent,
            'closing'  => round($opening + $received - $spent, 2),
            'income_lines'        => Income::all($period),
            'expense_by_category' => Expense::byCategory($period),
            'expense_lines'       => $expenseLines,
            'receipt_images' => $appendix['images'],
            'receipt_pdfs'   => $appendix['pdfs'],
        ];
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php vendor/bin/phpunit --filter OrgStatement`
Expected: PASS (all OrgStatement tests, incl. the new one).

- [ ] **Step 6: Commit**

```bash
git add src/Reports/OrgStatement.php src/Reports/ProjectStatement.php tests/OrgStatementTest.php
git commit -m "feat: statement builders expose receipt_images/receipt_pdfs appendix data"
```

---

### Task 3: Per-booking "Print" (Income + Expense)

**Files:**
- Modify: `src/ReceiptStorage.php` (add `printResponse`)
- Modify: `src/Controllers/ExpenseController.php` (add `receiptPrint`)
- Modify: `src/Controllers/IncomeController.php` (add `receiptPrint`)
- Modify: `public/index.php` (2 routes)
- Modify: `views/expenses/form.php`, `views/income/form.php` (Print link)

**Interfaces:**
- Consumes: `ReceiptStorage::extension()`, `ReceiptStorage::path()`.
- Produces: `ReceiptStorage::printResponse(string $basename, string $inlineUrl, string $title): void`
  — for images emits an auto-printing HTML page (`<img>` + `onload="window.print()"`);
  for PDFs sends a `Location:` redirect to `$inlineUrl` (browser PDF viewer prints).
  Controller methods `ExpenseController::receiptPrint(int $id)` and
  `IncomeController::receiptPrint(int $id)`.

This task has no unit test (output/redirect side-effects only); verified manually in Task 5's
review via the running local server. Keep the code minimal and mirror the existing
`receipt()` guards exactly.

- [ ] **Step 1: Add `printResponse` to `src/ReceiptStorage.php`**

```php
    public static function printResponse(string $basename, string $inlineUrl, string $title): void
    {
        if (self::extension($basename) === 'pdf') {
            header('Location: ' . $inlineUrl); // browser's PDF viewer handles printing
            return;
        }
        $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $u = htmlspecialchars($inlineUrl, ENT_QUOTES, 'UTF-8');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
           . '<title>' . $t . '</title>'
           . '<style>html,body{margin:0}img{display:block;max-width:100%;height:auto;margin:0 auto}'
           . '@page{margin:10mm}</style></head>'
           . '<body onload="window.print()"><img src="' . $u . '" alt="' . $t . '"></body></html>';
    }
```

- [ ] **Step 2: Add `receiptPrint` to `src/Controllers/ExpenseController.php`**

Place directly after `receipt()`:

```php
    public function receiptPrint(int $id): never
    {
        Auth::requireRole('admin','editor','viewer');
        $row = Expense::find($id);
        if (!$row || empty($row['receipt_path'])) { http_response_code(404); echo 'Not found'; exit; }
        if (!is_file(\App\ReceiptStorage::path($row['receipt_path']))) { http_response_code(404); echo 'Not found'; exit; }
        \App\ReceiptStorage::printResponse($row['receipt_path'], '/expenses/' . $id . '/receipt', 'Receipt — expense #' . $id);
        exit;
    }
```

- [ ] **Step 3: Add `receiptPrint` to `src/Controllers/IncomeController.php`**

Place directly after `receipt()`:

```php
    public function receiptPrint(int $id): never
    {
        Auth::requireRole('admin','editor','viewer');
        $row = Income::find($id);
        if (!$row || empty($row['receipt_path'])) { http_response_code(404); echo 'Not found'; exit; }
        if (!is_file(\App\ReceiptStorage::path($row['receipt_path']))) { http_response_code(404); echo 'Not found'; exit; }
        \App\ReceiptStorage::printResponse($row['receipt_path'], '/income/' . $id . '/receipt', 'Receipt — income #' . $id);
        exit;
    }
```

- [ ] **Step 4: Register routes in `public/index.php`**

Directly under the two existing receipt routes (routes are anchored `^…$`, so these do not
collide with `/…/:id/receipt`):

```php
$router->add('GET', '/income/:id/receipt/print',   fn($p) => (new IncomeController())->receiptPrint((int)$p['id']));
$router->add('GET', '/expenses/:id/receipt/print', fn($p) => (new ExpenseController())->receiptPrint((int)$p['id']));
```

- [ ] **Step 5: Add the Print link — `views/expenses/form.php:48`**

```php
    <?php if (!empty($r['receipt_path'])): ?><div class="form-hint">Current: <a href="/expenses/<?= (int)$r['id'] ?>/receipt">View receipt</a> &middot; <a href="/expenses/<?= (int)$r['id'] ?>/receipt/print" target="_blank">Print</a></div><?php endif; ?>
```

- [ ] **Step 6: Add the Print link — `views/income/form.php:58`**

```php
    <?php if (!empty($r['receipt_path'])): ?><div class="form-hint">Current: <a href="/income/<?= (int)$r['id'] ?>/receipt">View receipt</a> &middot; <a href="/income/<?= (int)$r['id'] ?>/receipt/print" target="_blank">Print</a></div><?php endif; ?>
```

- [ ] **Step 7: Smoke-check routing (no fatal)**

Run: `php -l src/ReceiptStorage.php && php -l src/Controllers/ExpenseController.php && php -l src/Controllers/IncomeController.php`
Expected: `No syntax errors detected` for each.

- [ ] **Step 8: Commit**

```bash
git add src/ReceiptStorage.php src/Controllers/ExpenseController.php src/Controllers/IncomeController.php public/index.php views/expenses/form.php views/income/form.php
git commit -m "feat: per-booking Print action for receipts (image auto-print, PDF via viewer)"
```

---

### Task 4: Statement appendix views + print styles

**Files:**
- Modify: `views/reports/statement.php`
- Modify: `views/reports/org_statement.php`
- Modify: `public/assets/css/print.css`

**Interfaces:**
- Consumes: `$d['receipt_images']`, `$d['receipt_pdfs']` (Task 2). Each image row is shown via
  `<img src="/expenses/{id}/receipt">`; each PDF row links to `/expenses/{id}/receipt`.

Field availability: `Expense::all()` rows include `date`, `contact_name` (vendor),
`category_name`, `amount_tzs`, `id`, `receipt_path`.

- [ ] **Step 1: Add the appendix partial markup to `views/reports/statement.php`**

Insert immediately **before** the final `<p class="muted">Generated …</p>` line:

```php
<?php if (!empty($d['receipt_images'])): ?>
<section class="receipt-appendix">
  <h3>Appendix — receipt photos</h3>
  <div class="receipt-grid">
    <?php foreach ($d['receipt_images'] as $r): ?>
      <figure class="receipt-fig">
        <figcaption><?= e($r['date']) ?> &middot; <?= e($r['contact_name'] ?? '') ?> &middot; <?= e($r['category_name'] ?? '') ?> &middot; <?= number_format((float)$r['amount_tzs'], 2) ?> TZS</figcaption>
        <img src="/expenses/<?= (int)$r['id'] ?>/receipt" alt="Receipt <?= e($r['date']) ?>">
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($d['receipt_pdfs'])): ?>
<section class="receipt-pdf-list">
  <h3>Appendix — PDF receipts on file</h3>
  <table>
    <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th class="num">Amount (TZS)</th><th>Receipt</th></tr></thead>
    <tbody>
    <?php foreach ($d['receipt_pdfs'] as $r): ?>
      <tr>
        <td><?= e($r['date']) ?></td>
        <td><?= e($r['contact_name'] ?? '') ?></td>
        <td><?= e($r['category_name'] ?? '') ?></td>
        <td class="num"><?= number_format((float)$r['amount_tzs'], 2) ?></td>
        <td><a href="/expenses/<?= (int)$r['id'] ?>/receipt">view</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
```

- [ ] **Step 2: Add the same appendix markup to `views/reports/org_statement.php`**

Insert the identical block (copy the Step-1 markup verbatim) immediately **before** the final
`<p class="muted">Generated …</p>` line (after the Balances table).

- [ ] **Step 3: Add appendix print styles to `public/assets/css/print.css`**

Append:

```css
/* Receipt appendix (statements) */
.receipt-appendix{ break-before:page; }
.receipt-appendix h3, .receipt-pdf-list h3{ margin-top:0; }
.receipt-grid{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.receipt-grid .receipt-fig{ margin:0; break-inside:avoid; border:1px solid #ddd; padding:8px; border-radius:6px; }
.receipt-grid figcaption{ font-size:11px; color:#555; margin-bottom:6px; }
.receipt-grid img{ width:100%; height:auto; }
.receipt-pdf-list{ margin-top:18px; }
```

- [ ] **Step 4: Render-verify both statements (helpers loaded via layout.php)**

Run this throwaway check against the running local app instead (needs auth) OR lint only:
`php -l views/reports/statement.php && php -l views/reports/org_statement.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add views/reports/statement.php views/reports/org_statement.php public/assets/css/print.css
git commit -m "feat: receipt appendix (photos two-up + PDF list) in project & org statements"
```

---

## Final verification (before finishing the branch)

- [ ] Full suite green: `php vendor/bin/phpunit` (expect prior 79 + 4 new = 83 tests).
- [ ] Manual on local server (http://localhost:8000), logged in:
  - Open an expense **with a photo receipt** → **Print** opens a page that auto-triggers the
    print dialog showing the image.
  - Open an expense **with a PDF receipt** → **Print** opens the PDF inline (viewer).
  - Attach a photo + a PDF to two expenses in a period; open **Reports → Project statement**
    and **Org statement** for that period → confirm the **photo appendix** (two-up, date
    order) and the **PDF list** appear; **Save as PDF** and confirm photos print.
  - Confirm a booking **without** a receipt does not appear in either appendix.
- [ ] Then use superpowers:finishing-a-development-branch to deploy (push + `git pull` on
      server) after the user confirms locally.
```
