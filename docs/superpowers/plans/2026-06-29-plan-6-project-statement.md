# LIPA Web — Plan 6: Project (Donor) Statement — Implementation Plan

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A printable per-project donor/grant statement (opening → received → spent → closing, with line detail) for a chosen period, output as a clean print-to-PDF HTML page. TZS only, no new dependencies, no schema change.

**Architecture:** A testable `App\Reports\ProjectStatement::build()` computes the figures by reusing the existing `Income`/`Expense` project+date filters. `ReportController::statement()` renders a standalone print view (bypassing the app shell). The Reports page gains a project+period form that opens the statement in a new tab.

**Tech Stack:** PHP 8.3, PDO, PHPUnit, vanilla PHP print view.

## Global Constraints

- **TZS** balances; income lines also show the original currency/amount when `currency != 'TZS'`.
- **No schema change** — figures computed from existing data via `Income`/`Expense` filters (`project_id`, `date_from`, `date_to`).
- **Print-to-PDF HTML** — standalone page + print CSS + a "Print / Save as PDF" button hidden via `@media print`. No PDF library.
- Access: **all roles** (`admin`,`editor`,`viewer`).
- Money display: `number_format($v, 2)`. Escape output with the global `e()` helper.
- Tests run against `lipa_test` via `Tests\DatabaseTestCase`.

Toolchain (local): prefix shell commands with
`export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`.

---

## File structure

```
src/Reports/ProjectStatement.php   (new — figure/line builder)
src/Controllers/ReportController.php (add statement(); index() passes projects)
views/reports/statement.php        (new — standalone print page)
views/reports/index.php            (add the project-statement form)
public/index.php                   (add GET /reports/statement route)
tests/ProjectStatementTest.php     (new)
```

---

### Task 1: ProjectStatement builder (TDD)

**Files:**
- Create: `src/Reports/ProjectStatement.php`
- Test: `tests/ProjectStatementTest.php`

**Interfaces:**
- Consumes: `Income::totalTzs/all/byCategory? (no)`, `Expense::totalTzs/all/byCategory`, `Project::find` — all existing.
- Produces: `App\Reports\ProjectStatement::build(int $projectId, string $from, string $to): array` with keys `project, from, to, opening, received, spent, closing, income_lines, expense_by_category, expense_lines`.

- [ ] **Step 1: Write the failing test** `tests/ProjectStatementTest.php`

```php
<?php
namespace Tests;
use App\Reports\ProjectStatement;
use App\Models\Project;
use App\Database;

final class ProjectStatementTest extends DatabaseTestCase
{
    public function test_build_computes_opening_received_spent_closing(): void
    {
        $pid = Project::create(['name'=>'Verein 2026','code'=>'','description'=>'']);
        $pdo = Database::pdo();
        // before the period: income 1000, expense 300  -> opening 700
        $pdo->exec("INSERT INTO income (date,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-01-05',$pid,'TZS',1000,1,1000)");
        $pdo->exec("INSERT INTO expenses (date,project_id,amount_tzs) VALUES ('2026-01-06',$pid,300)");
        // in the period: income 500, expense 200
        $pdo->exec("INSERT INTO income (date,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-10',$pid,'TZS',500,1,500)");
        $pdo->exec("INSERT INTO expenses (date,project_id,amount_tzs) VALUES ('2026-02-15',$pid,200)");
        // after the period (must be ignored entirely)
        $pdo->exec("INSERT INTO income (date,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-03-10',$pid,'TZS',9999,1,9999)");

        $d = ProjectStatement::build($pid, '2026-02-01', '2026-02-28');
        $this->assertSame('Verein 2026', $d['project']['name']);
        $this->assertEqualsWithDelta(700.0, $d['opening'], 0.001);
        $this->assertEqualsWithDelta(500.0, $d['received'], 0.001);
        $this->assertEqualsWithDelta(200.0, $d['spent'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $d['closing'], 0.001);
        $this->assertCount(1, $d['income_lines']);   // only the in-period income
        $this->assertCount(1, $d['expense_lines']);   // only the in-period expense
    }

    public function test_build_returns_null_project_for_unknown_id(): void
    {
        $d = ProjectStatement::build(99999, '2026-01-01', '2026-12-31');
        $this->assertNull($d['project']);
        $this->assertEqualsWithDelta(0.0, $d['closing'], 0.001);
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ProjectStatementTest.php`
Expected: FAIL — class `App\Reports\ProjectStatement` not found.

