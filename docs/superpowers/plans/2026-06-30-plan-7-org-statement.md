# LIPA Web — Plan 7: Organisation Statement — Implementation Plan

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A printable whole-organisation funds statement for a period (opening → income/expenses → net → closing, plus income/expense-by-category, by-project, and balances-by-account), as a standalone print-to-PDF HTML page. TZS only, no schema change.

**Architecture:** A testable `App\Reports\OrgStatement::build()` composes the figures from existing model helpers (`Account::balance` with `asOf`, `Income`/`Expense` `totalTzs`/`byCategory`/`byProject`). `ReportController::orgStatement()` renders a standalone print view (bypasses the app shell). The Reports page gains a third form.

**Tech Stack:** PHP 8.3, PDO, PHPUnit, vanilla PHP print view.

## Global Constraints

- **TZS** only; money display `number_format($v, 2)`; escape with the global `e()` helper.
- **No schema change** — figures computed from existing data.
- **Print-to-PDF HTML** standalone page (own `<!DOCTYPE>`, inline print CSS reused from
  `views/reports/statement.php`; "Print / Save as PDF" button hidden via `@media print`).
- Access: all roles (`admin`,`editor`,`viewer`).
- Reconciliation: **Closing = Opening + Net** must hold (transfers net to zero across accounts).
- Tests run against `lipa_test` via `Tests\DatabaseTestCase`.

Toolchain (local): prefix shell commands with
`export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`.

---

## File structure

```
src/Reports/OrgStatement.php       (new)
src/Controllers/ReportController.php (add orgStatement())
views/reports/org_statement.php    (new — standalone print page)
views/reports/index.php            (add the org-statement form)
public/index.php                   (add GET /reports/org-statement route)
tests/OrgStatementTest.php         (new)
```

---

### Task 1: OrgStatement builder (TDD)

**Files:**
- Create: `src/Reports/OrgStatement.php`
- Test: `tests/OrgStatementTest.php`

**Interfaces:**
- Consumes (existing): `Account::all(true)`, `Account::balance(id, asOf)`, `Income::totalTzs`,
  `Expense::totalTzs`, `Income::byCategory`, `Expense::byCategory`, `Income::byProject`,
  `Expense::byProject`.
- Produces: `App\Reports\OrgStatement::build(string $from, string $to): array` with keys
  `from, to, opening, income, expenses, net, closing, income_by_category, expense_by_category,
  by_project, balances`.

- [ ] **Step 1: Write the failing test** `tests/OrgStatementTest.php`

```php
<?php
namespace Tests;
use App\Reports\OrgStatement;
use App\Models\Account;
use App\Models\Project;
use App\Database;

final class OrgStatementTest extends DatabaseTestCase
{
    public function test_build_reconciles_opening_net_closing(): void
    {
        $acc = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>1000,'opening_balance_date'=>'2026-01-01']);
        $pid = Project::create(['name'=>'Water','code'=>'','description'=>'']);
        $pdo = Database::pdo();
        // before period: income 500 (counts into Opening)
        $pdo->exec("INSERT INTO income (date,account_id,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-01-10',$acc,$pid,'TZS',500,1,500)");
        // in period: income 800, expense 300
        $pdo->exec("INSERT INTO income (date,account_id,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-10',$acc,$pid,'TZS',800,1,800)");
        $pdo->exec("INSERT INTO expenses (date,account_id,project_id,amount_tzs) VALUES ('2026-02-15',$acc,$pid,300)");

        $d = OrgStatement::build('2026-02-01', '2026-02-28');
        // opening = account opening 1000 + prior income 500 = 1500
        $this->assertEqualsWithDelta(1500.0, $d['opening'], 0.001);
        $this->assertEqualsWithDelta(800.0, $d['income'], 0.001);
        $this->assertEqualsWithDelta(300.0, $d['expenses'], 0.001);
        $this->assertEqualsWithDelta(500.0, $d['net'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $d['closing'], 0.001);
        // reconciliation invariant
        $this->assertEqualsWithDelta($d['opening'] + $d['net'], $d['closing'], 0.001);
    }

    public function test_build_by_project_and_balances(): void
    {
        $acc = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $pid = Project::create(['name'=>'Water','code'=>'','description'=>'']);
        $pdo = Database::pdo();
        $pdo->exec("INSERT INTO income (date,account_id,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-10',$acc,$pid,'TZS',800,1,800)");
        $pdo->exec("INSERT INTO expenses (date,account_id,project_id,amount_tzs) VALUES ('2026-02-15',$acc,$pid,300)");

        $d = OrgStatement::build('2026-02-01', '2026-02-28');
        $this->assertSame('Water', $d['by_project'][0]['name']);
        $this->assertEqualsWithDelta(800.0, $d['by_project'][0]['income'], 0.001);
        $this->assertEqualsWithDelta(300.0, $d['by_project'][0]['expense'], 0.001);
        $this->assertEqualsWithDelta(500.0, $d['by_project'][0]['balance'], 0.001);
        $this->assertSame('Bank', $d['balances'][0]['name']);
        $this->assertEqualsWithDelta(500.0, $d['balances'][0]['balance'], 0.001);
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/OrgStatementTest.php`
Expected: FAIL — class `App\Reports\OrgStatement` not found.

