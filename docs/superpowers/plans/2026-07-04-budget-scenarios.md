# Budget Scenarios (Feature A) — Implementation Plan

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** A planning tool where a scenario models a production **activity** with a **list of products** (soap = 1, pottery = many): shared start-up/funding/fixed costs + per-product price/cost/volume → live low/mid/high profit, break-even, and "what the profit pays for", with a donor-ready print. Pure **planning layer** — never touches the cashbook.

**Architecture:** `ScenarioCalc` (pure PHP) is the single source of truth for all figures; a small JS mirror gives a live preview on the combined edit+results page. Four new tables (`budget_scenarios` + `budget_products`/`budget_items`/`budget_allocations` children). Mirrors existing patterns: static-method models, lazy-closure routes, `render()`, `Auth` guards, `Activity::log`, the standalone print pattern.

**Tech Stack:** PHP 8.3, PDO, PHPUnit, vanilla PHP views + a scoped `budget.js`. No new deps.

## Global Constraints
- **Firewall:** scenarios/products/budgets **never** write to or read as `income`/`expenses`/`transfers`/`accounts`, and never appear in balances, statements, or the Excel export. Own tables, own screens. A test asserts no cross-writes; screen + print carry a "planning only" disclaimer.
- Money **stored** `DECIMAL(15,2)`; **displayed** as whole numbers `number_format($v, 0)`. Volumes are integers. TZS only.
- **Roles:** view = `admin,editor,viewer`; create/edit/delete = `admin,editor`; viewer read-only. Same pattern as Activities.
- Portable SQL (MySQL 8.4 local / MariaDB prod), prepared statements. Idempotent migration in `bin/migrate.php`. Every write → `Activity::log(...)`. CSRF auto via `render()`.
- Toolchain: prefix shell with `export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`. Ensure local MySQL is running (DBs `lipa`, `lipa_test`).

## File structure
```
db/schema.sql                          + 4 budget tables
bin/migrate.php                        + idempotent CREATE for the 4 tables
tests/DatabaseTestCase.php             + truncate the 4 tables
src/Budget/ScenarioCalc.php            NEW — pure compute()
src/Models/BudgetScenario.php          NEW — CRUD + children (products/items/allocations)
src/Controllers/BudgetController.php   NEW — index/create/store/show/update/delete/print
public/index.php                       + Budget routes + use
views/_shell.php                       + "Budget" nav under Reports (same group)
views/budget/index.php                 NEW — scenario list (ledger)
views/budget/form.php                  NEW — combined edit + live results
views/budget/print.php                 NEW — standalone donor print
public/assets/js/budget.js             NEW — dynamic rows + live-preview mirror of ScenarioCalc
views/_shell.php                       + <script> for budget.js on budget pages (or load globally, guarded)
tests/ScenarioCalcTest.php  BudgetScenarioTest.php  BudgetFirewallTest.php   NEW
```

---

### Task 1: Schema + migration

**Files:** Modify `db/schema.sql`, `bin/migrate.php`, `tests/DatabaseTestCase.php`.

**Interfaces produced:** tables `budget_scenarios`, `budget_products`, `budget_items`, `budget_allocations`.