- [ ] **Step 3: Write `src/Reports/ProjectStatement.php`**

```php
<?php
namespace App\Reports;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Project;

final class ProjectStatement
{
    public static function build(int $projectId, string $from, string $to): array
    {
        $project = Project::find($projectId);
        $before  = (new \DateTime($from))->modify('-1 day')->format('Y-m-d');
        $period  = ['project_id' => $projectId, 'date_from' => $from, 'date_to' => $to];
        $prior   = ['project_id' => $projectId, 'date_to' => $before];

        $opening  = round(Income::totalTzs($prior) - Expense::totalTzs($prior), 2);
        $received = round(Income::totalTzs($period), 2);
        $spent    = round(Expense::totalTzs($period), 2);

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
            'expense_lines'       => Expense::all($period),
        ];
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ProjectStatementTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Reports/ProjectStatement.php tests/ProjectStatementTest.php
git commit -m "feat: ProjectStatement builder (opening/received/spent/closing per project)"
```

---

### Task 2: Statement controller + standalone print view + route

**Files:**
- Modify: `src/Controllers/ReportController.php` (add `statement()`)
- Create: `views/reports/statement.php`
- Modify: `public/index.php` (route)

**Interfaces:**
- Consumes: `App\Reports\ProjectStatement::build()`, `App\Models\Setting::all()`, `Auth`.
- Produces: `ReportController::statement(): string` — renders the standalone print page from `$_GET` `project_id`/`date_from`/`date_to`; route `GET /reports/statement`.

- [ ] **Step 1: Add `statement()` to `src/Controllers/ReportController.php`** (after `export()`; add `use App\Models\Setting;` and `use App\Models\Project;` at the top with the others if not present)

```php
    public function statement(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $projectId = (int)($_GET['project_id'] ?? 0);
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';
        $valid = $projectId > 0
            && \DateTime::createFromFormat('Y-m-d', $from)
            && \DateTime::createFromFormat('Y-m-d', $to);

        if (!$valid) {
            return '<p style="font-family:sans-serif;padding:24px">Please choose a project and valid dates. <a href="/reports">Back to Reports</a>.</p>';
        }
        $d = \App\Reports\ProjectStatement::build($projectId, $from, $to);
        if (!$d['project']) {
            return '<p style="font-family:sans-serif;padding:24px">Project not found. <a href="/reports">Back to Reports</a>.</p>';
        }
        $s = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/reports/statement.php';
        return ob_get_clean();
    }
```

- [ ] **Step 2: Write `views/reports/statement.php`** (standalone HTML; `$d` and `$s` in scope; uses the global `e()` helper)

```php
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Statement — <?= e($d['project']['name']) ?></title>
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

<h2>Project Statement</h2>
<p><strong><?= e($d['project']['name']) ?></strong><br>
<span class="muted">Period: <?= e($d['from']) ?> to <?= e($d['to']) ?> &middot; Currency: TZS</span></p>

<div class="summary">
  <div>Opening balance<strong><?= number_format($d['opening'], 2) ?></strong></div>
  <div>Funds received<strong><?= number_format($d['received'], 2) ?></strong></div>
  <div>Expenditure<strong><?= number_format($d['spent'], 2) ?></strong></div>
  <div>Closing balance<strong><?= number_format($d['closing'], 2) ?></strong></div>
</div>

<h3>Funds received</h3>
<table>
  <thead><tr><th>Date</th><th>Donor</th><th>Description</th><th>Original</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['income_lines'] as $r): ?>
    <tr>
      <td><?= e($r['date']) ?></td>
      <td><?= e($r['contact_name'] ?? '') ?></td>
      <td><?= e($r['description'] ?? '') ?></td>
      <td><?= ($r['currency'] !== 'TZS') ? e($r['currency']) . ' ' . number_format((float)$r['amount_original'], 2) : '' ?></td>
      <td class="num"><?= number_format((float)$r['amount_tzs'], 2) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($d['income_lines'])): ?><tr><td colspan="5">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>Expenditure by category</h3>
<table>
  <thead><tr><th>Category</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['expense_by_category'] as $r): ?>
    <tr><td><?= e($r['name'] ?? '(none)') ?></td><td class="num"><?= number_format((float)$r['total'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['expense_by_category'])): ?><tr><td colspan="2">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>Expenditure detail</h3>
<table>
  <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th>Description</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['expense_lines'] as $r): ?>
    <tr>
      <td><?= e($r['date']) ?></td>
      <td><?= e($r['contact_name'] ?? '') ?></td>
      <td><?= e($r['category_name'] ?? '') ?></td>
      <td><?= e($r['description'] ?? '') ?></td>
      <td class="num"><?= number_format((float)$r['amount_tzs'], 2) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($d['expense_lines'])): ?><tr><td colspan="5">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<p class="muted">Generated <?= date('Y-m-d H:i') ?> &middot; LIPA</p>
</body>
</html>
```

