# LIPA Web — Plan 4: Dashboard, Excel Export, Settings, Activity Log — Implementation Plan

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Complete the app: a dashboard with KPIs and per-project summary, a multi-sheet Excel export for the accountant, an admin Settings page (org info + base currency + logo), and an activity-log audit trail populated by writes across the app.

**Architecture:** Same lean plain-PHP stack. Add aggregation helpers to `Income`/`Expense`; add `Setting` and `Activity` models; wire `Activity::log()` into existing controllers; build `DashboardController`, `ReportController` (PhpSpreadsheet export), `SettingController`, `ActivityController`. Add Composer dep `phpoffice/phpspreadsheet`.

**Tech Stack:** PHP 8.3, MariaDB/MySQL (PDO), PHPUnit, `phpoffice/phpspreadsheet`, vanilla PHP views.

## Global Constraints

- PHP **8.3**; production **MariaDB**, local **MySQL 8.4** — **portable SQL only**; **PDO prepared statements**.
- Money `DECIMAL(15,2)`; base currency **TZS**; amounts displayed with thousands separators + 2 decimals.
- UI language **English (UK)**; **mobile-first responsive** (existing `app.css`, tables in `table-wrap`).
- Roles enforced **server-side**:
  - **Dashboard** + **Reports/Export** — `admin`, `editor`, `viewer`.
  - **Settings** — `admin`.
  - **Activity log** — `admin`, `viewer`.
- Excel export: one `.xlsx` workbook, sheets **Overview, Income, Expenses, Income by category, Expenses by category, By project**, honouring the date range.
- Composer additions limited to `phpoffice/phpspreadsheet`.
- Tests run against `lipa_test` via `Tests\DatabaseTestCase`.

Toolchain note (local): prefix shell commands with
`export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`.

---

## File structure (created across this plan)

```
src/Models/Setting.php   Activity.php
src/Controllers/DashboardController.php (replace placeholder)  ReportController.php
                SettingController.php   ActivityController.php
src/Reports/ExcelExport.php
views/dashboard.php (replace)   views/reports/index.php
views/settings/index.php        views/activity/index.php
tests/SettingTest.php  ActivityTest.php  AggregateTest.php
public/index.php (routes)        composer.json (phpspreadsheet)
```

---

### Task 1: Aggregation helpers on Income & Expense (TDD)

**Files:**
- Modify: `src/Models/Income.php`, `src/Models/Expense.php`
- Test: `tests/AggregateTest.php`

**Interfaces:**
- Produces (on both `Income` and `Expense`):
  - `byCategory(array $filters = []): array` — rows `['name'=>string|null,'total'=>float]` grouped by category, ordered by total DESC. NULL category shown as name `null`.
  - `byProject(array $filters = []): array` — rows `['id'=>?int,'name'=>string|null,'total'=>float]` grouped by project.

- [ ] **Step 1: Write the failing test** `tests/AggregateTest.php`

```php
<?php
namespace Tests;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Category;
use App\Models\Project;

final class AggregateTest extends DatabaseTestCase
{
    public function test_income_by_category_and_project(): void
    {
        $grants = Category::create(['type'=>'income','name'=>'Grants','sort_order'=>1]);
        $don = Category::create(['type'=>'income','name'=>'Donations','sort_order'=>2]);
        $pa = Project::create(['name'=>'A','code'=>'','description'=>'']);
        $base = ['contact_id'=>null,'description'=>'','currency'=>'TZS','exchange_rate'=>1,'reference'=>'','notes'=>'','created_by'=>null,'date'=>'2026-03-01'];
        Income::create($base + ['category_id'=>$grants,'project_id'=>$pa,'amount_original'=>1000,'amount_tzs'=>1000]);
        Income::create($base + ['category_id'=>$grants,'project_id'=>null,'amount_original'=>500,'amount_tzs'=>500]);
        Income::create($base + ['category_id'=>$don,'project_id'=>$pa,'amount_original'=>200,'amount_tzs'=>200]);
        $byCat = Income::byCategory();
        $this->assertSame('Grants', $byCat[0]['name']);
        $this->assertEqualsWithDelta(1500.0, (float)$byCat[0]['total'], 0.001);
        $byProj = Income::byProject();
        $totals = [];
        foreach ($byProj as $r) { $totals[$r['name'] ?? '—'] = (float)$r['total']; }
        $this->assertEqualsWithDelta(1200.0, $totals['A'], 0.001);
        $this->assertEqualsWithDelta(500.0, $totals['—'], 0.001);
    }

    public function test_expense_by_category_respects_filter(): void
    {
        $rent = Category::create(['type'=>'expense','name'=>'Rent','sort_order'=>1]);
        $base = ['contact_id'=>null,'project_id'=>null,'description'=>'','reference'=>'','notes'=>'','created_by'=>null,'category_id'=>$rent];
        Expense::create($base + ['date'=>'2026-01-10','amount_tzs'=>100]);
        Expense::create($base + ['date'=>'2026-03-10','amount_tzs'=>300]);
        $byCat = Expense::byCategory(['date_from'=>'2026-02-01']);
        $this->assertCount(1, $byCat);
        $this->assertEqualsWithDelta(300.0, (float)$byCat[0]['total'], 0.001);
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/AggregateTest.php`
Expected: FAIL — method `byCategory` not found.