- [ ] **Step 1: Append the tables to `db/schema.sql`** (after the `activity_photos` block, before `settings` — projects/users already exist above):
```sql
CREATE TABLE IF NOT EXISTS budget_scenarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  description TEXT NULL,
  project_id INT NULL,
  status ENUM('draft','active','archived') NOT NULL DEFAULT 'draft',
  funded_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bscen_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_bscen_user    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS budget_products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scenario_id INT NOT NULL,
  name VARCHAR(190) NOT NULL,
  unit_name VARCHAR(20) NOT NULL DEFAULT 'unit',
  sale_price DECIMAL(15,2) NOT NULL DEFAULT 0,
  unit_cost DECIMAL(15,2) NOT NULL DEFAULT 0,
  units_low INT NOT NULL DEFAULT 0,
  units_mid INT NOT NULL DEFAULT 0,
  units_high INT NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  sort INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_bprod_scen FOREIGN KEY (scenario_id) REFERENCES budget_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS budget_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scenario_id INT NOT NULL,
  item_type ENUM('one_time','monthly_fixed') NOT NULL,
  name VARCHAR(190) NOT NULL,
  amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  sort INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_bitem_scen FOREIGN KEY (scenario_id) REFERENCES budget_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS budget_allocations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  scenario_id INT NOT NULL,
  name VARCHAR(190) NOT NULL,
  monthly_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
  sort INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_balloc_scen FOREIGN KEY (scenario_id) REFERENCES budget_scenarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Append the same four `CREATE TABLE IF NOT EXISTS` to `bin/migrate.php`** before `echo "migration complete\n";`, each as `$pdo->exec("…");` followed by `echo "budget_* table ok\n";` (copy the SQL verbatim from Step 1).

- [ ] **Step 3: Add the tables to the truncate list in `tests/DatabaseTestCase.php`** (before `activities`, since none reference budget; order only matters for FK — these reference projects/users which are later in the list, and truncation disables FK checks, but keep children before parents anyway):
```php
        foreach (['activity_log','budget_products','budget_items','budget_allocations','budget_scenarios','transfers','income','expenses','activity_photos','activities','accounts','categories','projects','contacts','settings','users'] as $t) {
```

- [ ] **Step 4: Apply + verify**
```bash
mysql --host=127.0.0.1 --protocol=tcp -uroot -e "DROP DATABASE IF EXISTS lipa_test; CREATE DATABASE lipa_test CHARACTER SET utf8mb4;"
mysql --host=127.0.0.1 --protocol=tcp -uroot lipa_test < db/schema.sql
php bin/migrate.php | tail -6
mysql --host=127.0.0.1 --protocol=tcp -uroot lipa -e "SHOW TABLES LIKE 'budget_%';"
composer test 2>&1 | tail -3
```
Expected: 4 budget tables present in both DBs; suite still green; re-running migrate is idempotent.

- [ ] **Step 5: Commit**
```bash
git add db/schema.sql bin/migrate.php tests/DatabaseTestCase.php
git commit -m "feat(budget): schema + migration for scenarios/products/items/allocations"
```

---

### Task 2: `ScenarioCalc` (pure calculation) — TDD

**Files:** Create `src/Budget/ScenarioCalc.php`; Test `tests/ScenarioCalcTest.php`.

**Interfaces produced:**
- `App\Budget\ScenarioCalc::compute(array $scenario, array $products, array $items, array $allocations): array`
  - `$scenario`: `['funded_amount'=>num]`
  - `$products[]`: `['name','unit_name','sale_price','unit_cost','units_low','units_mid','units_high']`
  - `$items[]`: `['item_type'=>'one_time'|'monthly_fixed','amount'=>num]`
  - `$allocations[]`: `['name','monthly_amount']` (already in sort order)
  - Returns (all money rounded to 2 dp):
    ```
    ['one_time_total','net_startup','fixed_total',
     'products'=>[['name','unit_name','sale_price','unit_cost','margin','margin_negative',
                   'units'=>['low','mid','high'],'contribution'=>['low','mid','high']]],
     'cases'=>['low'=>C,'mid'=>C,'high'=>C],           // C = ['units_total','revenue','variable','fixed','profit','break_even','break_even_unfunded']
     'allocations'=>[['name','monthly_amount','coverage_pct']],
     'alloc_leftover','alloc_note']
    ```
    `break_even*` are `?float` (null = "not recovered").

- [ ] **Step 1: Write the failing test** `tests/ScenarioCalcTest.php`:
```php
<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Budget\ScenarioCalc;

final class ScenarioCalcTest extends TestCase
{
    private function calc(array $p, array $i = [], array $a = [], float $funded = 0.0): array
    {
        return ScenarioCalc::compute(['funded_amount'=>$funded], $p, $i, $a);
    }

    public function test_single_product_profit_and_break_even(): void
    {
        $p = [['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'unit_cost'=>1250,
               'units_low'=>150,'units_mid'=>300,'units_high'=>500]];
        $items = [
            ['item_type'=>'one_time','amount'=>800000],
            ['item_type'=>'monthly_fixed','amount'=>200000],
        ];
        $r = ScenarioCalc::compute(['funded_amount'=>600000], $p, $items, []);
        $this->assertSame(800000.0, $r['one_time_total']);
        $this->assertSame(200000.0, $r['net_startup']);          // 800k - 600k funded
        $this->assertSame(200000.0, $r['fixed_total']);
        $this->assertSame(1250.0, $r['products'][0]['margin']);  // 2500 - 1250
        // realistic: 300*2500=750k rev, 300*1250=375k var, -200k fixed = 175k profit
        $this->assertSame(175000.0, $r['cases']['mid']['profit']);
        $this->assertEqualsWithDelta(1.14, $r['cases']['mid']['break_even'], 0.01); // 200k/175k
        $this->assertEqualsWithDelta(4.57, $r['cases']['mid']['break_even_unfunded'], 0.01); // 800k/175k
        // pessimistic 150 bars → 150*1250 - 200k = -12,500 loss → no break-even
        $this->assertSame(-12500.0, $r['cases']['low']['profit']);
        $this->assertNull($r['cases']['low']['break_even']);
    }

    public function test_multi_product_totals_sum_across_mix(): void
    {
        $p = [
            ['name'=>'Bowl','unit_name'=>'bowl','sale_price'=>5000,'unit_cost'=>2000,'units_low'=>10,'units_mid'=>20,'units_high'=>30],
            ['name'=>'Vase','unit_name'=>'vase','sale_price'=>20000,'unit_cost'=>8000,'units_low'=>2,'units_mid'=>5,'units_high'=>8],
        ];
        $r = ScenarioCalc::compute(['funded_amount'=>0], $p, [['item_type'=>'monthly_fixed','amount'=>50000]], []);
        // mid revenue = 20*5000 + 5*20000 = 100k + 100k = 200k
        $this->assertSame(200000.0, $r['cases']['mid']['revenue']);
        // mid variable = 20*2000 + 5*8000 = 40k + 40k = 80k
        $this->assertSame(80000.0, $r['cases']['mid']['variable']);
        $this->assertSame(70000.0, $r['cases']['mid']['profit']); // 200k-80k-50k
        $this->assertSame(25, $r['cases']['mid']['units_total']);  // 20 bowls + 5 vases
    }

    public function test_negative_margin_flagged(): void
    {
        $p = [['name'=>'X','unit_name'=>'unit','sale_price'=>1000,'unit_cost'=>1500,'units_low'=>1,'units_mid'=>1,'units_high'=>1]];
        $r = $this->calc($p);
        $this->assertTrue($r['products'][0]['margin_negative']);
        $this->assertSame(-500.0, $r['products'][0]['margin']);
    }

    public function test_allocation_waterfall_order_and_leftover(): void
    {
        $p = [['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'unit_cost'=>1250,'units_low'=>300,'units_mid'=>300,'units_high'=>300]];
        // mid profit = 300*1250 - 0 fixed = 375000
        $alloc = [['name'=>'Health','monthly_amount'=>80000], ['name'=>'Rent','monthly_amount'=>150000]];
        $r = ScenarioCalc::compute(['funded_amount'=>0], $p, [], $alloc);
        $this->assertSame(100, $r['allocations'][0]['coverage_pct']);        // health fully
        $this->assertSame(100, $r['allocations'][1]['coverage_pct']);        // rent fully (375k-80k=295k ≥150k)
        $this->assertSame(145000.0, $r['alloc_leftover']);                    // 295k-150k
    }
}
```
- [ ] **Step 2: Run → fail:** `vendor/bin/phpunit tests/ScenarioCalcTest.php` (class not found).

- [ ] **Step 3: Create `src/Budget/ScenarioCalc.php`:**
```php
<?php
namespace App\Budget;

final class ScenarioCalc
{
    public static function compute(array $scenario, array $products, array $items, array $allocations): array
    {
        $r2 = static fn($v) => round((float)$v, 2);
        $sum = static fn(array $rows, string $type) => array_sum(array_map(
            static fn($i) => ($i['item_type'] ?? '') === $type ? (float)$i['amount'] : 0.0, $rows));

        $oneTime = $sum($items, 'one_time');
        $fixed   = $sum($items, 'monthly_fixed');
        $funded  = (float)($scenario['funded_amount'] ?? 0);
        $net     = max($oneTime - $funded, 0.0);

        $cases = ['low'=>0,'mid'=>0,'high'=>0];
        $totals = [];
        foreach ($cases as $k => $_) {
            $totals[$k] = ['units_total'=>0,'revenue'=>0.0,'variable'=>0.0];
        }
        $prodOut = [];
        foreach ($products as $p) {
            $price = (float)($p['sale_price'] ?? 0);
            $cost  = (float)($p['unit_cost'] ?? 0);
            $margin = $price - $cost;
            $units = ['low'=>(int)($p['units_low'] ?? 0),'mid'=>(int)($p['units_mid'] ?? 0),'high'=>(int)($p['units_high'] ?? 0)];
            $contrib = [];
            foreach ($units as $k => $u) {
                $contrib[$k] = $r2($u * $margin);
                $totals[$k]['units_total'] += $u;
                $totals[$k]['revenue']  += $u * $price;
                $totals[$k]['variable'] += $u * $cost;
            }
            $prodOut[] = [
                'name'=>$p['name'] ?? '', 'unit_name'=>$p['unit_name'] ?? 'unit',
                'sale_price'=>$r2($price), 'unit_cost'=>$r2($cost),
                'margin'=>$r2($margin), 'margin_negative'=>$margin <= 0,
                'units'=>$units, 'contribution'=>$contrib,
            ];
        }

        $caseOut = [];
        foreach ($totals as $k => $t) {
            $profit = $t['revenue'] - $t['variable'] - $fixed;
            $caseOut[$k] = [
                'units_total'=>$t['units_total'],
                'revenue'=>$r2($t['revenue']),
                'variable'=>$r2($t['variable']),
                'fixed'=>$r2($fixed),
                'profit'=>$r2($profit),
                'break_even'=>$profit > 0 ? $r2($net / $profit) : null,
                'break_even_unfunded'=>$profit > 0 ? $r2($oneTime / $profit) : null,
            ];
        }

        // allocation waterfall on the realistic (mid) profit
        $remaining = max($caseOut['mid']['profit'], 0.0);
        $allocOut = [];
        foreach ($allocations as $a) {
            $amt = (float)($a['monthly_amount'] ?? 0);
            $cov = $amt > 0 ? min($remaining / $amt, 1.0) : 0.0;
            $remaining = max($remaining - $amt, 0.0);
            $allocOut[] = ['name'=>$a['name'] ?? '', 'monthly_amount'=>$r2($amt), 'coverage_pct'=>(int)round($cov * 100)];
        }
        $leftover = $r2($remaining);
        if ($caseOut['mid']['profit'] <= 0) {
            $note = 'No profit to allocate in the realistic case.';
        } elseif ($allocOut && $leftover > 0) {
            $note = 'All covered — ' . number_format($leftover, 0) . ' TZS/month left for reserves.';
        } elseif ($allocOut) {
            $note = 'Profit does not fully cover the allocations at the realistic volume.';
        } else {
            $note = '';
        }

        return [
            'one_time_total'=>$r2($oneTime), 'net_startup'=>$r2($net), 'fixed_total'=>$r2($fixed),
            'products'=>$prodOut, 'cases'=>$caseOut,
            'allocations'=>$allocOut, 'alloc_leftover'=>$leftover, 'alloc_note'=>$note,
        ];
    }
}
```

- [ ] **Step 4: Run → pass:** `vendor/bin/phpunit tests/ScenarioCalcTest.php` → PASS.

- [ ] **Step 5: Commit**
```bash
git add src/Budget/ScenarioCalc.php tests/ScenarioCalcTest.php
git commit -m "feat(budget): ScenarioCalc — multi-product profit, break-even, allocation waterfall"
```

---

### Task 3: `BudgetScenario` model — TDD

**Files:** Create `src/Models/BudgetScenario.php`; Test `tests/BudgetScenarioTest.php`.

**Interfaces produced (`App\Models\BudgetScenario`):**
- `create(array $data): int` — keys `name,description,project_id,status,funded_amount,created_by`
- `update(int $id, array $data): void` — same minus created_by; bumps `updated_at`
- `find(int $id): ?array`  ·  `all(): array` (LEFT JOIN `project_name`, order `updated_at DESC`)  ·  `delete(int $id): void`
- `products(int $id): array` · `items(int $id, ?string $type=null): array` · `allocations(int $id): array`
- `setProducts(int $id, array $rows): void` · `setItems(int $id, array $rows): void` · `setAllocations(int $id, array $rows): void` — replace-all (delete then insert), each row already validated/zipped by the controller.

- [ ] **Step 1: Write the failing test** `tests/BudgetScenarioTest.php`:
```php
<?php
namespace Tests;
use App\Models\BudgetScenario;

final class BudgetScenarioTest extends DatabaseTestCase
{
    public function test_crud_and_children_roundtrip_and_cascade(): void
    {
        $id = BudgetScenario::create(['name'=>'Soap','description'=>'','project_id'=>null,'status'=>'draft','funded_amount'=>600000,'created_by'=>null]);
        $this->assertSame('Soap', BudgetScenario::find($id)['name']);

        BudgetScenario::setProducts($id, [
            ['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'unit_cost'=>1250,'units_low'=>150,'units_mid'=>300,'units_high'=>500,'notes'=>'','sort'=>0],
        ]);
        BudgetScenario::setItems($id, [
            ['item_type'=>'one_time','name'=>'Molds','amount'=>800000,'notes'=>'','sort'=>0],
            ['item_type'=>'monthly_fixed','name'=>'Rent','amount'=>200000,'notes'=>'','sort'=>0],
        ]);
        BudgetScenario::setAllocations($id, [['name'=>'Health','monthly_amount'=>80000,'sort'=>0]]);

        $this->assertCount(1, BudgetScenario::products($id));
        $this->assertCount(1, BudgetScenario::items($id, 'one_time'));
        $this->assertCount(2, BudgetScenario::items($id));
        $this->assertCount(1, BudgetScenario::allocations($id));

        // replace-all semantics
        BudgetScenario::setProducts($id, []);
        $this->assertCount(0, BudgetScenario::products($id));

        // delete cascades children
        BudgetScenario::delete($id);
        $this->assertNull(BudgetScenario::find($id));
        $this->assertCount(0, BudgetScenario::items($id));
    }
}
```

- [ ] **Step 2: Run → fail.**

- [ ] **Step 3: Create `src/Models/BudgetScenario.php`:**
```php
<?php
namespace App\Models;

use App\Database;

final class BudgetScenario
{
    public static function create(array $d): int
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('INSERT INTO budget_scenarios (name, description, project_id, status, funded_amount, created_by, created_at, updated_at)
            VALUES (:n,:d,:p,:s,:f,:u,NOW(),NOW())');
        $st->execute([':n'=>$d['name'], ':d'=>$d['description'] ?: null, ':p'=>$d['project_id'] ?: null,
            ':s'=>$d['status'] ?? 'draft', ':f'=>$d['funded_amount'] ?: 0, ':u'=>$d['created_by'] ?: null]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        $st = Database::pdo()->prepare('UPDATE budget_scenarios SET name=:n, description=:d, project_id=:p, status=:s, funded_amount=:f, updated_at=NOW() WHERE id=:id');
        $st->execute([':n'=>$d['name'], ':d'=>$d['description'] ?: null, ':p'=>$d['project_id'] ?: null,
            ':s'=>$d['status'] ?? 'draft', ':f'=>$d['funded_amount'] ?: 0, ':id'=>$id]);
    }

    public static function find(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM budget_scenarios WHERE id=:id');
        $st->execute([':id'=>$id]);
        return $st->fetch() ?: null;
    }

    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT b.*, p.name AS project_name FROM budget_scenarios b
             LEFT JOIN projects p ON p.id = b.project_id ORDER BY b.updated_at DESC, b.id DESC'
        )->fetchAll();
    }

    public static function delete(int $id): void
    {
        $st = Database::pdo()->prepare('DELETE FROM budget_scenarios WHERE id=:id');
        $st->execute([':id'=>$id]);
    }

    public static function products(int $id): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM budget_products WHERE scenario_id=:id ORDER BY sort, id');
        $st->execute([':id'=>$id]);
        return $st->fetchAll();
    }

    public static function items(int $id, ?string $type = null): array
    {
        $sql = 'SELECT * FROM budget_items WHERE scenario_id=:id' . ($type ? ' AND item_type=:t' : '') . ' ORDER BY sort, id';
        $st = Database::pdo()->prepare($sql);
        $st->execute($type ? [':id'=>$id, ':t'=>$type] : [':id'=>$id]);
        return $st->fetchAll();
    }

    public static function allocations(int $id): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM budget_allocations WHERE scenario_id=:id ORDER BY sort, id');
        $st->execute([':id'=>$id]);
        return $st->fetchAll();
    }

    public static function setProducts(int $id, array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM budget_products WHERE scenario_id=:id')->execute([':id'=>$id]);
        $ins = $pdo->prepare('INSERT INTO budget_products (scenario_id,name,unit_name,sale_price,unit_cost,units_low,units_mid,units_high,notes,sort)
            VALUES (:s,:n,:u,:sp,:uc,:l,:m,:h,:no,:so)');
        foreach ($rows as $i => $r) {
            $ins->execute([':s'=>$id, ':n'=>$r['name'], ':u'=>$r['unit_name'] ?: 'unit',
                ':sp'=>$r['sale_price'] ?: 0, ':uc'=>$r['unit_cost'] ?: 0,
                ':l'=>(int)($r['units_low'] ?? 0), ':m'=>(int)($r['units_mid'] ?? 0), ':h'=>(int)($r['units_high'] ?? 0),
                ':no'=>$r['notes'] ?: null, ':so'=>$r['sort'] ?? $i]);
        }
    }

    public static function setItems(int $id, array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM budget_items WHERE scenario_id=:id')->execute([':id'=>$id]);
        $ins = $pdo->prepare('INSERT INTO budget_items (scenario_id,item_type,name,amount,notes,sort) VALUES (:s,:t,:n,:a,:no,:so)');
        foreach ($rows as $i => $r) {
            $ins->execute([':s'=>$id, ':t'=>$r['item_type'], ':n'=>$r['name'], ':a'=>$r['amount'] ?: 0, ':no'=>$r['notes'] ?: null, ':so'=>$r['sort'] ?? $i]);
        }
    }

    public static function setAllocations(int $id, array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM budget_allocations WHERE scenario_id=:id')->execute([':id'=>$id]);
        $ins = $pdo->prepare('INSERT INTO budget_allocations (scenario_id,name,monthly_amount,sort) VALUES (:s,:n,:a,:so)');
        foreach ($rows as $i => $r) {
            $ins->execute([':s'=>$id, ':n'=>$r['name'], ':a'=>$r['monthly_amount'] ?: 0, ':so'=>$r['sort'] ?? $i]);
        }
    }
}
```

- [ ] **Step 4: Run → pass.**
- [ ] **Step 5: Commit** `git add src/Models/BudgetScenario.php tests/BudgetScenarioTest.php && git commit -m "feat(budget): BudgetScenario model (CRUD + products/items/allocations children)"`

---

### Task 4: Controller + routes + nav + scenario list

**Files:** Create `src/Controllers/BudgetController.php`, `views/budget/index.php`; Modify `public/index.php`, `views/_shell.php`.

**Interfaces:** Consumes `BudgetScenario`, `ScenarioCalc`, `Project`, `Auth`, `Activity`, `render()`. Produces `BudgetController::index|create|store|show|update|delete|print` and a `rows()` helper for zipping parallel POST arrays.

- [ ] **Step 1: Write `src/Controllers/BudgetController.php`** (list + create/show/delete now; store/update/print filled in Tasks 5–6 — but include full class here so later tasks only edit views/JS):
```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Budget\ScenarioCalc;
use App\Models\BudgetScenario;
use App\Models\Project;
use App\Models\Activity;

final class BudgetController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $rows = [];
        foreach (BudgetScenario::all() as $s) {
            $calc = ScenarioCalc::compute($s, BudgetScenario::products((int)$s['id']),
                BudgetScenario::items((int)$s['id']), BudgetScenario::allocations((int)$s['id']));
            $rows[] = ['s'=>$s, 'products'=>count(BudgetScenario::products((int)$s['id'])), 'calc'=>$calc];
        }
        return render('budget/index', ['rows'=>$rows], 'Budget');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('budget/form', $this->formData(null), 'New scenario');
    }

    public function show(int $id): string
    {
        Auth::requireRole('admin','editor','viewer');
        $s = BudgetScenario::find($id);
        if (!$s) { http_response_code(404); return 'Not found'; }
        return render('budget/form', $this->formData($s), $s['name']);
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('budget/form', $this->formData(null, $error), 'New scenario'); }
        $id = BudgetScenario::create($this->scenarioFields($_POST) + ['created_by'=>Auth::user()['id'] ?? null]);
        $this->saveChildren($id);
        Activity::log(Auth::user()['id'] ?? null, 'create', 'budget', $id, 'Created scenario ' . trim($_POST['name'] ?? ''));
        header('Location: /budget/' . $id); exit;
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!BudgetScenario::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('budget/form', $this->formData(array_merge($_POST,['id'=>$id]), $error), 'Edit scenario'); }
        BudgetScenario::update($id, $this->scenarioFields($_POST));
        $this->saveChildren($id);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'budget', $id, 'Updated scenario');
        header('Location: /budget/' . $id); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        BudgetScenario::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'budget', $id, 'Deleted scenario');
        header('Location: /budget'); exit;
    }

    public function print(int $id): string
    {
        Auth::requireRole('admin','editor','viewer');
        $s = BudgetScenario::find($id);
        if (!$s) { http_response_code(404); return 'Not found'; }
        $products = BudgetScenario::products($id);
        $items = BudgetScenario::items($id);
        $allocations = BudgetScenario::allocations($id);
        $calc = ScenarioCalc::compute($s, $products, $items, $allocations);
        $set = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/budget/print.php';
        return ob_get_clean();
    }

    // ---- helpers ----
    private function scenarioFields(array $in): array
    {
        return [
            'name'=>trim($in['name'] ?? ''),
            'description'=>trim($in['description'] ?? ''),
            'project_id'=>$in['project_id'] ?? null,
            'status'=>in_array($in['status'] ?? '', ['draft','active','archived'], true) ? $in['status'] : 'draft',
            'funded_amount'=>(float)($in['funded_amount'] ?? 0),
        ];
    }

    private function saveChildren(int $id): void
    {
        BudgetScenario::setProducts($id, $this->rows($_POST, 'p_', [
            'name'=>'name','unit_name'=>'unit','sale_price'=>'price','unit_cost'=>'cost',
            'units_low'=>'low','units_mid'=>'mid','units_high'=>'high','notes'=>'notes',
        ]));
        $one = $this->rows($_POST, 'ot_', ['name'=>'name','amount'=>'amount','notes'=>'notes']);
        foreach ($one as &$r) { $r['item_type']='one_time'; } unset($r);
        $fix = $this->rows($_POST, 'mf_', ['name'=>'name','amount'=>'amount','notes'=>'notes']);
        foreach ($fix as &$r) { $r['item_type']='monthly_fixed'; } unset($r);
        BudgetScenario::setItems($id, array_merge($one, $fix));
        BudgetScenario::setAllocations($id, $this->rows($_POST, 'al_', ['name'=>'name','monthly_amount'=>'amount']));
    }

    /** Zip parallel POST arrays prefix+field[] into row dicts; skip rows with an empty 'name'. */
    private function rows(array $post, string $prefix, array $map): array
    {
        $names = $post[$prefix . 'name'] ?? [];
        $out = [];
        foreach ($names as $i => $_) {
            $row = [];
            foreach ($map as $key => $field) {
                $arr = $post[$prefix . $field] ?? [];
                $row[$key] = $arr[$i] ?? '';
            }
            if (trim((string)$row['name']) === '') { continue; }
            $out[] = $row;
        }
        return $out;
    }

    private function validate(array $in): ?string
    {
        if (trim($in['name'] ?? '') === '') { return 'A scenario name is required.'; }
        return null;
    }

    private function formData(?array $row, ?string $error = null): array
    {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        $products = $id ? BudgetScenario::products($id) : [];
        $items = $id ? BudgetScenario::items($id) : [];
        $allocs = $id ? BudgetScenario::allocations($id) : [];
        $scen = $row ?: ['funded_amount'=>0];
        return [
            's'=>$row, 'error'=>$error, 'projects'=>Project::all(true),
            'products'=>$products,
            'one_time'=>array_values(array_filter($items, fn($i)=>$i['item_type']==='one_time')),
            'monthly_fixed'=>array_values(array_filter($items, fn($i)=>$i['item_type']==='monthly_fixed')),
            'allocations'=>$allocs,
            'calc'=>ScenarioCalc::compute($scen, $products, $items, $allocs),
        ];
    }
}
```

- [ ] **Step 2: Add routes to `public/index.php`** (`use App\Controllers\BudgetController;` near the others):
```php
$router->add('GET',  '/budget',              fn() => (new BudgetController())->index());
$router->add('GET',  '/budget/new',          fn() => (new BudgetController())->create());
$router->add('POST', '/budget',              fn() => (new BudgetController())->store());
$router->add('GET',  '/budget/:id',          fn($p) => (new BudgetController())->show((int)$p['id']));
$router->add('POST', '/budget/:id',          fn($p) => (new BudgetController())->update((int)$p['id']));
$router->add('POST', '/budget/:id/delete',   fn($p) => (new BudgetController())->delete((int)$p['id']));
$router->add('GET',  '/budget/:id/print',    fn($p) => (new BudgetController())->print((int)$p['id']));
```

- [ ] **Step 3: Add the nav item** in `views/_shell.php` — inside the **same `.nav-group` as Reports**, directly under it:
```php
        <div class="nav-group">
          <a class="nav-item<?= $navActive('/reports') ?>" href="/reports"><?= $svg($ic['reports']) ?>Reports</a>
          <a class="nav-item<?= $navActive('/budget') ?>" href="/budget"><?= $svg($ic['budget']) ?>Budget</a>
        </div>