- [ ] **Step 3: Write `src/Reports/OrgStatement.php`**

```php
<?php
namespace App\Reports;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Account;

final class OrgStatement
{
    public static function build(string $from, string $to): array
    {
        $before = (new \DateTime($from))->modify('-1 day')->format('Y-m-d');
        $period = ['date_from' => $from, 'date_to' => $to];

        $opening = 0.0; $closing = 0.0; $balances = [];
        foreach (Account::all(true) as $a) {
            $opening += Account::balance((int)$a['id'], $before);
            $bal = Account::balance((int)$a['id'], $to);
            $closing += $bal;
            $balances[] = ['name' => $a['name'], 'balance' => $bal];
        }

        $income   = round(Income::totalTzs($period), 2);
        $expenses = round(Expense::totalTzs($period), 2);

        // Merge income & expense per project (NULL project -> '—').
        $proj = [];
        foreach (Income::byProject($period) as $r)  { $k = $r['name'] ?? '—'; $proj[$k]['income']  = (float)$r['total']; }
        foreach (Expense::byProject($period) as $r) { $k = $r['name'] ?? '—'; $proj[$k]['expense'] = (float)$r['total']; }
        $byProject = [];
        foreach ($proj as $name => $v) {
            $inc = $v['income'] ?? 0; $exp = $v['expense'] ?? 0;
            $byProject[] = ['name' => $name, 'income' => $inc, 'expense' => $exp, 'balance' => $inc - $exp];
        }

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
        ];
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/OrgStatementTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Reports/OrgStatement.php tests/OrgStatementTest.php
git commit -m "feat: OrgStatement builder (org-wide funds statement figures)"
```

---

### Task 2: orgStatement controller + standalone print view + route

**Files:**
- Modify: `src/Controllers/ReportController.php` (add `orgStatement()`)
- Create: `views/reports/org_statement.php`
- Modify: `public/index.php` (route)

**Interfaces:**
- Consumes: `App\Reports\OrgStatement::build()`, `App\Models\Setting::all()`, `Auth`.
- Produces: `ReportController::orgStatement(): string`; route `GET /reports/org-statement`.

- [ ] **Step 1: Add `orgStatement()` to `src/Controllers/ReportController.php`** (after `statement()`)

```php
    public function orgStatement(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';
        if (!\DateTime::createFromFormat('Y-m-d', $from) || !\DateTime::createFromFormat('Y-m-d', $to)) {
            return '<p style="font-family:sans-serif;padding:24px">Please choose valid dates. <a href="/reports">Back to Reports</a>.</p>';
        }
        $d = \App\Reports\OrgStatement::build($from, $to);
        $s = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/reports/org_statement.php';
        return ob_get_clean();
    }
```

