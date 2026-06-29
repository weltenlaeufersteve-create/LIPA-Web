# LIPA Web — Plan 3: Core Register (Income & Expenses) — Implementation Plan

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** The heart of the app — record Income and Expenses, each linked to a contact / project / category, with multi-currency capture for income (USD → TZS snapshot), date-range/project/category filtering, and secure receipt file uploads viewable by all roles.

**Architecture:** Same lean plain-PHP stack. `Income` and `Expense` models (PDO, TDD) with list queries that LEFT JOIN contact/project/category names. Controllers are role-guarded and render forms whose dropdowns come from the Plan 2 models. Receipts are stored **outside** the web root in `storage/receipts/` and streamed through an authenticated download route.

**Tech Stack:** PHP 8.3, MariaDB/MySQL (PDO), PHPUnit, vanilla PHP views.

## Global Constraints

- PHP **8.3** locally; production **MariaDB**, local **MySQL 8.4** — **portable SQL only**.
- All SQL uses **PDO prepared statements**; dynamic filters bind every value.
- Money columns are `DECIMAL(15,2)`; base currency **TZS**. **Income** may be `TZS` or `USD` and stores `amount_original`, `exchange_rate`, and computed `amount_tzs = round(amount_original * exchange_rate, 2)`. **Expenses are TZS only** (`amount_tzs`).
- UI language **English (UK)**; currency display in app uses thousands separators, 2 decimals.
- **Mobile-first responsive**: list tables wrapped in `<div class="table-wrap">`; existing `app.css` classes only.
- Roles enforced **server-side**:
  - **Income / Expenses** — index + receipt download for `admin`, `editor`, `viewer`; create/store/edit/update/delete require `admin`, `editor`.
- **Receipt files**: stored in project-root `storage/receipts/` (NOT under `public/`), gitignored. Allowed types: PDF, JPG, PNG; max 10 MB. Served only via the authenticated `/income/:id/receipt` and `/expenses/:id/receipt` routes after a role check.
- Tests run against `lipa_test` via `Tests\DatabaseTestCase`.

Toolchain note (local): prefix shell commands with
`export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`.

---

## File structure (created across this plan)

```
src/Models/Income.php          Expense.php
src/ReceiptStorage.php
src/Controllers/IncomeController.php   ExpenseController.php
views/income/index.php   income/form.php
views/expenses/index.php expenses/form.php
views/_filters.php             (shared date/project/category filter bar partial)
tests/IncomeTest.php  ExpenseTest.php  ReceiptStorageTest.php
storage/receipts/.gitkeep
public/index.php               (routes added)
.gitignore                     (ignore storage/receipts/*)
```

---

### Task 1: Income model (TDD)

**Files:**
- Create: `src/Models/Income.php`
- Test: `tests/IncomeTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Income::tzsValue(float $amount, float $rate): float` — `round($amount * $rate, 2)`.
  - `App\Models\Income::create(array $data): int` — keys `date,contact_id,project_id,category_id,description,currency,amount_original,exchange_rate,amount_tzs,reference,notes,created_by` (`receipt_path` optional).
  - `App\Models\Income::all(array $filters = []): array` — LEFT JOINs `contact_name,project_name,category_name`; ordered by `date DESC, id DESC`. Filter keys (all optional): `date_from`, `date_to`, `project_id`, `category_id`.
  - `App\Models\Income::find(int $id): ?array`
  - `App\Models\Income::update(int $id, array $data): void` — same keys as create except `created_by`.
  - `App\Models\Income::setReceipt(int $id, ?string $path): void`
  - `App\Models\Income::delete(int $id): void`
  - `App\Models\Income::totalTzs(array $filters = []): float` — sum of `amount_tzs` honouring the same filters.

- [ ] **Step 1: Write the failing test** `tests/IncomeTest.php`

