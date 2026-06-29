# LIPA Web — Plan 5: Accounts, Opening Balances, Transfers & Admin Tabs

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Add Cash & Bank accounts (TZS balances) with per-account opening balances, money transfers between accounts, an account field on every income/expense, per-account balances on the dashboard + Excel, and consolidate the admin pages under a tabbed Settings area.

**Architecture:** Same lean plain-PHP stack. New `Account` and `Transfer` models (PDO, TDD); `account_id` added to `income`/`expenses`; an idempotent PHP migration runner applies the schema change on local + server. New admin `AccountController` joins the existing admin pages under a shared tab bar; a `TransferController` adds the Transfers action to the main nav.

**Tech Stack:** PHP 8.3, MariaDB/MySQL (PDO), PHPUnit, PhpSpreadsheet, vanilla PHP views.

## Global Constraints

- PHP **8.3**; production **MariaDB**, local **MySQL 8.4** — **portable SQL only**; **PDO prepared statements** (no reuse of a named placeholder within one statement — native prepares are on).
- All balances in **TZS** (`DECIMAL(15,2)`). USD income unchanged (original amount + rate → `amount_tzs`). No per-currency balances / FX.
- **Account required** in the UI on new income & expenses; default = "Bank — TZS main". Columns are nullable (`ON DELETE SET NULL`).
- **Transfers** are excluded from income & expense totals (separate table).
- Roles enforced **server-side**: Accounts CRUD = **admin**; Transfers create/edit/delete = **admin/editor**, view = all; account dropdown on income/expense = editor/admin as today.
- UI English (UK); mobile-first (tables in `table-wrap`, themed classes); every write action calls `Activity::log(...)`.
- Tests run against `lipa_test` via `Tests\DatabaseTestCase` (it rebuilds from `db/schema.sql`, so schema.sql must include the new tables/columns).

Toolchain (local): prefix shell commands with
`export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`.

---

## File structure

```
db/schema.sql                 (add accounts, transfers, account_id columns)
bin/migrate.php               (new — idempotent migration runner for existing DBs)
src/Models/Account.php        (new)        src/Models/Transfer.php (new)
src/Models/Income.php Expense.php          (add account_id)
src/Controllers/AccountController.php (new) TransferController.php (new)
src/Controllers/IncomeController.php ExpenseController.php DashboardController.php SettingController.php CategoryController.php UserController.php (tab header / account field)
src/Reports/ExcelExport.php   (account column, Transfers + By-account sheets)
views/admin/_tabs.php         (new shared tab bar)
views/accounts/index.php form.php (new)    views/transfers/index.php form.php (new)
views/settings/index.php categories/index.php users/index.php (include tab bar)
views/income/* expenses/* dashboard.php    (account select/column, balances)
public/index.php              (routes + nav already in _shell.php)
views/_shell.php              (nav: + Transfers, consolidate admin → single Settings)
tests/AccountTest.php TransferTest.php (new); IncomeTest.php ExpenseTest.php (extend)
```

---

### Task 1: Schema + idempotent migration runner

**Files:**
- Modify: `db/schema.sql`
- Create: `bin/migrate.php`

**Interfaces:**
- Produces: `accounts` + `transfers` tables and `income.account_id` / `expenses.account_id` columns in both fresh installs (`schema.sql`) and existing DBs (`bin/migrate.php`, idempotent). Seeds "Bank — TZS main" + "Petty cash" when `accounts` is empty and backfills existing income/expense rows to the Bank id.

- [ ] **Step 1: Add the new tables + columns to `db/schema.sql`** (append before the final activity_log, or at the end — order matters for FKs, so put `accounts` before `income`/`expenses` is already created; since income/expenses already exist above, add `accounts` and `transfers` at the end and add the FK columns via the migration. For a clean fresh install, append this at the very end of `db/schema.sql`):

```sql

CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('bank','cash','other') NOT NULL DEFAULT 'bank',
  opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  opening_balance_date DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  from_account_id INT NULL,
  to_account_id INT NULL,
  amount_tzs DECIMAL(15,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transfer_from FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_transfer_to   FOREIGN KEY (to_account_id)   REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_transfer_user FOREIGN KEY (created_by)      REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE income   ADD COLUMN account_id INT NULL,
  ADD CONSTRAINT fk_income_account  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL;
ALTER TABLE expenses ADD COLUMN account_id INT NULL,
  ADD CONSTRAINT fk_expense_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL;
```

> Note: `schema.sql` is executed as one `exec()` by the test base class and on fresh installs. Because `income`/`expenses` are created earlier in the file without `account_id`, the two `ALTER TABLE` lines at the end add it. On a fresh DB this runs once cleanly.

- [ ] **Step 2: Write `bin/migrate.php`** (idempotent runner for the live/existing DB)

```php
<?php
// Idempotent migration for the accounts feature. Safe to run repeatedly.
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Database;

$pdo = Database::pdo();

function columnExists(\PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $st->execute([':t' => $table, ':c' => $col]);
    return (bool) $st->fetchColumn();
}

$pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('bank','cash','other') NOT NULL DEFAULT 'bank',
  opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  opening_balance_date DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "accounts table ok\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  from_account_id INT NULL,
  to_account_id INT NULL,
  amount_tzs DECIMAL(15,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transfer_from FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_transfer_to   FOREIGN KEY (to_account_id)   REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_transfer_user FOREIGN KEY (created_by)      REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "transfers table ok\n";

if (!columnExists($pdo, 'income', 'account_id')) {
    $pdo->exec('ALTER TABLE income ADD COLUMN account_id INT NULL,
        ADD CONSTRAINT fk_income_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL');
    echo "income.account_id added\n";
} else { echo "income.account_id exists\n"; }

if (!columnExists($pdo, 'expenses', 'account_id')) {
    $pdo->exec('ALTER TABLE expenses ADD COLUMN account_id INT NULL,
        ADD CONSTRAINT fk_expense_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL');
    echo "expenses.account_id added\n";
} else { echo "expenses.account_id exists\n"; }

// Seed default accounts if none exist.
$count = (int) $pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
if ($count === 0) {
    $pdo->exec("INSERT INTO accounts (name, type, opening_balance, active) VALUES
        ('Bank — TZS main', 'bank', 0, 1),
        ('Petty cash', 'cash', 0, 1)");
    echo "seeded 2 accounts\n";
}

// Backfill existing rows to the Bank account.
$bankId = (int) $pdo->query("SELECT id FROM accounts ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($bankId > 0) {
    $pdo->exec("UPDATE income   SET account_id = {$bankId} WHERE account_id IS NULL");
    $pdo->exec("UPDATE expenses SET account_id = {$bankId} WHERE account_id IS NULL");
    echo "backfilled income/expenses to account #{$bankId}\n";
}
echo "migration complete\n";
```