```
and add a `budget` icon to the `$ic` map near the other icons:
```php
  'budget'    => '<path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="4" width="3" height="14"/>',
```

- [ ] **Step 4: Write `views/budget/index.php`** (ledger list):
```php
<div class="row-between" style="margin-bottom:16px">
  <span class="count"><?= count($rows) ?> scenario<?= count($rows) === 1 ? '' : 's' ?></span>
  <?php if (App\Auth::is('admin','editor')): ?><a class="btn list-new" href="/budget/new">+ New scenario</a><?php endif; ?>
</div>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Scenario</th><th>Project</th><th>Products</th><th class="r">Profit / mo (realistic)</th><th class="r">Break-even</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): $s = $row['s']; $mid = $row['calc']['cases']['mid']; ?>
      <tr>
        <td class="name"><a href="/budget/<?= (int)$s['id'] ?>"><?= e($s['name']) ?></a></td>
        <td><?php if (!empty($s['project_name'])): ?><span class="tag"><?= e($s['project_name']) ?></span><?php endif; ?></td>
        <td class="muted-cell num"><?= (int)$row['products'] ?></td>
        <td class="r money" style="color:var(--<?= $mid['profit'] >= 0 ? 'pos' : 'neg' ?>)"><?= number_format($mid['profit'], 0) ?></td>
        <td class="r muted-cell"><?= $mid['break_even'] !== null ? number_format($mid['break_even'], 1) . ' mo' : '—' ?></td>
        <td><span class="badge <?= $s['status'] === 'active' ? 'on' : 'off' ?>"><span class="bdot"></span><?= e(ucfirst($s['status'])) ?></span></td>
        <td class="r">
          <?php if (App\Auth::is('admin','editor')): ?>
            <div class="rowact">
              <a class="edit" href="/budget/<?= (int)$s['id'] ?>" aria-label="Open"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="post" action="/budget/<?= (int)$s['id'] ?>/delete" style="display:inline" data-confirm="Delete this scenario?">
                <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
              </form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="muted-cell">No scenarios yet. Create one to plan a production activity.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