```php
<?php
namespace Tests;
use App\Models\Income;
use App\Models\Contact;
use App\Models\Category;
use App\Models\Project;

final class IncomeTest extends DatabaseTestCase
{
    private function seedRefs(): array
    {
        return [
            'contact'  => Contact::create(['type'=>'donor','name'=>'Donor X','email'=>'','phone'=>'','address'=>'','notes'=>'']),
            'project'  => Project::create(['name'=>'Proj','code'=>'','description'=>'']),
            'category' => Category::create(['type'=>'income','name'=>'Grants','sort_order'=>1]),
        ];
    }

    public function test_tzs_value_rounds(): void
    {
        $this->assertSame(25000.00, Income::tzsValue(10.0, 2500.0));
        $this->assertSame(2500.50, Income::tzsValue(2500.5, 1.0));
    }

    public function test_create_find_with_joined_names(): void
    {
        $r = $this->seedRefs();
        $id = Income::create([
            'date'=>'2026-03-01','contact_id'=>$r['contact'],'project_id'=>$r['project'],
            'category_id'=>$r['category'],'description'=>'Q1 grant','currency'=>'USD',
            'amount_original'=>1000,'exchange_rate'=>2500,'amount_tzs'=>2500000,
            'reference'=>'WIRE-1','notes'=>'','created_by'=>null,
        ]);
        $row = Income::find($id);
        $this->assertSame('Q1 grant', $row['description']);
        $this->assertSame('USD', $row['currency']);
        $this->assertEquals(2500000, (int)$row['amount_tzs']);
        $all = Income::all();
        $this->assertSame('Donor X', $all[0]['contact_name']);
        $this->assertSame('Grants', $all[0]['category_name']);
        $this->assertSame('Proj', $all[0]['project_name']);
    }

    public function test_filters_and_total(): void
    {
        $r = $this->seedRefs();
        $base = ['contact_id'=>null,'project_id'=>$r['project'],'category_id'=>$r['category'],
                 'description'=>'','currency'=>'TZS','exchange_rate'=>1,'reference'=>'','notes'=>'','created_by'=>null];
        Income::create($base + ['date'=>'2026-01-10','amount_original'=>100,'amount_tzs'=>100]);
        Income::create($base + ['date'=>'2026-02-10','amount_original'=>200,'amount_tzs'=>200]);
        Income::create($base + ['date'=>'2026-03-10','amount_original'=>300,'amount_tzs'=>300]);
        $this->assertCount(2, Income::all(['date_from'=>'2026-02-01','date_to'=>'2026-03-31']));
        $this->assertEqualsWithDelta(500.0, Income::totalTzs(['date_from'=>'2026-02-01','date_to'=>'2026-03-31']), 0.001);
        $this->assertEqualsWithDelta(600.0, Income::totalTzs(), 0.001);
    }

    public function test_update_setReceipt_delete(): void
    {
        $r = $this->seedRefs();
        $id = Income::create(['date'=>'2026-03-01','contact_id'=>null,'project_id'=>null,
            'category_id'=>$r['category'],'description'=>'x','currency'=>'TZS','amount_original'=>50,
            'exchange_rate'=>1,'amount_tzs'=>50,'reference'=>'','notes'=>'','created_by'=>null]);
        Income::update($id, ['date'=>'2026-03-02','contact_id'=>null,'project_id'=>null,
            'category_id'=>$r['category'],'description'=>'y','currency'=>'TZS','amount_original'=>75,
            'exchange_rate'=>1,'amount_tzs'=>75,'reference'=>'R2','notes'=>'n']);
        $row = Income::find($id);
        $this->assertSame('y', $row['description']);
        $this->assertEquals(75, (int)$row['amount_tzs']);
        Income::setReceipt($id, 'income_1_abc.pdf');
        $this->assertSame('income_1_abc.pdf', Income::find($id)['receipt_path']);
        Income::delete($id);
        $this->assertNull(Income::find($id));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/IncomeTest.php`
Expected: FAIL — class `App\Models\Income` not found.

- [ ] **Step 3: Write `src/Models/Income.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class Income
{
    public static function tzsValue(float $amount, float $rate): float
    {
        return round($amount * $rate, 2);
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO income
             (date, contact_id, project_id, category_id, description, currency,
              amount_original, exchange_rate, amount_tzs, reference, receipt_path, notes, created_by)
             VALUES
             (:date, :contact_id, :project_id, :category_id, :description, :currency,
              :amount_original, :exchange_rate, :amount_tzs, :reference, :receipt_path, :notes, :created_by)'
        );
        $stmt->execute(self::bind($data) + [':created_by' => $data['created_by'] ?: null]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE income SET date=:date, contact_id=:contact_id, project_id=:project_id,
             category_id=:category_id, description=:description, currency=:currency,
             amount_original=:amount_original, exchange_rate=:exchange_rate, amount_tzs=:amount_tzs,
             reference=:reference, notes=:notes WHERE id=:id'
        );
        $params = self::bind($data);
        unset($params[':receipt_path']); // receipts handled by setReceipt()
        $stmt->execute($params + [':id' => $id]);
    }

    private static function bind(array $d): array
    {
        return [
            ':date'=>$d['date'],
            ':contact_id'=>$d['contact_id'] ?: null,
            ':project_id'=>$d['project_id'] ?: null,
            ':category_id'=>$d['category_id'] ?: null,
            ':description'=>$d['description'] ?: null,
            ':currency'=>$d['currency'],
            ':amount_original'=>$d['amount_original'],
            ':exchange_rate'=>$d['exchange_rate'],
            ':amount_tzs'=>$d['amount_tzs'],
            ':reference'=>$d['reference'] ?: null,
            ':receipt_path'=>$d['receipt_path'] ?? null,
            ':notes'=>$d['notes'] ?: null,
        ];
    }

    public static function setReceipt(int $id, ?string $path): void
    {
        $stmt = Database::pdo()->prepare('UPDATE income SET receipt_path=:p WHERE id=:id');
        $stmt->execute([':p'=>$path, ':id'=>$id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM income WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT i.*, c.name AS contact_name, p.name AS project_name, cat.name AS category_name
                FROM income i
                LEFT JOIN contacts c   ON c.id = i.contact_id
                LEFT JOIN projects p   ON p.id = i.project_id
                LEFT JOIN categories cat ON cat.id = i.category_id
                ' . $where . ' ORDER BY i.date DESC, i.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function totalTzs(array $filters = []): float
    {
        [$where, $params] = self::whereClause($filters);
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount_tzs),0) FROM income i ' . $where);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /** @return array{0:string,1:array} */
    private static function whereClause(array $f): array
    {
        $cond = []; $params = [];
        if (!empty($f['date_from'])) { $cond[] = 'i.date >= :date_from'; $params[':date_from'] = $f['date_from']; }
        if (!empty($f['date_to']))   { $cond[] = 'i.date <= :date_to';   $params[':date_to']   = $f['date_to']; }
        if (!empty($f['project_id']))  { $cond[] = 'i.project_id = :project_id';   $params[':project_id']  = (int)$f['project_id']; }
        if (!empty($f['category_id'])) { $cond[] = 'i.category_id = :category_id'; $params[':category_id'] = (int)$f['category_id']; }
        return [$cond ? 'WHERE ' . implode(' AND ', $cond) : '', $params];
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM income WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/IncomeTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/Income.php tests/IncomeTest.php
git commit -m "feat: Income model with currency, joined names, filters, totals"
```