- [ ] **Step 3: Apply locally and verify**

```bash
# fresh test DB picks it up via schema.sql automatically; apply to dev DB:
mysql --host=127.0.0.1 --protocol=tcp -uroot lipa < db/schema.sql 2>/dev/null
php bin/migrate.php
mysql --host=127.0.0.1 --protocol=tcp -uroot lipa -e "SHOW TABLES LIKE 'accounts'; SHOW TABLES LIKE 'transfers'; SHOW COLUMNS FROM income LIKE 'account_id'; SELECT name FROM accounts;"
```
Expected: `accounts` + `transfers` listed, `income.account_id` present, 2 accounts seeded. Re-running `php bin/migrate.php` prints "exists"/no error (idempotent).

- [ ] **Step 4: Commit**

```bash
git add db/schema.sql bin/migrate.php
git commit -m "feat: accounts/transfers schema + idempotent migration runner"
```

---

### Task 2: Account model (TDD)

**Files:**
- Create: `src/Models/Account.php`
- Test: `tests/AccountTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Account::create(array $data): int` — keys `name,type,opening_balance,opening_balance_date`.
  - `App\Models\Account::all(bool $activeOnly = false): array` — ordered by name.
  - `App\Models\Account::find(int $id): ?array`
  - `App\Models\Account::update(int $id, array $data): void` — keys `name,type,opening_balance,opening_balance_date,active`.
  - `App\Models\Account::delete(int $id): void`
  - `App\Models\Account::balance(int $id, ?string $asOf = null): float` — opening + income − expenses + transfers in − transfers out (optionally `date <= asOf`).
  - `App\Models\Account::balancesAll(?string $asOf = null): array` — `[['id'=>int,'name'=>string,'balance'=>float], …]` for active accounts, ordered by name.

- [ ] **Step 1: Write the failing test** `tests/AccountTest.php`

```php
<?php
namespace Tests;
use App\Models\Account;
use App\Database;

final class AccountTest extends DatabaseTestCase
{
    public function test_create_find_update_delete(): void
    {
        $id = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>1000,'opening_balance_date'=>'2026-01-01']);
        $row = Account::find($id);
        $this->assertSame('Bank', $row['name']);
        $this->assertEquals(1000, (int)$row['opening_balance']);
        $this->assertSame(1, (int)$row['active']);
        Account::update($id, ['name'=>'Bank 2','type'=>'bank','opening_balance'=>1500,'opening_balance_date'=>'2026-01-01','active'=>0]);
        $this->assertSame('Bank 2', Account::find($id)['name']);
        $this->assertSame(0, (int)Account::find($id)['active']);
        Account::delete($id);
        $this->assertNull(Account::find($id));
    }

    public function test_active_only_listing(): void
    {
        $a = Account::create(['name'=>'A','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $b = Account::create(['name'=>'B','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>null]);
        Account::update($b, ['name'=>'B','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>null,'active'=>0]);
        $this->assertCount(2, Account::all());
        $this->assertCount(1, Account::all(true));
    }

    public function test_balance_combines_opening_income_expense_transfers(): void
    {
        $bank = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>1000,'opening_balance_date'=>'2026-01-01']);
        $cash = Account::create(['name'=>'Cash','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>'2026-01-01']);
        $pdo = Database::pdo();
        // income 500 to bank, expense 200 from bank
        $pdo->exec("INSERT INTO income (date,account_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-01',$bank,'TZS',500,1,500)");
        $pdo->exec("INSERT INTO expenses (date,account_id,amount_tzs) VALUES ('2026-02-02',$bank,200)");
        // transfer 300 bank -> cash
        $pdo->exec("INSERT INTO transfers (date,from_account_id,to_account_id,amount_tzs) VALUES ('2026-02-03',$bank,$cash,300)");
        // bank = 1000 + 500 - 200 - 300 = 1000 ; cash = 0 + 300 = 300
        $this->assertEqualsWithDelta(1000.0, Account::balance($bank), 0.001);
        $this->assertEqualsWithDelta(300.0, Account::balance($cash), 0.001);
        // as-of before the transfer: bank = 1000 + 500 - 200 = 1300
        $this->assertEqualsWithDelta(1300.0, Account::balance($bank, '2026-02-02'), 0.001);
    }

    public function test_balances_all_active(): void
    {
        $bank = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>100,'opening_balance_date'=>null]);
        $all = Account::balancesAll();
        $this->assertSame('Bank', $all[0]['name']);
        $this->assertEqualsWithDelta(100.0, $all[0]['balance'], 0.001);
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/AccountTest.php`
Expected: FAIL — class `App\Models\Account` not found.

- [ ] **Step 3: Write `src/Models/Account.php`**