```

- [ ] **Step 5: Verify (lint + list renders + nav):**
```bash
php -l src/Controllers/BudgetController.php && php -l views/budget/index.php && php -l views/_shell.php && php -l public/index.php
```
Start server, log in as admin, visit `/budget` → 200, empty-state row shows, "Budget" appears in the sidebar under Reports. Viewer sees the list without New/Edit/Delete. (Full form/print in the next tasks — `/budget/new` will render once `views/budget/form.php` exists in Task 5.)

- [ ] **Step 6: Commit**
```bash
git add src/Controllers/BudgetController.php views/budget/index.php public/index.php views/_shell.php
git commit -m "feat(budget): controller + routes + nav + scenario list"
```

---

### Task 5: Combined edit + results page (view) + live-preview JS

**Files:** Create `views/budget/form.php`, `public/assets/js/budget.js`; Modify `views/_shell.php` (load `budget.js`).

**Interfaces:** Consumes the `formData()` payload (`s, error, projects, products, one_time, monthly_fixed, allocations, calc`). POST field names are parallel arrays with prefixes: products `p_name[] p_unit[] p_price[] p_cost[] p_low[] p_mid[] p_high[] p_notes[]`; start-up `ot_name[] ot_amount[] ot_notes[]`; fixed `mf_name[] mf_amount[] mf_notes[]`; allocations `al_name[] al_amount[]`; scenario scalars `name, description, project_id, status, funded_amount`.

- [ ] **Step 1: Write `views/budget/form.php`.** Structure (server renders stored inputs + `calc` results; `budget.js` recomputes live). Use `form-card` sections; each dynamic list is a `<table>` with a hidden template `<tr>` cloned by JS; each editable amount uses `class="bnum"` and `data-*` hooks the JS reads. Render:
  - hidden `<div id="budget-root">` wrapping everything with `data-funded` etc. is not needed — JS reads inputs directly by name.
  - **Base fields:** `name` (required), `description`, `project_id` select, `status` select.
  - **Products** table: columns Product / Unit / Price / Cost / Low / Mid / High / (remove). Row inputs named `p_name[]`,`p_unit[]`,`p_price[]`,`p_cost[]`,`p_low[]`,`p_mid[]`,`p_high[]`. Pre-fill from `$products`; render one blank row if none. "＋ Add product" button. Optional per-row **batch helper**: a small "⚙" toggling two inputs (batch total, units per batch) whose product fills that row's `p_cost` via JS (not submitted).
  - **Start-up costs** table (`ot_name[]`,`ot_amount[]`,`ot_notes[]`) + a **Funded by partner** input `funded_amount` + a JS-computed "NGO share to recover" line.
  - **Fixed costs / month** table (`mf_name[]`,`mf_amount[]`,`mf_notes[]`).
  - **Allocations** table (`al_name[]`,`al_amount[]` → `monthly_amount`).
  - **Results** block (ids the JS updates): KPI hero `#r-profit`, `#r-breakeven`, `#r-revenue`; per-product results `#r-products` (name, margin, realistic units, contribution); three-cases table cells `#c-{low|mid|high}-{revenue|variable|fixed|profit|be}`; allocation bars `#r-allocs` + note `#r-allocnote`.
  - Action bar: **Save** (`type=submit`), **Print / PDF** (`<a href="/budget/:id/print" target="_blank">`, only when `$s['id']`), **Cancel**. Form `id="budget-form" method="post" action="<?= $s['id'] ? '/budget/'.$s['id'] : '/budget' ?>"`. Accountant (`!Auth::is('admin','editor')`): add `disabled` to all inputs and hide Save (same pattern as read-only settings).
  Render the **initial** results from `$calc` server-side (so the page is correct before JS runs and for the accountant). Keep the markup lifted from `lipa-budget-mockup.html` (`.items`, `.controls`, `.kpis`, `.cases`, `.alloc-row`, `.bar`) but retokenised to app classes where equivalents exist (`.card`, `.kpi`, `.section-title`).