- [ ] **Step 3: Add methods to `src/Models/Income.php`** (before the final `delete()` method)

```php
    public static function byCategory(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT cat.name AS name, COALESCE(SUM(i.amount_tzs),0) AS total
                FROM income i LEFT JOIN categories cat ON cat.id = i.category_id
                ' . $where . ' GROUP BY i.category_id, cat.name ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byProject(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT p.id AS id, p.name AS name, COALESCE(SUM(i.amount_tzs),0) AS total
                FROM income i LEFT JOIN projects p ON p.id = i.project_id
                ' . $where . ' GROUP BY i.project_id, p.id, p.name ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
```

- [ ] **Step 4: Add the same two methods to `src/Models/Expense.php`** (use alias `e` to match its other queries)

```php
    public static function byCategory(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT cat.name AS name, COALESCE(SUM(e.amount_tzs),0) AS total
                FROM expenses e LEFT JOIN categories cat ON cat.id = e.category_id
                ' . $where . ' GROUP BY e.category_id, cat.name ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byProject(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT p.id AS id, p.name AS name, COALESCE(SUM(e.amount_tzs),0) AS total
                FROM expenses e LEFT JOIN projects p ON p.id = e.project_id
                ' . $where . ' GROUP BY e.project_id, p.id, p.name ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
```