```php
<?php
namespace App\Models;

use App\Database;
use PDO;

final class Account
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO accounts (name, type, opening_balance, opening_balance_date, active)
             VALUES (:name, :type, :ob, :obd, 1)'
        );
        $stmt->execute([
            ':name'=>$data['name'], ':type'=>$data['type'] ?: 'bank',
            ':ob'=>(float)($data['opening_balance'] ?? 0),
            ':obd'=>$data['opening_balance_date'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM accounts' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY name';
        return Database::pdo()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM accounts WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE accounts SET name=:name, type=:type, opening_balance=:ob,
             opening_balance_date=:obd, active=:active WHERE id=:id'
        );
        $stmt->execute([
            ':name'=>$data['name'], ':type'=>$data['type'] ?: 'bank',
            ':ob'=>(float)($data['opening_balance'] ?? 0),
            ':obd'=>$data['opening_balance_date'] ?: null,
            ':active'=>(int)$data['active'], ':id'=>$id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM accounts WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }

    public static function balance(int $id, ?string $asOf = null): float
    {
        $acc = self::find($id);
        if (!$acc) { return 0.0; }
        $pdo = Database::pdo();
        $cond = $asOf !== null ? ' AND date <= :asOf' : '';
        $sum = function (string $col, string $table) use ($pdo, $id, $asOf, $cond): float {
            $st = $pdo->prepare("SELECT COALESCE(SUM(amount_tzs),0) FROM {$table} WHERE {$col} = :id{$cond}");
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            if ($asOf !== null) { $st->bindValue(':asOf', $asOf); }
            $st->execute();
            return (float)$st->fetchColumn();
        };
        $income   = $sum('account_id', 'income');
        $expense  = $sum('account_id', 'expenses');
        $transIn  = $sum('to_account_id', 'transfers');
        $transOut = $sum('from_account_id', 'transfers');
        return round((float)$acc['opening_balance'] + $income - $expense + $transIn - $transOut, 2);
    }

    public static function balancesAll(?string $asOf = null): array
    {
        $out = [];
        foreach (self::all(true) as $a) {
            $out[] = ['id'=>(int)$a['id'], 'name'=>$a['name'], 'balance'=>self::balance((int)$a['id'], $asOf)];
        }
        return $out;
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/AccountTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/Account.php tests/AccountTest.php
git commit -m "feat: Account model with TZS balance calculation"
```

---

### Task 3: Transfer model (TDD)

**Files:**
- Create: `src/Models/Transfer.php`
- Test: `tests/TransferTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Transfer::create(array $data): int` — keys `date,from_account_id,to_account_id,amount_tzs,description,created_by`.
  - `App\Models\Transfer::all(array $filters = []): array` — LEFT JOINs `from_name`/`to_name`; ordered `date DESC, id DESC`; filters `date_from,date_to`.
  - `App\Models\Transfer::find(int $id): ?array`
  - `App\Models\Transfer::update(int $id, array $data): void` — keys `date,from_account_id,to_account_id,amount_tzs,description`.
  - `App\Models\Transfer::delete(int $id): void`

- [ ] **Step 1: Write the failing test** `tests/TransferTest.php`