- [ ] **Step 2: Write `views/reports/org_statement.php`** (standalone; `$d` and `$s` in scope; global `e()`)

```php
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Organisation Statement — <?= e($s['org_name'] ?? 'Organisation') ?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:32px;max-width:900px;}
  h1{font-size:1.4rem;margin:0 0 2px;} h2{font-size:1.1rem;margin:20px 0 4px;} h3{font-size:1rem;margin:18px 0 4px;}
  .muted{color:#555;font-size:.9rem;}
  table{width:100%;border-collapse:collapse;margin:8px 0;}
  th,td{border-bottom:1px solid #ddd;padding:6px 8px;text-align:left;font-size:.88rem;}
  th{background:#f0f0f0;}
  .num{text-align:right;}
  .summary{display:flex;gap:12px;flex-wrap:wrap;margin:14px 0;}
  .summary div{border:1px solid #ddd;border-radius:8px;padding:10px 14px;min-width:150px;font-size:.85rem;}
  .summary strong{display:block;font-size:1.15rem;margin-top:2px;}
  .actions{margin:0 0 18px;}
  .btn{padding:8px 14px;border:1px solid #ccc;border-radius:6px;background:#f5f5f5;cursor:pointer;text-decoration:none;color:#111;font-size:.9rem;}
  @media print { .actions{display:none;} body{padding:0;} }
</style>
</head>
<body>
<div class="actions">
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
  <a class="btn" href="/reports">Back</a>
</div>

<h1><?= e($s['org_name'] ?? 'Organisation') ?></h1>
<?php if (!empty($s['org_address'])): ?><div class="muted"><?= nl2br(e($s['org_address'])) ?></div><?php endif; ?>
<?php if (!empty($s['org_email'])): ?><div class="muted"><?= e($s['org_email']) ?></div><?php endif; ?>
<?php if (!empty($s['tax_id']) || !empty($s['ngo_number'])): ?>
  <div class="muted">
    <?php if (!empty($s['tax_id'])): ?>Tax ID: <?= e($s['tax_id']) ?><?php endif; ?>
    <?php if (!empty($s['tax_id']) && !empty($s['ngo_number'])): ?> &middot; <?php endif; ?>
    <?php if (!empty($s['ngo_number'])): ?>Reg. No: <?= e($s['ngo_number']) ?><?php endif; ?>
  </div>
<?php endif; ?>

<h2>Income &amp; Expenditure Statement</h2>
<p class="muted">Period: <?= e($d['from']) ?> to <?= e($d['to']) ?> &middot; Currency: TZS</p>

<div class="summary">
  <div>Opening balance<strong><?= number_format($d['opening'], 2) ?></strong></div>
  <div>Total income<strong><?= number_format($d['income'], 2) ?></strong></div>
  <div>Total expenses<strong><?= number_format($d['expenses'], 2) ?></strong></div>
  <div>Net (surplus/deficit)<strong><?= number_format($d['net'], 2) ?></strong></div>
  <div>Closing balance<strong><?= number_format($d['closing'], 2) ?></strong></div>
</div>

<h3>Income by category</h3>
<table>
  <thead><tr><th>Category</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['income_by_category'] as $r): ?>
    <tr><td><?= e($r['name'] ?? '(none)') ?></td><td class="num"><?= number_format((float)$r['total'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['income_by_category'])): ?><tr><td colspan="2">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>Expenses by category</h3>
<table>
  <thead><tr><th>Category</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['expense_by_category'] as $r): ?>
    <tr><td><?= e($r['name'] ?? '(none)') ?></td><td class="num"><?= number_format((float)$r['total'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['expense_by_category'])): ?><tr><td colspan="2">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>By project</h3>
<table>
  <thead><tr><th>Project</th><th class="num">Income (TZS)</th><th class="num">Expenses (TZS)</th><th class="num">Balance (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['by_project'] as $r): ?>
    <tr><td><?= e($r['name']) ?></td>
      <td class="num"><?= number_format($r['income'], 2) ?></td>
      <td class="num"><?= number_format($r['expense'], 2) ?></td>
      <td class="num"><?= number_format($r['balance'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['by_project'])): ?><tr><td colspan="4">No data for this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>Balances by account (as at <?= e($d['to']) ?>)</h3>
<table>
  <thead><tr><th>Account</th><th class="num">Balance (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['balances'] as $r): ?>
    <tr><td><?= e($r['name']) ?></td><td class="num"><?= number_format($r['balance'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['balances'])): ?><tr><td colspan="2">No accounts.</td></tr><?php endif; ?>
  </tbody>
</table>

<p class="muted">Generated <?= date('Y-m-d H:i') ?> &middot; LIPA</p>
</body>
</html>
```