- [ ] **Step 5: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/AggregateTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Models/Income.php src/Models/Expense.php tests/AggregateTest.php
git commit -m "feat: category/project aggregation helpers for income and expenses"
```

---

### Task 2: Activity model + logging wiring (TDD)

**Files:**
- Create: `src/Models/Activity.php`
- Test: `tests/ActivityTest.php`
- Modify: controllers (`User`, `Contact`, `Project`, `Category`, `Income`, `Expense`, `Auth`) to log writes.

**Interfaces:**
- Produces:
  - `App\Models\Activity::log(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void` — inserts a row; auto-prunes to the newest 1000.
  - `App\Models\Activity::recent(int $limit = 20): array` — newest first, with `user_name` LEFT JOINed.

- [ ] **Step 1: Write the failing test** `tests/ActivityTest.php`

```php
<?php
namespace Tests;
use App\Models\Activity;
use App\Models\User;

final class ActivityTest extends DatabaseTestCase
{
    public function test_log_and_recent_with_user_name(): void
    {
        $uid = User::create(['name'=>'Ada','email'=>'ada@x.org','password'=>'pw12345','role'=>'admin']);
        Activity::log($uid, 'create', 'income', 5, 'Created income #5');
        Activity::log($uid, 'delete', 'expense', 9, 'Deleted expense #9');
        $recent = Activity::recent(10);
        $this->assertCount(2, $recent);
        $this->assertSame('delete', $recent[0]['action']); // newest first
        $this->assertSame('Ada', $recent[0]['user_name']);
    }

    public function test_recent_respects_limit(): void
    {
        for ($i = 0; $i < 5; $i++) { Activity::log(null, 'create', 'contact', $i, "c{$i}"); }
        $this->assertCount(3, Activity::recent(3));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ActivityTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `src/Models/Activity.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class Activity
{
    public static function log(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, description)
             VALUES (:uid, :action, :etype, :eid, :descr)'
        );
        $stmt->execute([
            ':uid'=>$userId, ':action'=>$action, ':etype'=>$entityType,
            ':eid'=>$entityId, ':descr'=>$description,
        ]);
        // Prune to newest 1000.
        $pdo->exec('DELETE FROM activity_log WHERE id <= (
            SELECT id FROM (
                SELECT id FROM activity_log ORDER BY id DESC LIMIT 1 OFFSET 1000
            ) t)');
    }

    public static function recent(int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, u.name AS user_name
             FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ActivityTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Wire logging into write actions**

In each controller below, add a logging call right after the successful model write (before the redirect). Use the current user id from `Auth::user()['id'] ?? null`. Add `use App\Models\Activity;` to each file.

- `UserController::store` → `Activity::log(Auth::user()['id'] ?? null, 'create', 'user', null, 'Created user ' . trim($_POST['email']));`
- `UserController::update` → `Activity::log(Auth::user()['id'] ?? null, 'update', 'user', $id, 'Updated user');`
- `UserController::delete` (only when actually deleted) → `Activity::log(Auth::user()['id'] ?? null, 'delete', 'user', $id, 'Deleted user');`
- `ContactController::store/update/delete` → action `create/update/delete`, entity `contact`.
- `ProjectController::store/update/delete` → entity `project`.
- `CategoryController::store/update/delete` → entity `category`.
- `IncomeController::store/update/delete` → entity `income` (store/update use `$id` from create / route id).
- `ExpenseController::store/update/delete` → entity `expense`.
- `Auth::attempt` (on success, before `return true;`) → `Activity::log((int)$user['id'], 'login', 'user', (int)$user['id'], 'Logged in');` (add `use App\Models\Activity;` to `src/Auth.php`).

For `store()` methods that currently `header('Location: ...'); exit;` immediately after create, insert the `Activity::log(...)` call before the redirect, using the new row id where available (income/expense have `$id`; contact/project/category create returns an id — capture it: change `Model::create($this->fields($_POST));` to `$newId = Model::create($this->fields($_POST));` and log with `$newId`).

- [ ] **Step 6: Verify logging (e2e)**

Lint all modified controllers (`php -l`), start the dev server, log in (a `login` activity should be recorded), create a contact, then check:
```bash
mysql --host=127.0.0.1 --protocol=tcp -uroot lipa -e "SELECT action, entity_type, description FROM activity_log ORDER BY id DESC LIMIT 5;"
```
Expected: rows for the login and the contact creation.

- [ ] **Step 7: Commit**

```bash
git add src/Models/Activity.php tests/ActivityTest.php src/Auth.php src/Controllers/
git commit -m "feat: activity log model and write-action logging across controllers"
```

---

### Task 3: Setting model + Settings page (TDD)

**Files:**
- Create: `src/Models/Setting.php`
- Create: `src/Controllers/SettingController.php`
- Create: `views/settings/index.php`
- Test: `tests/SettingTest.php`
- Modify: `public/index.php`

**Interfaces:**
- Produces:
  - `App\Models\Setting::get(string $key, ?string $default = null): ?string`
  - `App\Models\Setting::set(string $key, ?string $value): void` — upsert.
  - `App\Models\Setting::all(): array` — `['key'=>'value', ...]`.
- `SettingController::index|save` (admin). Saves `org_name, org_address, org_email, base_currency`; optional logo upload to `public/uploads/` stored as setting `logo`.

- [ ] **Step 1: Write the failing test** `tests/SettingTest.php`

```php
<?php
namespace Tests;
use App\Models\Setting;

final class SettingTest extends DatabaseTestCase
{
    public function test_set_get_upsert(): void
    {
        $this->assertNull(Setting::get('org_name'));
        $this->assertSame('fallback', Setting::get('org_name', 'fallback'));
        Setting::set('org_name', 'Pepea');
        $this->assertSame('Pepea', Setting::get('org_name'));
        Setting::set('org_name', 'Pepea Africa');
        $this->assertSame('Pepea Africa', Setting::get('org_name'));
    }

    public function test_all_returns_map(): void
    {
        Setting::set('a', '1');
        Setting::set('b', '2');
        $all = Setting::all();
        $this->assertSame('1', $all['a']);
        $this->assertSame('2', $all['b']);
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/SettingTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `src/Models/Setting.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class Setting
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM settings WHERE setting_key = :k');
        $stmt->execute([':k'=>$key]);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : $val;
    }

    public static function set(string $key, ?string $value): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2'
        );
        $stmt->execute([':k'=>$key, ':v'=>$value, ':v2'=>$value]);
    }

    public static function all(): array
    {
        $rows = Database::pdo()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[$r['setting_key']] = $r['setting_value']; }
        return $out;
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/SettingTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Write `src/Controllers/SettingController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Setting;
use App\Models\Activity;

final class SettingController
{
    private const KEYS = ['org_name','org_address','org_email','base_currency'];

    public function index(): string
    {
        Auth::requireRole('admin');
        return render('settings/index', ['s'=>Setting::all(), 'saved'=>isset($_GET['saved'])], 'Settings');
    }

    public function save(): string
    {
        Auth::requireRole('admin');
        foreach (self::KEYS as $k) {
            Setting::set($k, trim($_POST[$k] ?? ''));
        }
        if (!empty($_FILES['logo']['name']) && ($_FILES['logo']['error'] ?? 1) === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','svg'], true)) {
                $dir = dirname(__DIR__, 2) . '/public/uploads';
                if (!is_dir($dir)) { mkdir($dir, 0775, true); }
                $name = 'logo.' . $ext;
                $tmp = $_FILES['logo']['tmp_name'];
                if (is_uploaded_file($tmp)) { move_uploaded_file($tmp, "$dir/$name"); } else { rename($tmp, "$dir/$name"); }
                Setting::set('logo', $name);
            }
        }
        Activity::log(Auth::user()['id'] ?? null, 'update', 'settings', null, 'Updated settings');
        header('Location: /settings?saved=1'); exit;
    }
}
```

- [ ] **Step 6: Write `views/settings/index.php`**

```php
<h1>Settings</h1>
<?php if (!empty($saved)): ?><div class="alert" style="background:var(--accent-subtle);padding:10px 12px;border-radius:8px;margin:12px 0">Settings saved.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="/settings">
  <label>Organisation name <input name="org_name" value="<?= e($s['org_name'] ?? '') ?>"></label>
  <label>Address <textarea name="org_address"><?= e($s['org_address'] ?? '') ?></textarea></label>
  <label>Email <input type="email" name="org_email" value="<?= e($s['org_email'] ?? '') ?>"></label>
  <label>Base currency
    <select name="base_currency">
      <?php foreach (['TZS','USD','EUR'] as $cur): ?>
        <option value="<?= $cur ?>" <?= (($s['base_currency'] ?? 'TZS') === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Logo (PNG/JPG/SVG) <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg"></label>
  <?php if (!empty($s['logo'])): ?><p>Current logo: <img src="/uploads/<?= e($s['logo']) ?>" alt="logo" style="max-height:48px;vertical-align:middle"></p><?php endif; ?>
  <button type="submit" class="btn btn-primary">Save settings</button>
</form>
```

- [ ] **Step 7: Add routes in `public/index.php`** (`use App\Controllers\SettingController;`)

```php
$router->add('GET',  '/settings', fn() => (new SettingController())->index());
$router->add('POST', '/settings', fn() => (new SettingController())->save());
```

- [ ] **Step 8: Verify (lint + e2e)**

Lint; log in as admin → `/settings` 200; POST org fields → redirect `/settings?saved=1`, "Settings saved." shown, values persist. Editor → `/settings` 403.

- [ ] **Step 9: Commit**

```bash
git add src/Models/Setting.php tests/SettingTest.php src/Controllers/SettingController.php views/settings/ public/index.php
git commit -m "feat: Setting model and admin Settings page"
```

---

### Task 4: Dashboard (KPIs + per-project summary + recent activity)

**Files:**
- Replace: `src/Controllers/DashboardController.php`
- Replace: `views/dashboard.php`

**Interfaces:**
- Consumes: `Income`, `Expense`, `Activity`, `Setting`, `Auth`. Reads optional `date_from`/`date_to` from `$_GET` (defaults: current calendar year).
- Produces: dashboard with three KPI cards (income, expenses, balance), a per-project table (income/expense/balance), and a recent-activity list.

- [ ] **Step 1: Replace `src/Controllers/DashboardController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Activity;

final class DashboardController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $f = [
            'date_from' => $_GET['date_from'] ?? (date('Y') . '-01-01'),
            'date_to'   => $_GET['date_to'] ?? (date('Y') . '-12-31'),
        ];
        $income = Income::totalTzs($f);
        $expense = Expense::totalTzs($f);

        // Merge per-project income & expense into one table keyed by project name.
        $proj = [];
        foreach (Income::byProject($f) as $r)  { $k = $r['name'] ?? '—'; $proj[$k]['income'] = (float)$r['total']; }
        foreach (Expense::byProject($f) as $r) { $k = $r['name'] ?? '—'; $proj[$k]['expense'] = (float)$r['total']; }

        return render('dashboard', [
            'f'=>$f, 'income'=>$income, 'expense'=>$expense, 'balance'=>$income - $expense,
            'projects'=>$proj, 'activity'=>Activity::recent(10),
        ], 'Dashboard');
    }
}
```

- [ ] **Step 2: Replace `views/dashboard.php`**

```php
<h1>Dashboard</h1>
<form method="get" action="/" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <button class="btn" type="submit">Apply</button>
</form>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0">
  <div class="btn" style="flex:1;min-width:180px;flex-direction:column;align-items:flex-start;cursor:default">
    <span>Income (TZS)</span><strong style="font-size:1.4rem"><?= number_format($income, 2) ?></strong>
  </div>
  <div class="btn" style="flex:1;min-width:180px;flex-direction:column;align-items:flex-start;cursor:default">
    <span>Expenses (TZS)</span><strong style="font-size:1.4rem"><?= number_format($expense, 2) ?></strong>
  </div>
  <div class="btn" style="flex:1;min-width:180px;flex-direction:column;align-items:flex-start;cursor:default">
    <span>Balance (TZS)</span><strong style="font-size:1.4rem"><?= number_format($balance, 2) ?></strong>
  </div>
</div>

<h2>By project</h2>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Project</th><th>Income (TZS)</th><th>Expenses (TZS)</th><th>Balance (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($projects as $name => $vals): $inc = $vals['income'] ?? 0; $exp = $vals['expense'] ?? 0; ?>
    <tr>
      <td><?= e($name) ?></td>
      <td><?= number_format($inc, 2) ?></td>
      <td><?= number_format($exp, 2) ?></td>
      <td><?= number_format($inc - $exp, 2) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($projects)): ?><tr><td colspan="4">No data for this period.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<h2>Recent activity</h2>
<ul>
  <?php foreach ($activity as $a): ?>
    <li><?= e($a['created_at']) ?> — <?= e($a['user_name'] ?? 'system') ?>: <?= e($a['description'] ?? ($a['action'] . ' ' . $a['entity_type'])) ?></li>
  <?php endforeach; ?>
  <?php if (empty($activity)): ?><li>No activity yet.</li><?php endif; ?>
</ul>
```

- [ ] **Step 3: Verify (e2e)**

Log in as admin; seed one income + one expense (via the UI or model); the dashboard shows correct KPI numbers and balance, a per-project row, and recent-activity entries. Change the date range to a period with no data → KPIs show 0.00 and "No data for this period."

- [ ] **Step 4: Commit**

```bash
git add src/Controllers/DashboardController.php views/dashboard.php
git commit -m "feat: dashboard with KPIs, per-project summary, recent activity"
```

---

### Task 5: Reports + Excel export (PhpSpreadsheet)

**Files:**
- Modify: `composer.json` (add `phpoffice/phpspreadsheet`)
- Create: `src/Reports/ExcelExport.php`
- Create: `src/Controllers/ReportController.php`
- Create: `views/reports/index.php`
- Modify: `public/index.php`

**Interfaces:**
- Produces:
  - `App\Reports\ExcelExport::build(array $filters): \PhpOffice\PhpSpreadsheet\Spreadsheet` — six sheets (Overview, Income, Expenses, Income by category, Expenses by category, By project).
  - `ReportController::index` (date-range form) and `ReportController::export` (streams `.xlsx`). Both require `admin,editor,viewer`.

- [ ] **Step 1: Add PhpSpreadsheet**

Run: `composer require phpoffice/phpspreadsheet`
Expected: dependency added; `vendor/` updated; `composer.lock` changed.

- [ ] **Step 2: Write `src/Reports/ExcelExport.php`**

```php
<?php
namespace App\Reports;

use App\Models\Income;
use App\Models\Expense;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class ExcelExport
{
    public static function build(array $filters): Spreadsheet
    {
        $book = new Spreadsheet();
        $income = Income::all($filters);
        $expense = Expense::all($filters);
        $incTotal = Income::totalTzs($filters);
        $expTotal = Expense::totalTzs($filters);

        // 1. Overview
        $s = $book->getActiveSheet();
        $s->setTitle('Overview');
        $s->fromArray([
            ['LIPA — Income & Expenditure'],
            ['Period', ($filters['date_from'] ?? 'all') . ' to ' . ($filters['date_to'] ?? 'all')],
            [],
            ['Total income (TZS)', $incTotal],
            ['Total expenses (TZS)', $expTotal],
            ['Balance (TZS)', $incTotal - $expTotal],
        ], null, 'A1');

        // 2. Income
        $s = $book->createSheet(); $s->setTitle('Income');
        $s->fromArray(['Date','Donor','Category','Project','Description','Currency','Amount (orig.)','Exchange rate','Amount (TZS)','Reference'], null, 'A1');
        $row = 2;
        foreach ($income as $r) {
            $s->fromArray([
                $r['date'], $r['contact_name'], $r['category_name'], $r['project_name'], $r['description'],
                $r['currency'], (float)$r['amount_original'], (float)$r['exchange_rate'], (float)$r['amount_tzs'], $r['reference'],
            ], null, 'A' . $row++);
        }

        // 3. Expenses
        $s = $book->createSheet(); $s->setTitle('Expenses');
        $s->fromArray(['Date','Vendor','Category','Project','Description','Amount (TZS)','Reference'], null, 'A1');
        $row = 2;
        foreach ($expense as $r) {
            $s->fromArray([
                $r['date'], $r['contact_name'], $r['category_name'], $r['project_name'], $r['description'],
                (float)$r['amount_tzs'], $r['reference'],
            ], null, 'A' . $row++);
        }

        // 4. Income by category
        $s = $book->createSheet(); $s->setTitle('Income by category');
        $s->fromArray(['Category','Total (TZS)'], null, 'A1');
        $row = 2;
        foreach (Income::byCategory($filters) as $r) { $s->fromArray([$r['name'] ?? '(none)', (float)$r['total']], null, 'A' . $row++); }

        // 5. Expenses by category
        $s = $book->createSheet(); $s->setTitle('Expenses by category');
        $s->fromArray(['Category','Total (TZS)'], null, 'A1');
        $row = 2;
        foreach (Expense::byCategory($filters) as $r) { $s->fromArray([$r['name'] ?? '(none)', (float)$r['total']], null, 'A' . $row++); }

        // 6. By project (income, expense, balance)
        $s = $book->createSheet(); $s->setTitle('By project');
        $s->fromArray(['Project','Income (TZS)','Expenses (TZS)','Balance (TZS)'], null, 'A1');
        $proj = [];
        foreach (Income::byProject($filters) as $r)  { $proj[$r['name'] ?? '(none)']['inc'] = (float)$r['total']; }
        foreach (Expense::byProject($filters) as $r) { $proj[$r['name'] ?? '(none)']['exp'] = (float)$r['total']; }
        $row = 2;
        foreach ($proj as $name => $v) {
            $inc = $v['inc'] ?? 0; $exp = $v['exp'] ?? 0;
            $s->fromArray([$name, $inc, $exp, $inc - $exp], null, 'A' . $row++);
        }

        $book->setActiveSheetIndex(0);
        return $book;
    }
}
```

- [ ] **Step 3: Write `src/Controllers/ReportController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Reports\ExcelExport;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ReportController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        return render('reports/index', [
            'date_from'=>$_GET['date_from'] ?? (date('Y') . '-01-01'),
            'date_to'=>$_GET['date_to'] ?? (date('Y') . '-12-31'),
        ], 'Reports');
    }

    public function export(): never
    {
        Auth::requireRole('admin','editor','viewer');
        $filters = [
            'date_from'=>$_GET['date_from'] ?? '', 'date_to'=>$_GET['date_to'] ?? '',
        ];
        $book = ExcelExport::build($filters);
        $name = 'lipa-report-' . date('Ymd-His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($book))->save('php://output');
        exit;
    }
}
```

- [ ] **Step 4: Write `views/reports/index.php`**

```php
<h1>Reports</h1>
<p>Export all income and expenses for a period to Excel (multiple sheets).</p>
<form method="get" action="/reports/export" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="btn btn-primary" type="submit">Download Excel</button>
</form>
```

- [ ] **Step 5: Add routes in `public/index.php`** (`use App\Controllers\ReportController;`)

```php
$router->add('GET', '/reports',        fn() => (new ReportController())->index());
$router->add('GET', '/reports/export', fn() => (new ReportController())->export());
```

- [ ] **Step 6: Verify (lint + e2e)**

Lint; seed an income + expense; log in; `GET /reports` 200; `GET /reports/export?date_from=2026-01-01&date_to=2026-12-31` returns HTTP 200 with `Content-Type` containing `spreadsheetml.sheet` and a non-trivial body size. Save the body and confirm with:
```bash
php -r '$z=new ZipArchive; echo $z->open("/tmp/report.xlsx")===true ? "valid xlsx\n" : "bad\n";'
```
(An `.xlsx` is a ZIP; a valid open confirms PhpSpreadsheet produced a real workbook.) Viewer can also export (200).

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock src/Reports/ src/Controllers/ReportController.php views/reports/ public/index.php
git commit -m "feat: multi-sheet Excel export and Reports page"
```

---

### Task 6: Activity log page

**Files:**
- Create: `src/Controllers/ActivityController.php`
- Create: `views/activity/index.php`
- Modify: `public/index.php`

**Interfaces:**
- Consumes: `App\Models\Activity`, `Auth`. Produces `ActivityController::index` (admin, viewer) showing the latest 100 entries.

- [ ] **Step 1: Write `src/Controllers/ActivityController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Activity;

final class ActivityController
{
    public function index(): string
    {
        Auth::requireRole('admin','viewer');
        return render('activity/index', ['rows'=>Activity::recent(100)], 'Activity log');
    }
}
```

- [ ] **Step 2: Write `views/activity/index.php`**

```php
<h1>Activity log</h1>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $a): ?>
    <tr>
      <td><?= e($a['created_at']) ?></td>
      <td><?= e($a['user_name'] ?? 'system') ?></td>
      <td><?= e($a['action']) ?></td>
      <td><?= e($a['entity_type']) ?><?= $a['entity_id'] ? ' #' . (int)$a['entity_id'] : '' ?></td>
      <td><?= e($a['description']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($rows)): ?><tr><td colspan="5">No activity yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
```

- [ ] **Step 3: Add route in `public/index.php`** (`use App\Controllers\ActivityController;`)

```php
$router->add('GET', '/activity', fn() => (new ActivityController())->index());
```

- [ ] **Step 4: Verify (lint + e2e + full suite)**

Lint; log in as admin → `/activity` 200 lists entries; editor → `/activity` 403 (nav already hides it for editor). Run `composer test` → all green.

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/ActivityController.php views/activity/ public/index.php
git commit -m "feat: activity log page"
```

---

## Self-Review

**Spec coverage (Plan 4 scope):**
- Dashboard KPIs (income/expenses/balance), per-project summary, recent activity → Task 4. ✓
- Excel export with the six named sheets, date-range → Task 5. ✓
- Settings (org info, base currency, logo) admin-only → Task 3. ✓
- Activity log audit trail (writes logged) + viewer page → Tasks 2, 6. ✓
- Role matrix matches `_shell.php` nav (dashboard/reports all; settings admin; activity admin+viewer) → guards in each controller. ✓
- Mobile: tables in `table-wrap`; themed classes. ✓
- Composer dep limited to PhpSpreadsheet. ✓

**Placeholder scan:** None. Aggregations, export sheets, settings save, and logging are all concrete.

**Type consistency:** `Income`/`Expense` gain matching `byCategory`/`byProject`; `whereClause` reused (already private in each). `Activity::log/recent`, `Setting::get/set/all` used consistently. `ExcelExport::build` consumes the same filter array shape (`date_from`,`date_to`,…) as the models. Routes reference controller methods that exist. Dashboard merges per-project income/expense by project name (NULL → '—'); export uses '(none)'.