- [ ] **Step 2: Write `public/assets/js/budget.js`** — dynamic rows + a JS mirror of `ScenarioCalc`. Guard on `#budget-form`. Functions: `addRow(tableId)` clones the table's `<template>` row; delegated click removes a row (`[data-row-remove]`) and runs batch-helpers; `recompute()` reads all inputs, mirrors the PHP formulas exactly (per-product margin/contribution; per-case revenue/variable/fixed/profit; break-even `net/profit`; allocation waterfall on mid), and writes the `#r-*`/`#c-*` cells with whole-number `toLocaleString`. Bind `input`+`change` on the form → `recompute()`; run once on load. **Comment at top:** "Canonical calculation is `src/Budget/ScenarioCalc.php`; this mirror is preview-only — the server value wins on Save/print." Money rounding: whole numbers to match the PHP display.

- [ ] **Step 3: Load `budget.js`** — in `views/_shell.php`, after `app.js`, add a guarded include only on budget pages:
```php
  <?php if (str_starts_with($reqPath, '/budget')): ?><script src="<?= asset('/assets/js/budget.js') ?>"></script><?php endif; ?>
```

- [ ] **Step 4: Verify (e2e create + save + reload parity).**
```bash
php -l views/budget/form.php
```
Log in as admin: `/budget/new` renders; add 2 products + a start-up item + a fixed item + one allocation; watch results update live as you type; Save → redirects to `/budget/:id`; the **server-rendered** figures on reload match what the live preview showed (spot-check realistic profit + break-even). Edit, remove a product, Save → persists. Viewer: `/budget/:id` shows inputs disabled, no Save; `POST /budget/:id` → 403.