- [ ] **Step 3: Add the route in `public/index.php`** (next to the other report routes)

```php
$router->add('GET', '/reports/org-statement', fn() => (new ReportController())->orgStatement());
```

- [ ] **Step 4: Verify (lint + e2e)**

```bash
php -l src/Controllers/ReportController.php && php -l views/reports/org_statement.php && php -l public/index.php
```
Seed an account with an opening balance + income/expense in a period; log in; request
`/reports/org-statement?date_from=2026-02-01&date_to=2026-02-28`. Confirm HTTP 200, the summary
box shows opening/income/expenses/net/closing, the four tables render, "Print / Save as PDF"
is present, and **closing = opening + net**. Invalid dates → friendly message; logged-out →
redirect to `/login`.

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/ReportController.php views/reports/org_statement.php public/index.php
git commit -m "feat: printable organisation statement page + route"
```

---

### Task 3: Reports page — organisation statement form

**Files:**
- Modify: `views/reports/index.php`

**Interfaces:**
- Consumes: `$date_from`/`$date_to` already passed to the view by `ReportController::index()`.
- Produces: an org-statement form opening `/reports/org-statement` in a new tab.

- [ ] **Step 1: Append the org-statement form to `views/reports/index.php`** (after the project-statement form)

```php
<h2 style="margin-top:28px">Organisation statement</h2>
<p>A printable whole-organisation Income &amp; Expenditure statement for a period (opens in a new tab → Print → Save as PDF).</p>
<form method="get" action="/reports/org-statement" target="_blank" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="btn btn-primary" type="submit">Open statement</button>
</form>
```

- [ ] **Step 2: Verify (lint + e2e + full suite)**

```bash
php -l views/reports/index.php
```
Log in; the Reports page shows the **Organisation statement** section with From/To + "Open
statement"; submitting opens the org statement (new tab). Run `composer test` → all green.

- [ ] **Step 3: Commit**

```bash
git add views/reports/index.php
git commit -m "feat: Reports page organisation-statement form"
```

---

## Self-Review

**Spec coverage:**
- Funds statement (opening/income/expenses/net/closing) reconciling Closing = Opening + Net → Task 1 (asserted). ✓
- Summary tables: income-by-cat, expense-by-cat, by-project, balances-by-account → Task 1 (data) + Task 2 (view). ✓
- Standalone print page bypassing the shell, org header incl. Tax ID/Reg. No → Task 2. ✓
- Reports form + route `GET /reports/org-statement`, all roles → Tasks 2, 3. ✓
- TZS, no schema change, summary-only → honoured. ✓

**Placeholder scan:** None. Builder, controller, and view are complete.

**Type consistency:** `OrgStatement::build()` returns keys (`from,to,opening,income,expenses,net,
closing,income_by_category,expense_by_category,by_project,balances`) consumed exactly by
`org_statement.php` (Task 2). `by_project` rows use `name/income/expense/balance`; `balances`
rows use `name/balance`; `*_by_category` rows use `name/total` (matching `byCategory()`). The
controller reads `date_from`/`date_to`; the Reports form posts them.