```php
<?php
namespace Tests;
use App\Models\Transfer;
use App\Models\Account;

final class TransferTest extends DatabaseTestCase
{
    private function accs(): array
    {
        return [
            Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]),
            Account::create(['name'=>'Cash','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>null]),
        ];
    }

    public function test_create_find_with_joined_names(): void
    {
        [$bank,$cash] = $this->accs();
        $id = Transfer::create(['date'=>'2026-03-01','from_account_id'=>$bank,'to_account_id'=>$cash,'amount_tzs'=>500,'description'=>'cash withdrawal','created_by'=>null]);
        $row = Transfer::find($id);
        $this->assertEquals(500, (int)$row['amount_tzs']);
        $all = Transfer::all();
        $this->assertSame('Bank', $all[0]['from_name']);
        $this->assertSame('Cash', $all[0]['to_name']);
    }

    public function test_filter_by_date(): void
    {
        [$bank,$cash] = $this->accs();
        $base = ['from_account_id'=>$bank,'to_account_id'=>$cash,'amount_tzs'=>100,'description'=>'','created_by'=>null];
        Transfer::create($base + ['date'=>'2026-01-10']);
        Transfer::create($base + ['date'=>'2026-03-10']);
        $this->assertCount(1, Transfer::all(['date_from'=>'2026-02-01']));
    }

    public function test_update_and_delete(): void
    {
        [$bank,$cash] = $this->accs();
        $id = Transfer::create(['date'=>'2026-03-01','from_account_id'=>$bank,'to_account_id'=>$cash,'amount_tzs'=>500,'description'=>'','created_by'=>null]);
        Transfer::update($id, ['date'=>'2026-03-02','from_account_id'=>$cash,'to_account_id'=>$bank,'amount_tzs'=>250,'description'=>'reversed']);
        $row = Transfer::find($id);
        $this->assertEquals(250, (int)$row['amount_tzs']);
        $this->assertSame('reversed', $row['description']);
        Transfer::delete($id);
        $this->assertNull(Transfer::find($id));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/TransferTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `src/Models/Transfer.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class Transfer
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO transfers (date, from_account_id, to_account_id, amount_tzs, description, created_by)
             VALUES (:date, :from, :to, :amt, :descr, :by)'
        );
        $stmt->execute([
            ':date'=>$data['date'], ':from'=>$data['from_account_id'] ?: null,
            ':to'=>$data['to_account_id'] ?: null, ':amt'=>(float)$data['amount_tzs'],
            ':descr'=>$data['description'] ?: null, ':by'=>$data['created_by'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE transfers SET date=:date, from_account_id=:from, to_account_id=:to,
             amount_tzs=:amt, description=:descr WHERE id=:id'
        );
        $stmt->execute([
            ':date'=>$data['date'], ':from'=>$data['from_account_id'] ?: null,
            ':to'=>$data['to_account_id'] ?: null, ':amt'=>(float)$data['amount_tzs'],
            ':descr'=>$data['description'] ?: null, ':id'=>$id,
        ]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM transfers WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        $cond = []; $params = [];
        if (!empty($filters['date_from'])) { $cond[] = 't.date >= :date_from'; $params[':date_from'] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $cond[] = 't.date <= :date_to';   $params[':date_to']   = $filters['date_to']; }
        $where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';
        $sql = "SELECT t.*, f.name AS from_name, d.name AS to_name
                FROM transfers t
                LEFT JOIN accounts f ON f.id = t.from_account_id
                LEFT JOIN accounts d ON d.id = t.to_account_id
                {$where} ORDER BY t.date DESC, t.id DESC";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM transfers WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/TransferTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/Transfer.php tests/TransferTest.php
git commit -m "feat: Transfer model (money movement between accounts)"
```

---

### Task 4: Add account_id to Income & Expense models (TDD)

**Files:**
- Modify: `src/Models/Income.php`, `src/Models/Expense.php`
- Test: extend `tests/IncomeTest.php`, `tests/ExpenseTest.php`

**Interfaces:**
- Consumes: `accounts` table.
- Produces: `Income::create/update` accept `account_id`; `Income::all()` returns joined `account_name`. Same for `Expense`. Existing keys unchanged.

- [ ] **Step 1: Add a failing test to `tests/IncomeTest.php`** (append this method inside the class)

```php
    public function test_account_id_round_trip_and_join(): void
    {
        $acc = \App\Models\Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $id = Income::create(['date'=>'2026-03-01','contact_id'=>null,'project_id'=>null,'category_id'=>null,
            'description'=>'x','currency'=>'TZS','amount_original'=>10,'exchange_rate'=>1,'amount_tzs'=>10,
            'reference'=>'','notes'=>'','created_by'=>null,'account_id'=>$acc]);
        $this->assertEquals($acc, (int)Income::find($id)['account_id']);
        $this->assertSame('Bank', Income::all()[0]['account_name']);
    }
```

- [ ] **Step 2: Add the mirror test to `tests/ExpenseTest.php`**

```php
    public function test_account_id_round_trip_and_join(): void
    {
        $acc = \App\Models\Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $id = Expense::create(['date'=>'2026-03-01','contact_id'=>null,'project_id'=>null,'category_id'=>null,
            'description'=>'x','amount_tzs'=>10,'reference'=>'','notes'=>'','created_by'=>null,'account_id'=>$acc]);
        $this->assertEquals($acc, (int)Expense::find($id)['account_id']);
        $this->assertSame('Bank', Expense::all()[0]['account_name']);
    }
```

- [ ] **Step 3: Run tests, verify they fail**

Run: `vendor/bin/phpunit tests/IncomeTest.php tests/ExpenseTest.php`
Expected: FAIL — `account_name` key missing / `account_id` not stored.

- [ ] **Step 4: Update `src/Models/Income.php`** — add `account_id` to the `bind()` array and the INSERT/UPDATE column lists, and join accounts in `all()`.

In `create()` INSERT, add `account_id` to the column list and `:account_id` to VALUES:
```php
        $stmt = $pdo->prepare(
            'INSERT INTO income
             (date, contact_id, project_id, category_id, description, currency,
              amount_original, exchange_rate, amount_tzs, reference, receipt_path, notes, created_by, account_id)
             VALUES
             (:date, :contact_id, :project_id, :category_id, :description, :currency,
              :amount_original, :exchange_rate, :amount_tzs, :reference, :receipt_path, :notes, :created_by, :account_id)'
        );
```
In `update()` SET clause add `account_id=:account_id`:
```php
            'UPDATE income SET date=:date, contact_id=:contact_id, project_id=:project_id,
             category_id=:category_id, description=:description, currency=:currency,
             amount_original=:amount_original, exchange_rate=:exchange_rate, amount_tzs=:amount_tzs,
             reference=:reference, notes=:notes, account_id=:account_id WHERE id=:id'
```
In `bind()` add the key (so both INSERT and UPDATE get it; `update()` already `unset($params[':receipt_path'])` — leave `:account_id` in):
```php
            ':receipt_path'=>$d['receipt_path'] ?? null,
            ':notes'=>$d['notes'] ?: null,
            ':account_id'=>$d['account_id'] ?: null,
```
In `all()` add the join + column:
```php
        $sql = 'SELECT i.*, c.name AS contact_name, p.name AS project_name, cat.name AS category_name, a.name AS account_name
                FROM income i
                LEFT JOIN contacts c   ON c.id = i.contact_id
                LEFT JOIN projects p   ON p.id = i.project_id
                LEFT JOIN categories cat ON cat.id = i.category_id
                LEFT JOIN accounts a   ON a.id = i.account_id
                ' . $where . ' ORDER BY i.date DESC, i.id DESC';
```

- [ ] **Step 5: Update `src/Models/Expense.php`** — same shape.

`create()` INSERT:
```php
        $stmt = $pdo->prepare(
            'INSERT INTO expenses
             (date, contact_id, project_id, category_id, description, amount_tzs, reference, receipt_path, notes, created_by, account_id)
             VALUES
             (:date, :contact_id, :project_id, :category_id, :description, :amount_tzs, :reference, :receipt_path, :notes, :created_by, :account_id)'
        );
```
`update()` SET:
```php
            'UPDATE expenses SET date=:date, contact_id=:contact_id, project_id=:project_id,
             category_id=:category_id, description=:description, amount_tzs=:amount_tzs,
             reference=:reference, notes=:notes, account_id=:account_id WHERE id=:id'
```
`bind()` add:
```php
            ':receipt_path'=>$d['receipt_path'] ?? null,
            ':notes'=>$d['notes'] ?: null,
            ':account_id'=>$d['account_id'] ?: null,
```
`all()` join:
```php
        $sql = 'SELECT e.*, c.name AS contact_name, p.name AS project_name, cat.name AS category_name, a.name AS account_name
                FROM expenses e
                LEFT JOIN contacts c   ON c.id = e.contact_id
                LEFT JOIN projects p   ON p.id = e.project_id
                LEFT JOIN categories cat ON cat.id = e.category_id
                LEFT JOIN accounts a   ON a.id = e.account_id
                ' . $where . ' ORDER BY e.date DESC, e.id DESC';
```

- [ ] **Step 6: Run tests, verify they pass**

Run: `vendor/bin/phpunit tests/IncomeTest.php tests/ExpenseTest.php`
Expected: PASS (all, incl. the new account tests).

- [ ] **Step 7: Commit**

```bash
git add src/Models/Income.php src/Models/Expense.php tests/IncomeTest.php tests/ExpenseTest.php
git commit -m "feat: account_id on income/expense models + joined account name"
```

---

### Task 5: Admin tab bar + Accounts CRUD + nav consolidation

**Files:**
- Create: `views/admin/_tabs.php`, `src/Controllers/AccountController.php`, `views/accounts/index.php`, `views/accounts/form.php`
- Modify: `views/settings/index.php`, `views/categories/index.php`, `views/users/index.php` (prepend tab bar), `views/_shell.php` (nav), `public/index.php` (routes)

**Interfaces:**
- Consumes: `App\Models\Account`, `Auth`, `Activity`, `render()`.
- Produces: `AccountController::index|create|store|edit|update|delete` (all `admin`). The shared `views/admin/_tabs.php` expects a `$activeTab` string in scope (`organisation|accounts|categories|users`).

- [ ] **Step 1: Write `views/admin/_tabs.php`**

```php
<?php
$tabs = [
  'organisation' => ['/settings', 'Organisation'],
  'accounts'     => ['/accounts', 'Accounts'],
  'categories'   => ['/categories', 'Categories'],
  'users'        => ['/users', 'Users'],
];
$active = $activeTab ?? '';
?>
<nav class="admin-tabs" style="display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--border);margin-bottom:18px">
  <?php foreach ($tabs as $key => [$href, $label]): ?>
    <a href="<?= $href ?>" class="admin-tab<?= $key === $active ? ' is-active' : '' ?>"
       style="padding:8px 14px;text-decoration:none;border-radius:8px 8px 0 0;<?= $key === $active ? 'background:var(--accent);color:var(--accent-text);font-weight:600' : 'color:var(--text-secondary)' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</nav>
```

- [ ] **Step 2: Write `src/Controllers/AccountController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Account;
use App\Models\Activity;

final class AccountController
{
    public function index(): string
    {
        Auth::requireRole('admin');
        return render('accounts/index', ['accounts'=>Account::all()], 'Accounts');
    }

    public function create(): string
    {
        Auth::requireRole('admin');
        return render('accounts/form', ['a'=>null, 'error'=>null], 'New account');
    }

    public function store(): string
    {
        Auth::requireRole('admin');
        $error = $this->validate($_POST);
        if ($error) { return render('accounts/form', ['a'=>$_POST, 'error'=>$error], 'New account'); }
        $id = Account::create($this->fields($_POST));
        Activity::log(Auth::user()['id'] ?? null, 'create', 'account', $id, 'Created account ' . trim($_POST['name'] ?? ''));
        header('Location: /accounts'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin');
        $a = Account::find($id);
        if (!$a) { http_response_code(404); return 'Not found'; }
        return render('accounts/form', ['a'=>$a, 'error'=>null], 'Edit account');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin');
        if (!Account::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('accounts/form', ['a'=>array_merge($_POST,['id'=>$id]), 'error'=>$error], 'Edit account'); }
        Account::update($id, $this->fields($_POST) + ['active'=>$_POST['active'] ?? 0]);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'account', $id, 'Updated account');
        header('Location: /accounts'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin');
        Account::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'account', $id, 'Deleted account');
        header('Location: /accounts'); exit;
    }

    private function fields(array $in): array
    {
        $type = in_array($in['type'] ?? '', ['bank','cash','other'], true) ? $in['type'] : 'bank';
        return [
            'name'=>trim($in['name'] ?? ''), 'type'=>$type,
            'opening_balance'=>(float)($in['opening_balance'] ?? 0),
            'opening_balance_date'=>trim($in['opening_balance_date'] ?? '') ?: null,
        ];
    }

    private function validate(array $in): ?string
    {
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        if (($in['opening_balance'] ?? '') !== '' && !is_numeric($in['opening_balance'])) return 'Opening balance must be a number.';
        return null;
    }
}
```

- [ ] **Step 3: Write `views/accounts/index.php`**

```php
<?php $activeTab = 'accounts'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Accounts</h1>
  <a class="btn btn-primary" href="/accounts/new">New account</a>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Name</th><th>Type</th><th>Opening balance (TZS)</th><th>Current balance (TZS)</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($accounts as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e(ucfirst($row['type'])) ?></td>
      <td><?= number_format((float)$row['opening_balance'], 2) ?></td>
      <td><?= number_format(\App\Models\Account::balance((int)$row['id']), 2) ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <a href="/accounts/<?= (int)$row['id'] ?>/edit">Edit</a>
        <form method="post" action="/accounts/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this account? Entries keep their history but lose the link.">
          <button type="submit" class="btn-link-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
```

- [ ] **Step 4: Write `views/accounts/form.php`**

```php
<?php $activeTab = 'accounts'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<?php $isNew = empty($a['id']); ?>
<h1><?= $isNew ? 'New account' : 'Edit account' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/accounts' : '/accounts/' . (int)$a['id'] ?>">
  <label>Name <input name="name" value="<?= e($a['name'] ?? '') ?>" required></label>
  <label>Type
    <select name="type">
      <?php foreach (['bank','cash','other'] as $t): ?>
        <option value="<?= $t ?>" <?= (($a['type'] ?? 'bank') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Opening balance (TZS) <input type="number" step="0.01" name="opening_balance" value="<?= e($a['opening_balance'] ?? '0') ?>"></label>
  <label>Opening balance date <input type="date" name="opening_balance_date" value="<?= e($a['opening_balance_date'] ?? '') ?>"></label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($a['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/accounts" class="btn">Cancel</a>
</form>
```

- [ ] **Step 5: Prepend the tab bar to the three existing admin index views**

At the very top of `views/settings/index.php` add:
```php
<?php $activeTab = 'organisation'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
```
At the very top of `views/categories/index.php` add:
```php
<?php $activeTab = 'categories'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
```
At the very top of `views/users/index.php` add:
```php
<?php $activeTab = 'users'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
```

- [ ] **Step 6: Consolidate the sidebar nav in `views/_shell.php`**

Replace the admin block:
```php
        <?php if (Auth::is('admin')): ?>
          <a href="/categories">Categories</a>
          <a href="/users">Users</a>
          <a href="/settings">Settings</a>
        <?php endif; ?>
```
with a single link (Categories + Users are now tabs):
```php
        <?php if (Auth::is('admin')): ?>
          <a href="/settings">Settings</a>
        <?php endif; ?>
```

- [ ] **Step 7: Add account routes in `public/index.php`** (`use App\Controllers\AccountController;` with the others)

```php
$router->add('GET',  '/accounts',            fn() => (new AccountController())->index());
$router->add('GET',  '/accounts/new',        fn() => (new AccountController())->create());
$router->add('POST', '/accounts',            fn() => (new AccountController())->store());
$router->add('GET',  '/accounts/:id/edit',   fn($p) => (new AccountController())->edit((int)$p['id']));
$router->add('POST', '/accounts/:id',        fn($p) => (new AccountController())->update((int)$p['id']));
$router->add('POST', '/accounts/:id/delete', fn($p) => (new AccountController())->delete((int)$p['id']));
```

- [ ] **Step 8: Verify (lint + e2e)**

```bash
php -l src/Controllers/AccountController.php && php -l views/admin/_tabs.php && php -l views/accounts/index.php && php -l views/accounts/form.php && php -l views/_shell.php && php -l public/index.php
```
Start dev server, log in as admin: `/accounts` shows the tab bar + seeded Bank/Petty cash with current balances; create an account, edit, deactivate. The Settings/Categories/Users pages show the same tab bar and the active tab is highlighted. Sidebar now shows a single "Settings" (no separate Categories/Users). Log in as editor → `/accounts` → 403.

- [ ] **Step 9: Commit**

```bash
git add src/Controllers/AccountController.php views/accounts/ views/admin/ views/settings/index.php views/categories/index.php views/users/index.php views/_shell.php public/index.php
git commit -m "feat: Accounts admin CRUD + consolidated tabbed admin area"
```

---

### Task 6: Transfers controller + views + main nav

**Files:**
- Create: `src/Controllers/TransferController.php`, `views/transfers/index.php`, `views/transfers/form.php`
- Modify: `views/_shell.php` (nav), `public/index.php` (routes)

**Interfaces:**
- Consumes: `App\Models\Transfer`, `App\Models\Account`, `Auth`, `Activity`, `render()`, shared `views/_filters.php` not used here (own minimal filter).
- Produces: `TransferController::index|create|store|edit|update|delete`. index view: all roles; create/store/edit/update/delete: admin/editor.

- [ ] **Step 1: Write `src/Controllers/TransferController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Transfer;
use App\Models\Account;
use App\Models\Activity;

final class TransferController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $f = ['date_from'=>$_GET['date_from'] ?? '', 'date_to'=>$_GET['date_to'] ?? ''];
        return render('transfers/index', ['rows'=>Transfer::all($f), 'f'=>$f], 'Transfers');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('transfers/form', ['t'=>null, 'error'=>null, 'accounts'=>Account::all(true)], 'New transfer');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('transfers/form', ['t'=>$_POST, 'error'=>$error, 'accounts'=>Account::all(true)], 'New transfer'); }
        $d = $this->fields($_POST);
        $d['created_by'] = Auth::user()['id'] ?? null;
        $id = Transfer::create($d);
        Activity::log(Auth::user()['id'] ?? null, 'create', 'transfer', $id, 'Transfer ' . number_format($d['amount_tzs'], 2) . ' TZS');
        header('Location: /transfers'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $t = Transfer::find($id);
        if (!$t) { http_response_code(404); return 'Not found'; }
        return render('transfers/form', ['t'=>$t, 'error'=>null, 'accounts'=>Account::all(true)], 'Edit transfer');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Transfer::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('transfers/form', ['t'=>array_merge($_POST,['id'=>$id]), 'error'=>$error, 'accounts'=>Account::all(true)], 'Edit transfer'); }
        Transfer::update($id, $this->fields($_POST));
        Activity::log(Auth::user()['id'] ?? null, 'update', 'transfer', $id, 'Updated transfer');
        header('Location: /transfers'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Transfer::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'transfer', $id, 'Deleted transfer');
        header('Location: /transfers'); exit;
    }

    private function fields(array $in): array
    {
        return [
            'date'=>$in['date'] ?? date('Y-m-d'),
            'from_account_id'=>$in['from_account_id'] ?? null,
            'to_account_id'=>$in['to_account_id'] ?? null,
            'amount_tzs'=>(float)($in['amount_tzs'] ?? 0),
            'description'=>trim($in['description'] ?? ''),
        ];
    }

    private function validate(array $in): ?string
    {
        if (empty($in['date']) || !\DateTime::createFromFormat('Y-m-d', $in['date'])) return 'A valid date is required.';
        if (empty($in['from_account_id']) || empty($in['to_account_id'])) return 'Both accounts are required.';
        if ((int)$in['from_account_id'] === (int)$in['to_account_id']) return 'From and To must be different accounts.';
        if (!is_numeric($in['amount_tzs'] ?? null) || (float)$in['amount_tzs'] <= 0) return 'Amount must be greater than zero.';
        return null;
    }
}
```

- [ ] **Step 2: Write `views/transfers/index.php`**

```php
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Transfers</h1>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary" href="/transfers/new">New transfer</a>
  <?php endif; ?>
</div>
<form method="get" action="/transfers" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <button class="btn" type="submit">Filter</button>
  <a class="btn" href="/transfers">Clear</a>
</form>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Date</th><th>From</th><th>To</th><th>Amount (TZS)</th><th>Description</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e($row['date']) ?></td>
      <td><?= e($row['from_name']) ?></td>
      <td><?= e($row['to_name']) ?></td>
      <td><?= number_format((float)$row['amount_tzs'], 2) ?></td>
      <td><?= e($row['description']) ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/transfers/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/transfers/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this transfer?">
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

- [ ] **Step 3: Write `views/transfers/form.php`**

```php
<?php $isNew = empty($t['id']); ?>
<h1><?= $isNew ? 'New transfer' : 'Edit transfer' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/transfers' : '/transfers/' . (int)$t['id'] ?>">
  <label>Date <input type="date" name="date" value="<?= e($t['date'] ?? date('Y-m-d')) ?>" required></label>
  <label>From account
    <select name="from_account_id" required>
      <option value="">—</option>
      <?php foreach ($accounts as $acc): ?>
        <option value="<?= (int)$acc['id'] ?>" <?= ((int)($t['from_account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>To account
    <select name="to_account_id" required>
      <option value="">—</option>
      <?php foreach ($accounts as $acc): ?>
        <option value="<?= (int)$acc['id'] ?>" <?= ((int)($t['to_account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Amount (TZS) <input type="number" step="0.01" name="amount_tzs" value="<?= e($t['amount_tzs'] ?? '') ?>" required></label>
  <label>Description <input name="description" value="<?= e($t['description'] ?? '') ?>"></label>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/transfers" class="btn">Cancel</a>
</form>
```

- [ ] **Step 4: Add "Transfers" to the main nav in `views/_shell.php`** (after the Expenses link)

```php
        <a href="/expenses">Expenses</a>
        <a href="/transfers">Transfers</a>
        <a href="/contacts">Contacts</a>
```

- [ ] **Step 5: Add transfer routes in `public/index.php`** (`use App\Controllers\TransferController;`)

```php
$router->add('GET',  '/transfers',            fn() => (new TransferController())->index());
$router->add('GET',  '/transfers/new',        fn() => (new TransferController())->create());
$router->add('POST', '/transfers',            fn() => (new TransferController())->store());
$router->add('GET',  '/transfers/:id/edit',   fn($p) => (new TransferController())->edit((int)$p['id']));
$router->add('POST', '/transfers/:id',        fn($p) => (new TransferController())->update((int)$p['id']));
$router->add('POST', '/transfers/:id/delete', fn($p) => (new TransferController())->delete((int)$p['id']));
```

- [ ] **Step 6: Verify (lint + e2e)**

```bash
php -l src/Controllers/TransferController.php && php -l views/transfers/index.php && php -l views/transfers/form.php && php -l public/index.php
```
Log in as admin: create a transfer Bank→Petty cash 500 → listed; the Accounts page now shows Petty cash balance +500 and Bank −500. From=To rejected; amount ≤ 0 rejected. Viewer: `/transfers` 200 read-only, `POST /transfers` 403.

- [ ] **Step 7: Commit**

```bash
git add src/Controllers/TransferController.php views/transfers/ views/_shell.php public/index.php
git commit -m "feat: Transfers between accounts (action + nav)"
```

---

### Task 7: Account field on income & expense forms (required, default Bank)

**Files:**
- Modify: `src/Controllers/IncomeController.php`, `src/Controllers/ExpenseController.php`, `views/income/form.php`, `views/expenses/form.php`, `views/income/index.php`, `views/expenses/index.php`

**Interfaces:**
- Consumes: `App\Models\Account`.
- Produces: income/expense create/update persist `account_id`; forms show an Account dropdown (default = first active account); index views show an Account column. Validation requires an account.

- [ ] **Step 1: IncomeController — load accounts, default, validate, persist**

In `formData()` add accounts:
```php
    private function formData(?array $row, ?string $error): array
    {
        return [
            'r'=>$row, 'error'=>$error,
            'contacts'=>Contact::all('donor'),
            'projects'=>Project::all(),
            'categories'=>Category::all('income'),
            'accounts'=>\App\Models\Account::all(true),
        ];
    }
```
In `fields()` add `account_id`:
```php
            'reference'=>trim($in['reference'] ?? ''),
            'notes'=>trim($in['notes'] ?? ''),
            'account_id'=>$in['account_id'] ?? null,
```
In `validate()` add (after the date check):
```php
        if (empty($in['account_id'])) return 'An account is required.';
```

- [ ] **Step 2: ExpenseController — same four edits** (mirror of Step 1, `Category::all('expense')` stays as-is)

`formData()` add `'accounts'=>\App\Models\Account::all(true),`; `fields()` add `'account_id'=>$in['account_id'] ?? null,`; `validate()` add `if (empty($in['account_id'])) return 'An account is required.';`.

- [ ] **Step 3: Add the Account select to `views/income/form.php`** (after the Project select, before Description)

```php
  <label>Account
    <select name="account_id" required>
      <option value="">—</option>
      <?php foreach ($accounts as $acc): ?>
        <option value="<?= (int)$acc['id'] ?>" <?= ((int)($r['account_id'] ?? ($accounts[0]['id'] ?? 0)) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
```

- [ ] **Step 4: Add the same Account select to `views/expenses/form.php`** (after the Project select, before Description) — identical markup as Step 3.

- [ ] **Step 5: Add an Account column to the index tables**

In `views/income/index.php` header add `<th>Account</th>` after the Project header, and in the row add after the Project cell:
```php
      <td><?= e($row['account_name']) ?></td>
```
In `views/expenses/index.php` do the same (header `<th>Account</th>` after Project; row `<td><?= e($row['account_name']) ?></td>` after the Project cell).

- [ ] **Step 6: Verify (lint + e2e)**

```bash
php -l src/Controllers/IncomeController.php && php -l src/Controllers/ExpenseController.php && php -l views/income/form.php && php -l views/expenses/form.php && php -l views/income/index.php && php -l views/expenses/index.php
```
Log in as admin: new income with the Account select defaulting to Bank → saves; the income list shows the Account column; the Bank balance on the Accounts page reflects the new income. Submitting income with no account → "An account is required." Same checks for expenses.

- [ ] **Step 7: Commit**

```bash
git add src/Controllers/IncomeController.php src/Controllers/ExpenseController.php views/income/ views/expenses/
git commit -m "feat: required Account field on income/expense (default Bank) + list column"
```

---

### Task 8: Dashboard — balances by account

**Files:**
- Modify: `src/Controllers/DashboardController.php`, `views/dashboard.php`

**Interfaces:**
- Consumes: `App\Models\Account::balancesAll()`.
- Produces: dashboard passes `balances` (current per-account balances, not date-scoped) to the view.

- [ ] **Step 1: Add balances to `DashboardController::index()`** — before the `return render(...)`, add `'balances'=>\App\Models\Account::balancesAll(),` to the data array:

```php
        return render('dashboard', [
            'f'=>$f, 'income'=>$income, 'expense'=>$expense, 'balance'=>$income - $expense,
            'projects'=>$proj, 'activity'=>Activity::recent(10),
            'balances'=>\App\Models\Account::balancesAll(),
        ], 'Dashboard');
```

- [ ] **Step 2: Add a "Balances by account" section to `views/dashboard.php`** (after the KPI cards block, before "By project")

```php
<h2>Balances by account</h2>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Account</th><th>Current balance (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($balances as $b): ?>
    <tr><td><?= e($b['name']) ?></td><td><?= number_format($b['balance'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($balances)): ?><tr><td colspan="2">No accounts yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
```

- [ ] **Step 3: Verify (e2e)**

Log in; the dashboard shows "Balances by account" with each account's current balance. After adding income/expense/transfer, the figures update and reconcile (e.g. Bank opening + income − expenses − transfers out).

- [ ] **Step 4: Commit**

```bash
git add src/Controllers/DashboardController.php views/dashboard.php
git commit -m "feat: dashboard balances by account"
```

---

### Task 9: Excel export — account column + Transfers & By-account sheets

**Files:**
- Modify: `src/Reports/ExcelExport.php`

**Interfaces:**
- Consumes: `Transfer::all()`, `Account::all(true)`, `Account::balance()`.
- Produces: the workbook gains an **Account** column on Income & Expenses, a **Transfers** sheet, and a **By account** sheet (opening, income, expenses, transfers in/out, closing).

- [ ] **Step 1: Add the Account column to the Income sheet** — header and row in `ExcelExport::build()`:

Income header line becomes:
```php
        $s->fromArray(['Date','Donor','Category','Project','Account','Description','Currency','Amount (orig.)','Exchange rate','Amount (TZS)','Reference'], null, 'A1');
```
Income row becomes:
```php
            $s->fromArray([
                $r['date'], $r['contact_name'], $r['category_name'], $r['project_name'], $r['account_name'], $r['description'],
                $r['currency'], (float)$r['amount_original'], (float)$r['exchange_rate'], (float)$r['amount_tzs'], $r['reference'],
            ], null, 'A' . $row++);
```

- [ ] **Step 2: Add the Account column to the Expenses sheet**

Expenses header:
```php
        $s->fromArray(['Date','Vendor','Category','Project','Account','Description','Amount (TZS)','Reference'], null, 'A1');
```
Expenses row:
```php
            $s->fromArray([
                $r['date'], $r['contact_name'], $r['category_name'], $r['project_name'], $r['account_name'], $r['description'],
                (float)$r['amount_tzs'], $r['reference'],
            ], null, 'A' . $row++);
```

- [ ] **Step 3: Add the Transfers + By-account sheets** — at the end of `build()`, before `$book->setActiveSheetIndex(0);`, add:

```php
        // 7. Transfers
        $s = $book->createSheet(); $s->setTitle('Transfers');
        $s->fromArray(['Date','From','To','Amount (TZS)','Description'], null, 'A1');
        $row = 2;
        foreach (\App\Models\Transfer::all($filters) as $t) {
            $s->fromArray([$t['date'], $t['from_name'], $t['to_name'], (float)$t['amount_tzs'], $t['description']], null, 'A' . $row++);
        }

        // 8. By account (opening + movements + closing)
        $s = $book->createSheet(); $s->setTitle('By account');
        $s->fromArray(['Account','Opening (TZS)','Closing (TZS)'], null, 'A1');
        $row = 2;
        foreach (\App\Models\Account::all(true) as $a) {
            $s->fromArray([
                $a['name'], (float)$a['opening_balance'],
                \App\Models\Account::balance((int)$a['id'], $filters['date_to'] ?? null),
            ], null, 'A' . $row++);
        }
```

- [ ] **Step 4: Verify (e2e)**

Log in; `GET /reports/export?date_from=2026-01-01&date_to=2026-12-31` downloads an `.xlsx`. Validate it has 8 sheets including **Transfers** and **By account**, and the Income/Expenses sheets carry the **Account** column:
```bash
unzip -p /tmp/r2.xlsx xl/workbook.xml | grep -oE 'name="[^"]+"'
```
Expected sheet names include Overview, Income, Expenses, Income by category, Expenses by category, By project, Transfers, By account.

- [ ] **Step 5: Run the full suite + commit**

```bash
composer test
git add src/Reports/ExcelExport.php
git commit -m "feat: Excel export — account column + transfers & by-account sheets"
```

---

## Self-Review

**Spec coverage:**
- `accounts` + `transfers` tables, `account_id` columns, idempotent migration, seed, backfill → Task 1. ✓
- Account model + per-account balance (opening + income − expenses ± transfers, optional `asOf`) → Task 2. ✓
- Transfer model, excluded from income/expense totals (separate table; `Income/Expense::totalTzs` untouched) → Task 3. ✓
- `account_id` on income/expense + joined name → Task 4. ✓
- Accounts admin CRUD + consolidated tabbed admin (Organisation·Accounts·Categories·Users) + sidebar consolidation → Task 5. ✓
- Transfers action in main nav, role-guarded → Task 6. ✓
- Required account (default Bank) on income/expense forms + list column → Task 7. ✓
- Dashboard balances by account → Task 8. ✓
- Excel: account column + Transfers + By-account sheets → Task 9. ✓
- Roles: account CRUD admin (Task 5); transfers admin/editor (Task 6); income/expense account select editor/admin (Task 7, inherits existing guards). ✓
- TZS-only, account required, default Bank, transfers separate — all honoured.

**Placeholder scan:** None. All model/controller/view code is complete; migration is concrete and idempotent.

**Type consistency:** `Account::create/all/find/update/delete/balance/balancesAll` and `Transfer::create/all/find/update/delete` are used with identical signatures across tasks. `account_id` key flows through Income/Expense `bind()`; `account_name` join key used in views (Task 7) matches the `all()` aliases (Task 4). `balancesAll()` returns `['id','name','balance']` consumed by Task 8. `_tabs.php` `$activeTab` strings (`organisation/accounts/categories/users`) match the includes in Task 5.