- [ ] **Step 5: Commit**
```bash
git add views/budget/form.php public/assets/js/budget.js views/_shell.php
git commit -m "feat(budget): combined edit+results page with live-preview mirror of ScenarioCalc"
```

---

### Task 6: Printable scenario (donor/board PDF)

**Files:** Create `views/budget/print.php`.

**Interfaces:** Consumes `$s` (scenario), `$products`, `$items`, `$allocations`, `$calc`, `$set` (settings) — all in scope from `BudgetController::print()`.

- [ ] **Step 1: Write `views/budget/print.php`** — standalone page mirroring `views/reports/statement.php`: links `theme.css` + `print.css`, injects the org accent (`<style>:root{--accent: <?= e(\App\hex_color($set['accent_color'] ?? null)) ?>;}</style>`), a `.doc-head` org header (name, `Tax ID`, `No.`), title = scenario name + status, then:
  - **Products** table (name · price · cost · margin · realistic units/mo · contribution) from `$calc['products']`.
  - **Start-up** block (one_time lines, Total, − Funded, = NGO share) + **Fixed / month** block.
  - **Summary** KPIs (realistic profit hero, break-even, total realistic revenue) via `.summary`.
  - **Three cases** totals table (`.cases`-style) from `$calc['cases']`.
  - **Allocation coverage** rows with `.cat-bar`-like tracks + the `$calc['alloc_note']`.
  - **Assumptions** line + the **planning-only disclaimer** (verbatim: "Planning scenario — not an accounting record. Figures are projections and do not appear in the organisation's cashbook, statements, or exports.").
  Money via `number_format($v, 0)`. Print actions (`Print / Save as PDF`, `Back`) in a `.actions` bar hidden on print.