- [ ] **Step 3: Add the route in `public/index.php`** (next to the other report routes)

```php
$router->add('GET', '/reports/statement', fn() => (new ReportController())->statement());
```

- [ ] **Step 4: Verify (lint + e2e)**

```bash
php -l src/Controllers/ReportController.php && php -l views/reports/statement.php && php -l public/index.php
```
Seed a project with an income before and inside a period and an expense inside; log in; request
`/reports/statement?project_id=<id>&date_from=2026-02-01&date_to=2026-02-28`. Confirm: HTTP 200, the page shows the org header, the four summary figures (opening/received/spent/closing), the received + expense tables, and a "Print / Save as PDF" button. Request with no project_id → the friendly "choose a project" message. Logged-out request → redirects to `/login`.

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/ReportController.php views/reports/statement.php public/index.php
git commit -m "feat: printable project statement page + route"
```

---

### Task 3: Reports page — project statement form

**Files:**
- Modify: `src/Controllers/ReportController.php` (`index()` passes projects)
- Modify: `views/reports/index.php`

**Interfaces:**
- Consumes: `App\Models\Project::all(true)`.
- Produces: the Reports page renders a project+period form that opens `/reports/statement` in a new tab.

- [ ] **Step 1: Pass projects from `ReportController::index()`** — change the `render('reports/index', [...])` data array to include projects:

```php
        return render('reports/index', [
            'date_from'=>$_GET['date_from'] ?? (date('Y') . '-01-01'),
            'date_to'=>$_GET['date_to'] ?? (date('Y') . '-12-31'),
            'projects'=>\App\Models\Project::all(true),
        ], 'Reports');
```

- [ ] **Step 2: Append the statement form to `views/reports/index.php`** (after the existing export form)

```php
<h2 style="margin-top:28px">Project statement</h2>
<p>A printable statement for one project/grant over a period (opens in a new tab → Print → Save as PDF).</p>
<form method="get" action="/reports/statement" target="_blank" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">Project
    <select name="project_id" required>
      <option value="">—</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="btn btn-primary" type="submit">Open statement</button>
</form>
```

- [ ] **Step 3: Verify (lint + e2e + full suite)**

```bash
php -l src/Controllers/ReportController.php && php -l views/reports/index.php
```
Log in; the Reports page shows the **Project statement** section with a project dropdown + From/To + "Open statement". Picking a project and submitting opens the statement (new tab). Run `composer test` → all green.

- [ ] **Step 4: Commit**

```bash
git add src/Controllers/ReportController.php views/reports/index.php
git commit -m "feat: Reports page project-statement form"
```

---

## Self-Review

**Spec coverage:**
- Keyed off Project, TZS, original currency shown on income lines → Tasks 1, 2. ✓
- Computed opening/received/spent/closing (no schema change) via existing filters → Task 1. ✓
- Standalone print-to-PDF page (print CSS + Print button, hidden on print) bypassing the shell → Task 2. ✓
- Reports-page form (project + period, opens new tab) → Task 3. ✓
- Access all roles; route `GET /reports/statement` → Task 2. ✓
- Out of scope honoured (no PDF lib, no FX math, no schema change). ✓

**Placeholder scan:** None. The builder, controller, and view are complete.

**Type consistency:** `ProjectStatement::build()` returns the exact keys (`project, from, to, opening, received, spent, closing, income_lines, expense_by_category, expense_lines`) consumed by `statement.php` (Task 2). `income_lines`/`expense_lines` use the joined keys (`contact_name`, `category_name`, `currency`, `amount_original`, `amount_tzs`) that `Income::all()`/`Expense::all()` already return. `expense_by_category` rows use `name`/`total` matching `Expense::byCategory()`. The Reports form posts `project_id`/`date_from`/`date_to` that `statement()` reads.