---

### Task 2: Income CRUD + filters

**Files:**
- Create: `src/Controllers/IncomeController.php`
- Create: `views/income/index.php`, `views/income/form.php`, `views/_filters.php`
- Modify: `public/index.php`

**Interfaces:**
- Consumes: `App\Models\Income`, `Contact`, `Project`, `Category`, `Auth`, `render()`.
- Produces: `IncomeController::index|create|store|edit|update|delete`. `store`/`update` compute `amount_tzs` via `Income::tzsValue()` and set `created_by` from the session on create. `index` reads filters from `$_GET`.
- `views/_filters.php` expects `$projects`, `$categories`, and `$action` (the form's GET action path) in scope.

- [ ] **Step 1: Write `src/Controllers/IncomeController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Income;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Category;

final class IncomeController
{
    private function filters(): array
    {
        return [
            'date_from'=>$_GET['date_from'] ?? '', 'date_to'=>$_GET['date_to'] ?? '',
            'project_id'=>$_GET['project_id'] ?? '', 'category_id'=>$_GET['category_id'] ?? '',
        ];
    }

    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $f = $this->filters();
        return render('income/index', [
            'rows'=>Income::all($f), 'total'=>Income::totalTzs($f), 'f'=>$f,
            'projects'=>Project::all(), 'categories'=>Category::all('income'),
        ], 'Income');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('income/form', $this->formData(null, null), 'New income');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('income/form', $this->formData($_POST, $error), 'New income'); }
        $d = $this->fields($_POST);
        $d['created_by'] = Auth::user()['id'] ?? null;
        Income::create($d);
        header('Location: /income'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $row = Income::find($id);
        if (!$row) { http_response_code(404); return 'Not found'; }
        return render('income/form', $this->formData($row, null), 'Edit income');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Income::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('income/form', $this->formData(array_merge($_POST,['id'=>$id]), $error), 'Edit income'); }
        Income::update($id, $this->fields($_POST));
        header('Location: /income'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Income::delete($id);
        header('Location: /income'); exit;
    }

    private function formData(?array $row, ?string $error): array
    {
        return [
            'r'=>$row, 'error'=>$error,
            'contacts'=>Contact::all('donor'),
            'projects'=>Project::all(),
            'categories'=>Category::all('income'),
        ];
    }

    private function fields(array $in): array
    {
        $currency = ($in['currency'] ?? 'TZS') === 'USD' ? 'USD' : 'TZS';
        $amount = (float)($in['amount_original'] ?? 0);
        $rate = $currency === 'USD' ? (float)($in['exchange_rate'] ?? 1) : 1.0;
        return [
            'date'=>$in['date'] ?? date('Y-m-d'),
            'contact_id'=>$in['contact_id'] ?? null,
            'project_id'=>$in['project_id'] ?? null,
            'category_id'=>$in['category_id'] ?? null,
            'description'=>trim($in['description'] ?? ''),
            'currency'=>$currency,
            'amount_original'=>$amount,
            'exchange_rate'=>$rate,
            'amount_tzs'=>Income::tzsValue($amount, $rate),
            'reference'=>trim($in['reference'] ?? ''),
            'notes'=>trim($in['notes'] ?? ''),
        ];
    }

    private function validate(array $in): ?string
    {
        if (empty($in['date']) || !\DateTime::createFromFormat('Y-m-d', $in['date'])) return 'A valid date is required.';
        if (!is_numeric($in['amount_original'] ?? null) || (float)$in['amount_original'] <= 0) return 'Amount must be greater than zero.';
        if (($in['currency'] ?? 'TZS') === 'USD' && (!is_numeric($in['exchange_rate'] ?? null) || (float)$in['exchange_rate'] <= 0)) {
            return 'Exchange rate must be greater than zero for USD.';
        }
        return null;
    }
}
```

- [ ] **Step 2: Write `views/_filters.php`** (shared filter bar)

```php
<form method="get" action="<?= e($action) ?>" class="filters" style="display:flex;flex-wrap:wrap;gap:8px;align-items:end">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($f['date_from'] ?? '') ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($f['date_to'] ?? '') ?>"></label>
  <label style="margin:0">Project
    <select name="project_id">
      <option value="">All</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)($f['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label style="margin:0">Category
    <select name="category_id">
      <option value="">All</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= ((int)($f['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit" class="btn">Filter</button>
  <a class="btn" href="<?= e($action) ?>">Clear</a>
</form>
```

- [ ] **Step 3: Write `views/income/index.php`**

```php
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Income</h1>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary" href="/income/new">New income</a>
  <?php endif; ?>
</div>
<?php $action = '/income'; include dirname(__DIR__) . '/_filters.php'; ?>
<p><strong>Total (TZS): <?= number_format($total, 2) ?></strong></p>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Date</th><th>Donor</th><th>Category</th><th>Project</th><th>Description</th><th>Currency</th><th>Amount (orig.)</th><th>Amount (TZS)</th><th>Receipt</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e($row['date']) ?></td>
      <td><?= e($row['contact_name']) ?></td>
      <td><?= e($row['category_name']) ?></td>
      <td><?= e($row['project_name']) ?></td>
      <td><?= e($row['description']) ?></td>
      <td><?= e($row['currency']) ?></td>
      <td><?= number_format((float)$row['amount_original'], 2) ?></td>
      <td><?= number_format((float)$row['amount_tzs'], 2) ?></td>
      <td><?php if (!empty($row['receipt_path'])): ?><a href="/income/<?= (int)$row['id'] ?>/receipt">View</a><?php endif; ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/income/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/income/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this entry?">
            <button type="submit" class="btn-link-danger">Delete</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
```

- [ ] **Step 4: Write `views/income/form.php`** (receipt file input included now; handled in Task 5)

```php
<?php $isNew = empty($r['id']); ?>
<h1><?= $isNew ? 'New income' : 'Edit income' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= $isNew ? '/income' : '/income/' . (int)$r['id'] ?>">
  <label>Date <input type="date" name="date" value="<?= e($r['date'] ?? date('Y-m-d')) ?>" required></label>
  <label>Donor
    <select name="contact_id">
      <option value="">—</option>
      <?php foreach ($contacts as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)($r['contact_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Category
    <select name="category_id">
      <option value="">—</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= ((int)($r['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Project
    <select name="project_id">
      <option value="">—</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)($r['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Description <input name="description" value="<?= e($r['description'] ?? '') ?>"></label>
  <label>Currency
    <select name="currency">
      <?php foreach (['TZS','USD'] as $cur): ?>
        <option value="<?= $cur ?>" <?= (($r['currency'] ?? 'TZS') === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Amount (original currency) <input type="number" step="0.01" name="amount_original" value="<?= e($r['amount_original'] ?? '') ?>" required></label>
  <label>Exchange rate to TZS (only for USD) <input type="number" step="0.000001" name="exchange_rate" value="<?= e($r['exchange_rate'] ?? '1') ?>"></label>
  <label>Reference <input name="reference" value="<?= e($r['reference'] ?? '') ?>"></label>
  <label>Notes <textarea name="notes"><?= e($r['notes'] ?? '') ?></textarea></label>
  <label>Receipt (PDF/JPG/PNG) <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"></label>
  <?php if (!empty($r['receipt_path'])): ?><p>Current receipt: <a href="/income/<?= (int)$r['id'] ?>/receipt">View</a></p><?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/income" class="btn">Cancel</a>
</form>
```

- [ ] **Step 5: Add routes in `public/index.php`** (`use App\Controllers\IncomeController;` with the others)

```php
$router->add('GET',  '/income',              fn() => (new IncomeController())->index());
$router->add('GET',  '/income/new',          fn() => (new IncomeController())->create());
$router->add('POST', '/income',              fn() => (new IncomeController())->store());
$router->add('GET',  '/income/:id/edit',     fn($p) => (new IncomeController())->edit((int)$p['id']));
$router->add('POST', '/income/:id',          fn($p) => (new IncomeController())->update((int)$p['id']));
$router->add('POST', '/income/:id/delete',   fn($p) => (new IncomeController())->delete((int)$p['id']));
```

- [ ] **Step 6: Verify (lint + e2e)**

```bash
php -l src/Controllers/IncomeController.php && php -l views/income/index.php && php -l views/income/form.php && php -l views/_filters.php && php -l public/index.php
```
Start the dev server, seed categories (`php bin/seed-categories.php`), log in as admin:
- Create a TZS income (amount 1000) → listed; Total shows 1,000.00.
- Create a USD income (amount 100, rate 2500) → Amount (TZS) shows 250,000.00.
- Amount ≤ 0 → "Amount must be greater than zero." USD with rate 0 → rate error.
- Filter by date range narrows the list and Total.
- Viewer: `GET /income` 200 but no New/Edit/Delete; `POST /income` 403.

- [ ] **Step 7: Commit**

```bash
git add src/Controllers/IncomeController.php views/income/ views/_filters.php public/index.php
git commit -m "feat: Income CRUD with currency conversion and filters"
```

---

### Task 3: Expense model (TDD)

**Files:**
- Create: `src/Models/Expense.php`
- Test: `tests/ExpenseTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Expense::create(array $data): int` — keys `date,contact_id,project_id,category_id,description,amount_tzs,reference,notes,created_by` (`receipt_path` optional).
  - `App\Models\Expense::all(array $filters = []): array` — LEFT JOINs `contact_name,project_name,category_name`; ordered `date DESC, id DESC`; filters `date_from,date_to,project_id,category_id`.
  - `App\Models\Expense::find(int $id): ?array`
  - `App\Models\Expense::update(int $id, array $data): void`
  - `App\Models\Expense::setReceipt(int $id, ?string $path): void`
  - `App\Models\Expense::delete(int $id): void`
  - `App\Models\Expense::totalTzs(array $filters = []): float`

- [ ] **Step 1: Write the failing test** `tests/ExpenseTest.php`

```php
<?php
namespace Tests;
use App\Models\Expense;
use App\Models\Contact;
use App\Models\Category;
use App\Models\Project;

final class ExpenseTest extends DatabaseTestCase
{
    private function refs(): array
    {
        return [
            'vendor'   => Contact::create(['type'=>'vendor','name'=>'Vendor Y','email'=>'','phone'=>'','address'=>'','notes'=>'']),
            'project'  => Project::create(['name'=>'Proj','code'=>'','description'=>'']),
            'category' => Category::create(['type'=>'expense','name'=>'Rent','sort_order'=>1]),
        ];
    }

    public function test_create_find_with_joined_names(): void
    {
        $r = $this->refs();
        $id = Expense::create(['date'=>'2026-03-01','contact_id'=>$r['vendor'],'project_id'=>$r['project'],
            'category_id'=>$r['category'],'description'=>'March rent','amount_tzs'=>450000,
            'reference'=>'INV-9','notes'=>'','created_by'=>null]);
        $row = Expense::find($id);
        $this->assertSame('March rent', $row['description']);
        $this->assertEquals(450000, (int)$row['amount_tzs']);
        $all = Expense::all();
        $this->assertSame('Vendor Y', $all[0]['contact_name']);
        $this->assertSame('Rent', $all[0]['category_name']);
    }

    public function test_filters_and_total(): void
    {
        $r = $this->refs();
        $base = ['contact_id'=>null,'project_id'=>$r['project'],'category_id'=>$r['category'],
                 'description'=>'','reference'=>'','notes'=>'','created_by'=>null];
        Expense::create($base + ['date'=>'2026-01-05','amount_tzs'=>100]);
        Expense::create($base + ['date'=>'2026-02-05','amount_tzs'=>200]);
        $this->assertCount(1, Expense::all(['date_from'=>'2026-02-01']));
        $this->assertEqualsWithDelta(200.0, Expense::totalTzs(['date_from'=>'2026-02-01']), 0.001);
        $this->assertEqualsWithDelta(300.0, Expense::totalTzs(), 0.001);
    }

    public function test_update_setReceipt_delete(): void
    {
        $r = $this->refs();
        $id = Expense::create(['date'=>'2026-03-01','contact_id'=>null,'project_id'=>null,
            'category_id'=>$r['category'],'description'=>'x','amount_tzs'=>50,'reference'=>'','notes'=>'','created_by'=>null]);
        Expense::update($id, ['date'=>'2026-03-02','contact_id'=>null,'project_id'=>null,
            'category_id'=>$r['category'],'description'=>'y','amount_tzs'=>75,'reference'=>'R2','notes'=>'n']);
        $row = Expense::find($id);
        $this->assertSame('y', $row['description']);
        $this->assertEquals(75, (int)$row['amount_tzs']);
        Expense::setReceipt($id, 'expense_1_x.pdf');
        $this->assertSame('expense_1_x.pdf', Expense::find($id)['receipt_path']);
        Expense::delete($id);
        $this->assertNull(Expense::find($id));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ExpenseTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `src/Models/Expense.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class Expense
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO expenses
             (date, contact_id, project_id, category_id, description, amount_tzs, reference, receipt_path, notes, created_by)
             VALUES
             (:date, :contact_id, :project_id, :category_id, :description, :amount_tzs, :reference, :receipt_path, :notes, :created_by)'
        );
        $stmt->execute(self::bind($data) + [':created_by'=>$data['created_by'] ?: null]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE expenses SET date=:date, contact_id=:contact_id, project_id=:project_id,
             category_id=:category_id, description=:description, amount_tzs=:amount_tzs,
             reference=:reference, notes=:notes WHERE id=:id'
        );
        $params = self::bind($data);
        unset($params[':receipt_path']);
        $stmt->execute($params + [':id'=>$id]);
    }

    private static function bind(array $d): array
    {
        return [
            ':date'=>$d['date'],
            ':contact_id'=>$d['contact_id'] ?: null,
            ':project_id'=>$d['project_id'] ?: null,
            ':category_id'=>$d['category_id'] ?: null,
            ':description'=>$d['description'] ?: null,
            ':amount_tzs'=>$d['amount_tzs'],
            ':reference'=>$d['reference'] ?: null,
            ':receipt_path'=>$d['receipt_path'] ?? null,
            ':notes'=>$d['notes'] ?: null,
        ];
    }

    public static function setReceipt(int $id, ?string $path): void
    {
        $stmt = Database::pdo()->prepare('UPDATE expenses SET receipt_path=:p WHERE id=:id');
        $stmt->execute([':p'=>$path, ':id'=>$id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM expenses WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT e.*, c.name AS contact_name, p.name AS project_name, cat.name AS category_name
                FROM expenses e
                LEFT JOIN contacts c   ON c.id = e.contact_id
                LEFT JOIN projects p   ON p.id = e.project_id
                LEFT JOIN categories cat ON cat.id = e.category_id
                ' . $where . ' ORDER BY e.date DESC, e.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function totalTzs(array $filters = []): float
    {
        [$where, $params] = self::whereClause($filters);
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount_tzs),0) FROM expenses e ' . $where);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /** @return array{0:string,1:array} */
    private static function whereClause(array $f): array
    {
        $cond = []; $params = [];
        if (!empty($f['date_from'])) { $cond[] = 'e.date >= :date_from'; $params[':date_from'] = $f['date_from']; }
        if (!empty($f['date_to']))   { $cond[] = 'e.date <= :date_to';   $params[':date_to']   = $f['date_to']; }
        if (!empty($f['project_id']))  { $cond[] = 'e.project_id = :project_id';   $params[':project_id']  = (int)$f['project_id']; }
        if (!empty($f['category_id'])) { $cond[] = 'e.category_id = :category_id'; $params[':category_id'] = (int)$f['category_id']; }
        return [$cond ? 'WHERE ' . implode(' AND ', $cond) : '', $params];
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM expenses WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ExpenseTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/Expense.php tests/ExpenseTest.php
git commit -m "feat: Expense model with joined names, filters, totals"
```

---

### Task 4: Expenses CRUD + filters

**Files:**
- Create: `src/Controllers/ExpenseController.php`
- Create: `views/expenses/index.php`, `views/expenses/form.php`
- Modify: `public/index.php`

**Interfaces:**
- Consumes: `App\Models\Expense`, `Contact`, `Project`, `Category`, `Auth`, `render()`, shared `views/_filters.php`.
- Produces: `ExpenseController::index|create|store|edit|update|delete`. Vendor dropdown uses `Contact::all('vendor')`; category dropdown uses `Category::all('expense')`. Amount is TZS only.

- [ ] **Step 1: Write `src/Controllers/ExpenseController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Expense;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Category;

final class ExpenseController
{
    private function filters(): array
    {
        return [
            'date_from'=>$_GET['date_from'] ?? '', 'date_to'=>$_GET['date_to'] ?? '',
            'project_id'=>$_GET['project_id'] ?? '', 'category_id'=>$_GET['category_id'] ?? '',
        ];
    }

    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $f = $this->filters();
        return render('expenses/index', [
            'rows'=>Expense::all($f), 'total'=>Expense::totalTzs($f), 'f'=>$f,
            'projects'=>Project::all(), 'categories'=>Category::all('expense'),
        ], 'Expenses');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('expenses/form', $this->formData(null, null), 'New expense');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('expenses/form', $this->formData($_POST, $error), 'New expense'); }
        $d = $this->fields($_POST);
        $d['created_by'] = Auth::user()['id'] ?? null;
        Expense::create($d);
        header('Location: /expenses'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $row = Expense::find($id);
        if (!$row) { http_response_code(404); return 'Not found'; }
        return render('expenses/form', $this->formData($row, null), 'Edit expense');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Expense::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('expenses/form', $this->formData(array_merge($_POST,['id'=>$id]), $error), 'Edit expense'); }
        Expense::update($id, $this->fields($_POST));
        header('Location: /expenses'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Expense::delete($id);
        header('Location: /expenses'); exit;
    }

    private function formData(?array $row, ?string $error): array
    {
        return [
            'r'=>$row, 'error'=>$error,
            'contacts'=>Contact::all('vendor'),
            'projects'=>Project::all(),
            'categories'=>Category::all('expense'),
        ];
    }

    private function fields(array $in): array
    {
        return [
            'date'=>$in['date'] ?? date('Y-m-d'),
            'contact_id'=>$in['contact_id'] ?? null,
            'project_id'=>$in['project_id'] ?? null,
            'category_id'=>$in['category_id'] ?? null,
            'description'=>trim($in['description'] ?? ''),
            'amount_tzs'=>(float)($in['amount_tzs'] ?? 0),
            'reference'=>trim($in['reference'] ?? ''),
            'notes'=>trim($in['notes'] ?? ''),
        ];
    }

    private function validate(array $in): ?string
    {
        if (empty($in['date']) || !\DateTime::createFromFormat('Y-m-d', $in['date'])) return 'A valid date is required.';
        if (!is_numeric($in['amount_tzs'] ?? null) || (float)$in['amount_tzs'] <= 0) return 'Amount must be greater than zero.';
        return null;
    }
}
```

- [ ] **Step 2: Write `views/expenses/index.php`**

```php
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Expenses</h1>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary" href="/expenses/new">New expense</a>
  <?php endif; ?>
</div>
<?php $action = '/expenses'; include dirname(__DIR__) . '/_filters.php'; ?>
<p><strong>Total (TZS): <?= number_format($total, 2) ?></strong></p>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th>Project</th><th>Description</th><th>Amount (TZS)</th><th>Receipt</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e($row['date']) ?></td>
      <td><?= e($row['contact_name']) ?></td>
      <td><?= e($row['category_name']) ?></td>
      <td><?= e($row['project_name']) ?></td>
      <td><?= e($row['description']) ?></td>
      <td><?= number_format((float)$row['amount_tzs'], 2) ?></td>
      <td><?php if (!empty($row['receipt_path'])): ?><a href="/expenses/<?= (int)$row['id'] ?>/receipt">View</a><?php endif; ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/expenses/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/expenses/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this entry?">
            <button type="submit" class="btn-link-danger">Delete</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
```

- [ ] **Step 3: Write `views/expenses/form.php`**

```php
<?php $isNew = empty($r['id']); ?>
<h1><?= $isNew ? 'New expense' : 'Edit expense' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= $isNew ? '/expenses' : '/expenses/' . (int)$r['id'] ?>">
  <label>Date <input type="date" name="date" value="<?= e($r['date'] ?? date('Y-m-d')) ?>" required></label>
  <label>Vendor
    <select name="contact_id">
      <option value="">—</option>
      <?php foreach ($contacts as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)($r['contact_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Category
    <select name="category_id">
      <option value="">—</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= ((int)($r['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Project
    <select name="project_id">
      <option value="">—</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)($r['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Description <input name="description" value="<?= e($r['description'] ?? '') ?>"></label>
  <label>Amount (TZS) <input type="number" step="0.01" name="amount_tzs" value="<?= e($r['amount_tzs'] ?? '') ?>" required></label>
  <label>Reference <input name="reference" value="<?= e($r['reference'] ?? '') ?>"></label>
  <label>Notes <textarea name="notes"><?= e($r['notes'] ?? '') ?></textarea></label>
  <label>Receipt (PDF/JPG/PNG) <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"></label>
  <?php if (!empty($r['receipt_path'])): ?><p>Current receipt: <a href="/expenses/<?= (int)$r['id'] ?>/receipt">View</a></p><?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/expenses" class="btn">Cancel</a>
</form>
```

- [ ] **Step 4: Add routes in `public/index.php`** (`use App\Controllers\ExpenseController;`)

```php
$router->add('GET',  '/expenses',            fn() => (new ExpenseController())->index());
$router->add('GET',  '/expenses/new',        fn() => (new ExpenseController())->create());
$router->add('POST', '/expenses',            fn() => (new ExpenseController())->store());
$router->add('GET',  '/expenses/:id/edit',   fn($p) => (new ExpenseController())->edit((int)$p['id']));
$router->add('POST', '/expenses/:id',        fn($p) => (new ExpenseController())->update((int)$p['id']));
$router->add('POST', '/expenses/:id/delete', fn($p) => (new ExpenseController())->delete((int)$p['id']));
```

- [ ] **Step 5: Verify (lint + e2e)**

```bash
php -l src/Controllers/ExpenseController.php && php -l views/expenses/index.php && php -l views/expenses/form.php && php -l public/index.php
```
Log in as admin: create an expense (amount 450000) → listed, Total 450,000.00; amount ≤ 0 rejected; date filter works. Viewer: GET 200 read-only, POST 403.

- [ ] **Step 6: Commit**

```bash
git add src/Controllers/ExpenseController.php views/expenses/ public/index.php
git commit -m "feat: Expenses CRUD (TZS) with filters"
```

---

### Task 5: Receipt upload + secure download

**Files:**
- Create: `src/ReceiptStorage.php`
- Test: `tests/ReceiptStorageTest.php`
- Create: `storage/receipts/.gitkeep`
- Modify: `.gitignore`
- Modify: `src/Controllers/IncomeController.php`, `src/Controllers/ExpenseController.php` (handle upload on store/update)
- Modify: `public/index.php` (add receipt download routes)

**Interfaces:**
- Produces:
  - `App\ReceiptStorage::DIR` — absolute path to `storage/receipts/`.
  - `App\ReceiptStorage::validate(array $file): ?string` — returns an error string or null. Accepts a PHP `$_FILES` entry; enforces: upload ok, size ≤ 10 MB, extension in `pdf,jpg,jpeg,png`.
  - `App\ReceiptStorage::extension(string $filename): string` — lowercased extension.
  - `App\ReceiptStorage::store(array $file, string $prefix, int $id): string` — moves the upload to `DIR/{prefix}_{id}_{uniqid}.{ext}` and returns the stored basename.
  - `App\ReceiptStorage::path(string $basename): string` — absolute path for a stored basename (basename-sanitised).
- Controllers: after `create`/`update`, if a receipt file was uploaded and valid, store it and call `Income::setReceipt`/`Expense::setReceipt`. A new download method on each controller streams the file after `Auth::requireRole('admin','editor','viewer')`.

- [ ] **Step 1: Write the failing test** `tests/ReceiptStorageTest.php`

```php
<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\ReceiptStorage;

final class ReceiptStorageTest extends TestCase
{
    public function test_extension_lowercased(): void
    {
        $this->assertSame('pdf', ReceiptStorage::extension('Invoice.PDF'));
        $this->assertSame('jpg', ReceiptStorage::extension('a.b.JPG'));
    }

    public function test_validate_accepts_pdf(): void
    {
        $file = ['name'=>'r.pdf','type'=>'application/pdf','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024];
        $this->assertNull(ReceiptStorage::validate($file));
    }

    public function test_validate_rejects_bad_extension(): void
    {
        $file = ['name'=>'r.exe','type'=>'application/octet-stream','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024];
        $this->assertNotNull(ReceiptStorage::validate($file));
    }

    public function test_validate_rejects_too_large(): void
    {
        $file = ['name'=>'r.pdf','type'=>'application/pdf','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>11*1024*1024];
        $this->assertNotNull(ReceiptStorage::validate($file));
    }

    public function test_validate_rejects_upload_error(): void
    {
        $file = ['name'=>'r.pdf','type'=>'application/pdf','tmp_name'=>'','error'=>UPLOAD_ERR_NO_FILE,'size'=>0];
        $this->assertNotNull(ReceiptStorage::validate($file));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ReceiptStorageTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `src/ReceiptStorage.php`**

```php
<?php
namespace App;

final class ReceiptStorage
{
    public const DIR = __DIR__ . '/../storage/receipts';
    private const ALLOWED = ['pdf','jpg','jpeg','png'];
    private const MAX_BYTES = 10 * 1024 * 1024;

    public static function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function validate(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Receipt upload failed.';
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return 'Receipt must be 10 MB or smaller.';
        }
        if (!in_array(self::extension($file['name'] ?? ''), self::ALLOWED, true)) {
            return 'Receipt must be a PDF, JPG, or PNG.';
        }
        return null;
    }

    public static function store(array $file, string $prefix, int $id): string
    {
        if (!is_dir(self::DIR)) { mkdir(self::DIR, 0775, true); }
        $ext = self::extension($file['name']);
        $basename = sprintf('%s_%d_%s.%s', $prefix, $id, bin2hex(random_bytes(6)), $ext);
        $dest = self::DIR . '/' . $basename;
        // move_uploaded_file in web context; fall back to rename for tests/CLI.
        if (is_uploaded_file($file['tmp_name'])) {
            move_uploaded_file($file['tmp_name'], $dest);
        } else {
            rename($file['tmp_name'], $dest);
        }
        return $basename;
    }

    public static function path(string $basename): string
    {
        return self::DIR . '/' . basename($basename);
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ReceiptStorageTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Create `storage/receipts/.gitkeep` and ignore uploads**

Append to `.gitignore`:
```
storage/receipts/*
!storage/receipts/.gitkeep
```
Create the empty file `storage/receipts/.gitkeep`.

- [ ] **Step 6: Handle uploads in `IncomeController`**

In `store()`, after `$id = Income::create($d);` and before the redirect, add:
```php
        $this->maybeStoreReceipt($id);
```
In `update()`, after `Income::update($id, $this->fields($_POST));` and before the redirect, add the same line.
Add this private method to the class:
```php
    private function maybeStoreReceipt(int $id): void
    {
        if (empty($_FILES['receipt']['name'])) { return; }
        if (\App\ReceiptStorage::validate($_FILES['receipt']) !== null) { return; }
        $name = \App\ReceiptStorage::store($_FILES['receipt'], 'income', $id);
        \App\Models\Income::setReceipt($id, $name);
    }

    public function receipt(int $id): never
    {
        \App\Auth::requireRole('admin','editor','viewer');
        $row = \App\Models\Income::find($id);
        if (!$row || empty($row['receipt_path'])) { http_response_code(404); echo 'Not found'; exit; }
        $path = \App\ReceiptStorage::path($row['receipt_path']);
        if (!is_file($path)) { http_response_code(404); echo 'Not found'; exit; }
        header('Content-Type: ' . (\App\ReceiptStorage::extension($path) === 'pdf' ? 'application/pdf' : 'image/' . \App\ReceiptStorage::extension($path)));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path); exit;
    }
```

- [ ] **Step 7: Handle uploads in `ExpenseController`** (mirror of Step 6)

In `store()` after create and `update()` after update, add `$this->maybeStoreReceipt($id);` before each redirect. Add:
```php
    private function maybeStoreReceipt(int $id): void
    {
        if (empty($_FILES['receipt']['name'])) { return; }
        if (\App\ReceiptStorage::validate($_FILES['receipt']) !== null) { return; }
        $name = \App\ReceiptStorage::store($_FILES['receipt'], 'expense', $id);
        \App\Models\Expense::setReceipt($id, $name);
    }

    public function receipt(int $id): never
    {
        \App\Auth::requireRole('admin','editor','viewer');
        $row = \App\Models\Expense::find($id);
        if (!$row || empty($row['receipt_path'])) { http_response_code(404); echo 'Not found'; exit; }
        $path = \App\ReceiptStorage::path($row['receipt_path']);
        if (!is_file($path)) { http_response_code(404); echo 'Not found'; exit; }
        header('Content-Type: ' . (\App\ReceiptStorage::extension($path) === 'pdf' ? 'application/pdf' : 'image/' . \App\ReceiptStorage::extension($path)));
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path); exit;
    }
```

- [ ] **Step 8: Add download routes in `public/index.php`** (before `try {`)

```php
$router->add('GET', '/income/:id/receipt',   fn($p) => (new IncomeController())->receipt((int)$p['id']));
$router->add('GET', '/expenses/:id/receipt', fn($p) => (new ExpenseController())->receipt((int)$p['id']));
```

- [ ] **Step 9: Verify (lint + e2e upload)**

```bash
php -l src/ReceiptStorage.php && php -l src/Controllers/IncomeController.php && php -l src/Controllers/ExpenseController.php && php -l public/index.php
```
Start the dev server, log in as admin, create an expense with a small test PDF attached (curl `-F`), then:
- The list shows a "View" link; `GET /expenses/:id/receipt` returns HTTP 200 with `Content-Type: application/pdf`.
- A logged-out request to the receipt URL redirects to `/login` (302).
- Confirm the file lives under `storage/receipts/` and **not** under `public/`.

- [ ] **Step 10: Commit**

```bash
git add src/ReceiptStorage.php tests/ReceiptStorageTest.php storage/receipts/.gitkeep .gitignore \
        src/Controllers/IncomeController.php src/Controllers/ExpenseController.php public/index.php
git commit -m "feat: secure receipt uploads and authenticated download"
```

---

## Self-Review

**Spec coverage (Plan 3 scope):**
- Income recording with TZS/USD + snapshot rate → Tasks 1, 2. ✓
- Expenses recording (TZS only) → Tasks 3, 4. ✓
- Optional contact/project/category links on each entry → forms in Tasks 2, 4. ✓
- Date/project/category filtering with totals → `whereClause`/`totalTzs` (Tasks 1, 3), shared `_filters.php` (Task 2). ✓
- Receipt upload (PDF/JPG/PNG, ≤10 MB) stored outside `public/`, authenticated download for all roles → Task 5. ✓
- Role matrix (view-all, edit-staff) → guards in Tasks 2, 4, 5. ✓
- Mobile: lists wrapped in `table-wrap`; themed classes only. ✓
- Deferred to Plan 4: dashboard KPIs, Excel export, settings, activity-log UI.

**Placeholder scan:** None. Upload validation, currency computation, and streaming are all concrete.

**Type consistency:** `Income`/`Expense` share method names (`create/all/find/update/delete/setReceipt/totalTzs`); `Income` adds `tzsValue`. Controllers pass `created_by` only on create; `update()` ignores `receipt_path` (receipts go through `setReceipt`). `ReceiptStorage::validate/extension/store/path/DIR` are used consistently in both controllers. Routes reference controller methods that exist (`receipt` added in Task 5).