- [ ] **Step 2: Verify**
```bash
php -l views/budget/print.php
```
`GET /budget/:id/print` → 200, renders the scenario with server-computed figures; Print preview looks clean (headers repeat, no row splits — inherited from `print.css`). Accent matches org.

- [ ] **Step 3: Commit**
```bash
git add views/budget/print.php
git commit -m "feat(budget): printable scenario (donor/board PDF)"
```

---

### Task 7: Firewall + role guards + final

**Files:** Create `tests/BudgetFirewallTest.php`.

- [ ] **Step 1: Write the firewall test** `tests/BudgetFirewallTest.php`:
```php
<?php
namespace Tests;
use App\Models\BudgetScenario;
use App\Database;

final class BudgetFirewallTest extends DatabaseTestCase
{
    public function test_saving_a_scenario_never_touches_cashbook_tables(): void
    {
        $pdo = Database::pdo();
        $before = [];
        foreach (['income','expenses','transfers','accounts'] as $t) {
            $before[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        }
        $id = BudgetScenario::create(['name'=>'Soap','description'=>'','project_id'=>null,'status'=>'draft','funded_amount'=>0,'created_by'=>null]);
        BudgetScenario::setProducts($id, [['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'unit_cost'=>1250,'units_low'=>1,'units_mid'=>1,'units_high'=>1,'notes'=>'','sort'=>0]]);
        BudgetScenario::setItems($id, [['item_type'=>'one_time','name'=>'Molds','amount'=>800000,'notes'=>'','sort'=>0]]);
        foreach (['income','expenses','transfers','accounts'] as $t) {
            $this->assertSame($before[$t], (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(), "$t must be untouched by budget");
        }
    }
}
```

- [ ] **Step 2: Run → pass:** `vendor/bin/phpunit tests/BudgetFirewallTest.php`.

- [ ] **Step 3: Full suite + role e2e.**
```bash
composer test 2>&1 | tail -3
```
Manually (server): editor can create/edit/delete + print; viewer gets the list + read-only scenario + print, and `POST /budget` / `POST /budget/:id` / `POST /budget/:id/delete` → 403; a scenario figure appears **nowhere** in `/reports`, the Excel export, or dashboard balances (grep the report/export code paths — they never query `budget_*`).

- [ ] **Step 4: Update local `CLAUDE.md`** (gitignored) — add a "Budget scenarios (planning layer)" note: tables, `ScenarioCalc` canonical + JS mirror, firewall, roles, nav under Reports.

- [ ] **Step 5: Commit + finish**
```bash
git add tests/BudgetFirewallTest.php
git commit -m "test(budget): firewall — scenarios never write to the cashbook"
```
Then use **finishing-a-development-branch**: verify `composer test` green, merge `feature-budget-scenarios` → `master` after the user confirms locally, deploy (`git pull` + `composer install --no-dev` + `php bin/migrate.php` for the 4 tables).

---

## Self-Review
- **Spec coverage:** firewall → Task 7 (+ constraint in every task). Data model (4 tables) → Task 1. Hybrid calc (canonical PHP + JS mirror) → Task 2 (PHP) + Task 5 (JS mirror, comment-marked preview-only). Multi-product (products child, sum across mix) → Tasks 2/3/5. `funded_amount` + net/unfunded break-even → Task 2. Whole-number display → views (Tasks 4–6). One combined page + print → Tasks 5/6. List → Task 4. Nav under Reports → Task 4. Roles view/edit/read-only → controller guards (Task 4) + view disabling (Task 5). Activity log → controller (Task 4). Tests (ScenarioCalc, model, firewall) → Tasks 2/3/7. Out-of-scope (per-batch material rows, Feature B, charts) honoured.
- **Placeholder scan:** none. The one deliberate "trap" assertion in Task 2 Step 1 (`units_total` 30) is called out explicitly with the fix (25) — intentional TDD red, not a placeholder.
- **Type consistency:** `ScenarioCalc::compute($scenario,$products,$items,$allocations)` signature identical across Tasks 2/4/6; `BudgetScenario` method names (`create/update/find/all/delete/products/items/allocations/setProducts/setItems/setAllocations`) match between Task 3 definition and Task 4 call sites; POST prefixes (`p_ ot_ mf_ al_`) consistent between Task 4 `saveChildren()` and Task 5 form field names; `calc` result keys used in views match the Task 2 return shape.
