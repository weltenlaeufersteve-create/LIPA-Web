# LIPA Web — Plan 8: Activities (activity reporting) — Implementation Plan

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** An Activities module — record activities (date, title, description, up to 5 resized photos), link the expenses they incurred (from the activity side), and print an Activity report for a period with photos + per-activity cost.

**Architecture:** New `activities` + `activity_photos` tables and an `expenses.activity_id` column (idempotent migration). A GD-based `ImageStorage` resizes uploads. `Activity` model handles CRUD, photos, and expense linking. `ActivityController` drives the UI; `ActivityReport` + a standalone print view drive the report. Mirrors existing patterns (Account/Project CRUD, ReceiptStorage, the statements).

**Tech Stack:** PHP 8.3, PDO, **GD** (image resize), PHPUnit, vanilla PHP views.

## Global Constraints

- PHP **8.3**; production **MariaDB**, local **MySQL 8.4** — **portable SQL only**; **PDO prepared statements**.
- **Photos:** JPG/PNG only, ≤10 MB upload, **resized via GD to max 1600 px long edge, JPEG ~80%**, stored in `storage/activity_photos/` (outside web root), served via authed route. **Max 5 per activity.**
- **Linking is Activity → Expense:** the expense selector lives on the **Activity** form; there is **no** activity field on the expense form. `expenses.activity_id` nullable, `ON DELETE SET NULL`. One activity → many expenses.
- Roles: Activities view = all (`admin`,`editor`,`viewer`); create/edit/delete = `admin`,`editor`. Activity report = all roles.
- UI English (UK); mobile-first; every write action calls the audit-log helper `App\Models\Activity::log(...)`.

> **Naming clash — resolve up front:** the existing audit-log model is already `App\Models\Activity`. So the new feature's model is named **`App\Models\ActivityItem`** (table `activities`) to avoid colliding with it. Throughout this plan, "Activity model" / "the activity record" means **`App\Models\ActivityItem`**; the audit-log helper stays **`App\Models\Activity::log(...)`**.

- Tests run against `lipa_test` via `Tests\DatabaseTestCase`.

Toolchain (local): prefix shell commands with
`export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`.

---

## File structure

```
db/schema.sql                       (add activities, activity_photos; expenses.activity_id)
bin/migrate.php                     (extend: idempotent create + ALTER)
tests/DatabaseTestCase.php          (truncate the new tables)
src/ImageStorage.php                (new — GD resize + store)
src/Models/ActivityItem.php         (new — CRUD, photos, expense linking, cost)
src/Models/Expense.php              (add availableForActivity())
src/Controllers/ActivityController.php (new)
src/Reports/ActivityReport.php      (new)
src/Controllers/ReportController.php (add activityReport())
views/activities/index.php  form.php (new)
views/reports/activity_report.php   (new standalone print)
views/reports/index.php             (add Activity report form)
views/_shell.php                    (nav: Activities under Projects)
public/index.php                    (routes)
tests/ImageStorageTest.php  ActivityItemTest.php  ActivityReportTest.php  (new)
tests/ExpenseTest.php               (add availableForActivity test)
```

---

### Task 1: Schema + migration

**Files:**
- Modify: `db/schema.sql`, `bin/migrate.php`, `tests/DatabaseTestCase.php`

**Interfaces:**
- Produces: tables `activities` (id,date,title,description,project_id,created_by,created_at),
  `activity_photos` (id,activity_id,filename,created_at), and `expenses.activity_id` — on fresh
  installs (schema.sql) and existing DBs (migrate.php, idempotent).

- [ ] **Step 1: Add the tables to `db/schema.sql`** — insert the `activities` and
  `activity_photos` CREATE blocks **immediately after the `accounts` table** (so `expenses`,
  created later, can FK to `activities`), and add `activity_id` inline to the `expenses` table.

Insert after the `accounts` CREATE TABLE block:
```sql
CREATE TABLE IF NOT EXISTS activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  project_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_creator FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
In the `expenses` CREATE TABLE, add the column (after `category_id INT NULL,`) and the FK
(with the other constraints):
```sql
  account_id INT NULL,
  activity_id INT NULL,
```
```sql
  CONSTRAINT fk_expense_account  FOREIGN KEY (account_id)  REFERENCES accounts(id)   ON DELETE SET NULL,
  CONSTRAINT fk_expense_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE SET NULL,
```

- [ ] **Step 2: Extend `bin/migrate.php`** — append, before the final `echo "migration complete\n";`:

```php
$pdo->exec("CREATE TABLE IF NOT EXISTS activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  project_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_creator FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "activities table ok\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS activity_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "activity_photos table ok\n";

if (!columnExists($pdo, 'expenses', 'activity_id')) {
    $pdo->exec('ALTER TABLE expenses ADD COLUMN activity_id INT NULL,
        ADD CONSTRAINT fk_expense_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE SET NULL');
    echo "expenses.activity_id added\n";
} else { echo "expenses.activity_id exists\n"; }
```

- [ ] **Step 3: Add the new tables to the truncate list in `tests/DatabaseTestCase.php`**

```php
        foreach (['activity_log','transfers','income','expenses','activity_photos','activities','accounts','categories','projects','contacts','settings','users'] as $t) {
```

- [ ] **Step 4: Apply + verify**

```bash
# rebuild test DB from new schema, migrate dev DB
mysql --host=127.0.0.1 --protocol=tcp -uroot -e "DROP DATABASE IF EXISTS lipa_test; CREATE DATABASE lipa_test CHARACTER SET utf8mb4;"
mysql --host=127.0.0.1 --protocol=tcp -uroot lipa_test < db/schema.sql
php bin/migrate.php
mysql --host=127.0.0.1 --protocol=tcp -uroot lipa -e "SHOW TABLES LIKE 'activities'; SHOW TABLES LIKE 'activity_photos'; SHOW COLUMNS FROM expenses LIKE 'activity_id';"
composer test
```
Expected: both tables present, `expenses.activity_id` present, full suite still green. Re-running
`php bin/migrate.php` prints "exists" (idempotent).

- [ ] **Step 5: Commit**

```bash
git add db/schema.sql bin/migrate.php tests/DatabaseTestCase.php
git commit -m "feat: activities + activity_photos schema and expenses.activity_id (migration)"
```

---

### Task 2: ImageStorage (GD resize + store) (TDD)

**Files:**
- Create: `src/ImageStorage.php`
- Test: `tests/ImageStorageTest.php`

**Interfaces:**
- Produces:
  - `App\ImageStorage::DIR` — absolute path to `storage/activity_photos`.
  - `App\ImageStorage::extension(string $filename): string`
  - `App\ImageStorage::validate(array $file): ?string` — JPG/PNG, ≤10 MB, upload OK.
  - `App\ImageStorage::store(array $file, string $prefix): string` — resizes (≤1600 px long edge),
    re-encodes JPEG ~80%, writes to `DIR/{prefix}_{rand}.jpg`, returns the basename.
  - `App\ImageStorage::path(string $basename): string`

- [ ] **Step 1: Write the failing test** `tests/ImageStorageTest.php`

```php
<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\ImageStorage;

final class ImageStorageTest extends TestCase
{
    public function test_validate_accepts_jpg_png_rejects_others(): void
    {
        $ok = ['name'=>'p.jpg','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024];
        $this->assertNull(ImageStorage::validate($ok));
        $this->assertNull(ImageStorage::validate(['name'=>'p.PNG','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024]));
        $this->assertNotNull(ImageStorage::validate(['name'=>'p.gif','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>1024]));
        $this->assertNotNull(ImageStorage::validate(['name'=>'p.jpg','tmp_name'=>'/tmp/x','error'=>UPLOAD_ERR_OK,'size'=>11*1024*1024]));
        $this->assertNotNull(ImageStorage::validate(['name'=>'p.jpg','tmp_name'=>'','error'=>UPLOAD_ERR_NO_FILE,'size'=>0]));
    }

    public function test_store_resizes_large_image_down(): void
    {
        // make a 3000x2000 source JPEG in a temp file
        $tmp = tempnam(sys_get_temp_dir(), 'src') . '.jpg';
        $img = imagecreatetruecolor(3000, 2000);
        imagejpeg($img, $tmp);
        imagedestroy($img);

        $name = ImageStorage::store(['name'=>'photo.jpg','tmp_name'=>$tmp], 'act');
        $path = ImageStorage::path($name);
        $this->assertFileExists($path);
        [$w, $h] = getimagesize($path);
        $this->assertLessThanOrEqual(1600, max($w, $h));
        $this->assertStringEndsWith('.jpg', $name);

        @unlink($tmp); @unlink($path);
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ImageStorageTest.php`
Expected: FAIL — class `App\ImageStorage` not found.

- [ ] **Step 3: Write `src/ImageStorage.php`**

```php
<?php
namespace App;

final class ImageStorage
{
    public const DIR = __DIR__ . '/../storage/activity_photos';
    private const ALLOWED = ['jpg','jpeg','png'];
    private const MAX_BYTES = 10 * 1024 * 1024;
    private const MAX_EDGE = 1600;

    public static function extension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    public static function validate(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return 'Photo upload failed.';
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            return 'Photo must be 10 MB or smaller.';
        }
        if (!in_array(self::extension($file['name'] ?? ''), self::ALLOWED, true)) {
            return 'Photo must be a JPG or PNG.';
        }
        return null;
    }

    public static function store(array $file, string $prefix): string
    {
        if (!is_dir(self::DIR)) { mkdir(self::DIR, 0775, true); }
        $data = file_get_contents($file['tmp_name']);
        $src = imagecreatefromstring($data);
        if ($src === false) { throw new \RuntimeException('Unsupported image'); }

        $srcW = imagesx($src); $srcH = imagesy($src);
        $scale = min(1.0, self::MAX_EDGE / max($srcW, $srcH));
        $dstW = max(1, (int)round($srcW * $scale));
        $dstH = max(1, (int)round($srcH * $scale));

        $dst = imagecreatetruecolor($dstW, $dstH);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white); // flatten any transparency
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        $basename = sprintf('%s_%s.jpg', $prefix, bin2hex(random_bytes(6)));
        imagejpeg($dst, self::DIR . '/' . $basename, 80);
        imagedestroy($src);
        imagedestroy($dst);
        return $basename;
    }

    public static function path(string $basename): string
    {
        return self::DIR . '/' . basename($basename);
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ImageStorageTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Create the storage dir + gitignore**

```bash
mkdir -p storage/activity_photos && touch storage/activity_photos/.gitkeep
printf '\nstorage/activity_photos/*\n!storage/activity_photos/.gitkeep\n' >> .gitignore
```

- [ ] **Step 6: Commit**

```bash
git add src/ImageStorage.php tests/ImageStorageTest.php storage/activity_photos/.gitkeep .gitignore
git commit -m "feat: ImageStorage — GD resize + store for activity photos"
```

---

### Task 3: ActivityItem model (TDD)

**Files:**
- Create: `src/Models/ActivityItem.php`
- Test: `tests/ActivityItemTest.php`

**Interfaces:**
- Produces (`App\Models\ActivityItem`):
  - `create(array $data): int` — keys `date,title,description,project_id,created_by`.
  - `all(array $filters = []): array` — LEFT JOIN `project_name`; ordered `date DESC, id DESC`;
    filters `date_from,date_to,project_id`.
  - `find(int $id): ?array`
  - `update(int $id, array $data): void` — keys `date,title,description,project_id`.
  - `delete(int $id): void`
  - `photos(int $id): array` (rows of activity_photos for the activity, oldest first)
  - `addPhoto(int $id, string $filename): void`
  - `findPhoto(int $photoId): ?array`
  - `deletePhoto(int $photoId): void`
  - `photoCount(int $id): int`
  - `expenses(int $id): array` — linked expenses (joined vendor/category names), `date` order.
  - `cost(int $id): float` — Σ `amount_tzs` of linked expenses.
  - `setExpenses(int $id, array $expenseIds): void` — link the given expenses to this activity and
    unlink any previously-linked expense not in the list. Only links expenses that are currently
    unassigned or already on this activity (never steals).

- [ ] **Step 1: Write the failing test** `tests/ActivityItemTest.php`

```php
<?php
namespace Tests;
use App\Models\ActivityItem;
use App\Models\Expense;
use App\Models\Project;
use App\Database;

final class ActivityItemTest extends DatabaseTestCase
{
    public function test_crud_and_project_join(): void
    {
        $pid = Project::create(['name'=>'Farm','code'=>'','description'=>'']);
        $id = ActivityItem::create(['date'=>'2026-03-01','title'=>'Coffee Farm trip','description'=>'Field visit','project_id'=>$pid,'created_by'=>null]);
        $row = ActivityItem::find($id);
        $this->assertSame('Coffee Farm trip', $row['title']);
        $this->assertSame('Farm', ActivityItem::all()[0]['project_name']);
        ActivityItem::update($id, ['date'=>'2026-03-02','title'=>'Coffee Farm visit','description'=>'x','project_id'=>null]);
        $this->assertSame('Coffee Farm visit', ActivityItem::find($id)['title']);
        ActivityItem::delete($id);
        $this->assertNull(ActivityItem::find($id));
    }

    public function test_photos(): void
    {
        $id = ActivityItem::create(['date'=>'2026-03-01','title'=>'A','description'=>'','project_id'=>null,'created_by'=>null]);
        ActivityItem::addPhoto($id, 'act_aaa.jpg');
        ActivityItem::addPhoto($id, 'act_bbb.jpg');
        $this->assertSame(2, ActivityItem::photoCount($id));
        $photos = ActivityItem::photos($id);
        $this->assertSame('act_aaa.jpg', $photos[0]['filename']);
        ActivityItem::deletePhoto((int)$photos[0]['id']);
        $this->assertSame(1, ActivityItem::photoCount($id));
    }

    public function test_set_expenses_links_unlinks_and_cost(): void
    {
        $a = ActivityItem::create(['date'=>'2026-03-01','title'=>'Trip','description'=>'','project_id'=>null,'created_by'=>null]);
        $base = ['contact_id'=>null,'project_id'=>null,'category_id'=>null,'description'=>'','reference'=>'','notes'=>'','created_by'=>null,'date'=>'2026-03-01'];
        $e1 = Expense::create($base + ['amount_tzs'=>1000]);
        $e2 = Expense::create($base + ['amount_tzs'=>500]);
        $e3 = Expense::create($base + ['amount_tzs'=>200]);

        ActivityItem::setExpenses($a, [$e1, $e2]);
        $this->assertEqualsWithDelta(1500.0, ActivityItem::cost($a), 0.001);
        $this->assertCount(2, ActivityItem::expenses($a));

        // re-set to [e2,e3]: e1 unlinked, e3 linked
        ActivityItem::setExpenses($a, [$e2, $e3]);
        $this->assertEqualsWithDelta(700.0, ActivityItem::cost($a), 0.001);
        $this->assertNull(Expense::find($e1)['activity_id']);
        $this->assertEquals($a, (int)Expense::find($e2)['activity_id']);
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ActivityItemTest.php`
Expected: FAIL — class `App\Models\ActivityItem` not found.

- [ ] **Step 3: Write `src/Models/ActivityItem.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class ActivityItem
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO activities (date, title, description, project_id, created_by)
             VALUES (:date, :title, :descr, :project_id, :created_by)'
        );
        $stmt->execute([
            ':date'=>$data['date'], ':title'=>$data['title'],
            ':descr'=>$data['description'] ?: null,
            ':project_id'=>$data['project_id'] ?: null,
            ':created_by'=>$data['created_by'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE activities SET date=:date, title=:title, description=:descr, project_id=:project_id WHERE id=:id'
        );
        $stmt->execute([
            ':date'=>$data['date'], ':title'=>$data['title'],
            ':descr'=>$data['description'] ?: null,
            ':project_id'=>$data['project_id'] ?: null, ':id'=>$id,
        ]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM activities WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        $cond = []; $params = [];
        if (!empty($filters['date_from'])) { $cond[] = 'a.date >= :date_from'; $params[':date_from'] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $cond[] = 'a.date <= :date_to';   $params[':date_to']   = $filters['date_to']; }
        if (!empty($filters['project_id'])) { $cond[] = 'a.project_id = :project_id'; $params[':project_id'] = (int)$filters['project_id']; }
        $where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';
        $sql = "SELECT a.*, p.name AS project_name
                FROM activities a LEFT JOIN projects p ON p.id = a.project_id
                {$where} ORDER BY a.date DESC, a.id DESC";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM activities WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }

    // ---- photos ----
    public static function photos(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM activity_photos WHERE activity_id = :id ORDER BY id ASC');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetchAll();
    }

    public static function addPhoto(int $id, string $filename): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO activity_photos (activity_id, filename) VALUES (:id, :f)');
        $stmt->execute([':id'=>$id, ':f'=>$filename]);
    }

    public static function findPhoto(int $photoId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM activity_photos WHERE id = :id');
        $stmt->execute([':id'=>$photoId]);
        return $stmt->fetch() ?: null;
    }

    public static function deletePhoto(int $photoId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM activity_photos WHERE id = :id');
        $stmt->execute([':id'=>$photoId]);
    }

    public static function photoCount(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM activity_photos WHERE activity_id = :id');
        $stmt->execute([':id'=>$id]);
        return (int)$stmt->fetchColumn();
    }

    // ---- linked expenses ----
    public static function expenses(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.*, c.name AS contact_name, cat.name AS category_name
             FROM expenses e
             LEFT JOIN contacts c ON c.id = e.contact_id
             LEFT JOIN categories cat ON cat.id = e.category_id
             WHERE e.activity_id = :id ORDER BY e.date, e.id'
        );
        $stmt->execute([':id'=>$id]);
        return $stmt->fetchAll();
    }

    public static function cost(int $id): float
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount_tzs),0) FROM expenses WHERE activity_id = :id');
        $stmt->execute([':id'=>$id]);
        return (float)$stmt->fetchColumn();
    }

    public static function setExpenses(int $id, array $expenseIds): void
    {
        $pdo = Database::pdo();
        $ids = array_values(array_unique(array_map('intval', $expenseIds)));
        // unlink expenses currently on this activity that are no longer selected
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE expenses SET activity_id = NULL WHERE activity_id = ? AND id NOT IN ($ph)");
            $stmt->execute(array_merge([$id], $ids));
            // link selected expenses (only those unassigned or already on this activity)
            $stmt = $pdo->prepare("UPDATE expenses SET activity_id = ? WHERE id IN ($ph) AND (activity_id IS NULL OR activity_id = ?)");
            $stmt->execute(array_merge([$id], $ids, [$id]));
        } else {
            $stmt = $pdo->prepare('UPDATE expenses SET activity_id = NULL WHERE activity_id = ?');
            $stmt->execute([$id]);
        }
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ActivityItemTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/ActivityItem.php tests/ActivityItemTest.php
git commit -m "feat: ActivityItem model (CRUD, photos, expense linking, cost)"
```

---

### Task 4: Expense::availableForActivity (TDD)

**Files:**
- Modify: `src/Models/Expense.php`
- Test: add to `tests/ExpenseTest.php`

**Interfaces:**
- Produces: `App\Models\Expense::availableForActivity(?int $activityId): array` — expenses with
  `activity_id IS NULL` OR `activity_id = $activityId`, joined `category_name`, newest first
  (for the activity form's expense picker).

- [ ] **Step 1: Add a failing test to `tests/ExpenseTest.php`** (new method)

```php
    public function test_available_for_activity(): void
    {
        $base = ['contact_id'=>null,'project_id'=>null,'category_id'=>null,'description'=>'','reference'=>'','notes'=>'','created_by'=>null,'date'=>'2026-03-01'];
        $free = Expense::create($base + ['amount_tzs'=>100]);
        $mine = Expense::create($base + ['amount_tzs'=>200]);
        $other = Expense::create($base + ['amount_tzs'=>300]);
        $pdo = \App\Database::pdo();
        $pdo->exec("INSERT INTO activities (date,title) VALUES ('2026-03-01','A')");
        $aid = (int)$pdo->lastInsertId();
        $pdo->exec("INSERT INTO activities (date,title) VALUES ('2026-03-01','B')");
        $other_a = (int)$pdo->lastInsertId();
        $pdo->exec("UPDATE expenses SET activity_id = $aid WHERE id = $mine");
        $pdo->exec("UPDATE expenses SET activity_id = $other_a WHERE id = $other");

        $ids = array_map(fn($r) => (int)$r['id'], Expense::availableForActivity($aid));
        $this->assertContains($free, $ids);   // unassigned
        $this->assertContains($mine, $ids);   // already on this activity
        $this->assertNotContains($other, $ids); // on a different activity
    }
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ExpenseTest.php`
Expected: FAIL — `availableForActivity` not found.

- [ ] **Step 3: Add the method to `src/Models/Expense.php`** (before the final `delete()`)

```php
    public static function availableForActivity(?int $activityId): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT e.*, cat.name AS category_name
             FROM expenses e LEFT JOIN categories cat ON cat.id = e.category_id
             WHERE e.activity_id IS NULL OR e.activity_id = :aid
             ORDER BY e.date DESC, e.id DESC'
        );
        $stmt->bindValue(':aid', (int)$activityId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ExpenseTest.php`
Expected: PASS (all expense tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/Expense.php tests/ExpenseTest.php
git commit -m "feat: Expense::availableForActivity for the activity expense picker"
```

---

### Task 5: Activity CRUD controller + views + nav + routes

**Files:**
- Create: `src/Controllers/ActivityController.php`, `views/activities/index.php`, `views/activities/form.php`
- Modify: `views/_shell.php` (nav), `public/index.php` (routes)

**Interfaces:**
- Consumes: `ActivityItem`, `Expense`, `Project`, `ImageStorage`, `Auth`, `Activity` (audit log), `render()`.
- Produces: `ActivityController::index|create|store|edit|update|delete|photo|deletePhoto`. View all roles;
  write (`store/update/delete/deletePhoto/create-form`) = admin/editor. Photos handled on store/update
  (≤5 total); expense links via `ActivityItem::setExpenses`.

- [ ] **Step 1: Write `src/Controllers/ActivityController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\ImageStorage;
use App\Models\ActivityItem;
use App\Models\Expense;
use App\Models\Project;
use App\Models\Activity; // audit log

final class ActivityController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $rows = ActivityItem::all();
        return render('activities/index', ['rows'=>$rows], 'Activities');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('activities/form', $this->formData(null), 'New activity');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('activities/form', $this->formData(null, $error), 'New activity'); }
        $id = ActivityItem::create([
            'date'=>$_POST['date'], 'title'=>trim($_POST['title']),
            'description'=>trim($_POST['description'] ?? ''),
            'project_id'=>$_POST['project_id'] ?? null,
            'created_by'=>Auth::user()['id'] ?? null,
        ]);
        ActivityItem::setExpenses($id, $_POST['expense_ids'] ?? []);
        $this->storePhotos($id);
        Activity::log(Auth::user()['id'] ?? null, 'create', 'activity', $id, 'Created activity ' . trim($_POST['title'] ?? ''));
        header('Location: /activities'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $a = ActivityItem::find($id);
        if (!$a) { http_response_code(404); return 'Not found'; }
        return render('activities/form', $this->formData($a), 'Edit activity');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!ActivityItem::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('activities/form', $this->formData(array_merge($_POST,['id'=>$id]), $error), 'Edit activity'); }
        ActivityItem::update($id, [
            'date'=>$_POST['date'], 'title'=>trim($_POST['title']),
            'description'=>trim($_POST['description'] ?? ''),
            'project_id'=>$_POST['project_id'] ?? null,
        ]);
        ActivityItem::setExpenses($id, $_POST['expense_ids'] ?? []);
        $this->storePhotos($id);
        Activity::log(Auth::user()['id'] ?? null, 'update', 'activity', $id, 'Updated activity');
        header('Location: /activities'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        foreach (ActivityItem::photos($id) as $p) { @unlink(ImageStorage::path($p['filename'])); }
        ActivityItem::delete($id);
        Activity::log(Auth::user()['id'] ?? null, 'delete', 'activity', $id, 'Deleted activity');
        header('Location: /activities'); exit;
    }

    public function photo(int $id, int $photoId): never
    {
        Auth::requireRole('admin','editor','viewer');
        $p = ActivityItem::findPhoto($photoId);
        if (!$p || (int)$p['activity_id'] !== $id) { http_response_code(404); echo 'Not found'; exit; }
        $path = ImageStorage::path($p['filename']);
        if (!is_file($path)) { http_response_code(404); echo 'Not found'; exit; }
        header('Content-Type: image/jpeg');
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        readfile($path); exit;
    }

    public function deletePhoto(int $id, int $photoId): never
    {
        Auth::requireRole('admin','editor');
        $p = ActivityItem::findPhoto($photoId);
        if ($p && (int)$p['activity_id'] === $id) {
            @unlink(ImageStorage::path($p['filename']));
            ActivityItem::deletePhoto($photoId);
        }
        header('Location: /activities/' . $id . '/edit'); exit;
    }

    private function storePhotos(int $id): void
    {
        if (empty($_FILES['photos']['name'][0])) { return; }
        $slots = 5 - ActivityItem::photoCount($id);
        if ($slots <= 0) { return; }
        $files = $_FILES['photos'];
        $n = count($files['name']);
        for ($i = 0; $i < $n && $slots > 0; $i++) {
            $one = ['name'=>$files['name'][$i], 'tmp_name'=>$files['tmp_name'][$i],
                    'error'=>$files['error'][$i], 'size'=>$files['size'][$i]];
            if (empty($one['name']) || ImageStorage::validate($one) !== null) { continue; }
            $name = ImageStorage::store($one, 'act' . $id);
            ActivityItem::addPhoto($id, $name);
            $slots--;
        }
    }

    private function formData(?array $row, ?string $error = null): array
    {
        $id = isset($row['id']) ? (int)$row['id'] : 0;
        return [
            'a'=>$row, 'error'=>$error,
            'projects'=>Project::all(true),
            'photos'=> $id ? ActivityItem::photos($id) : [],
            'available'=> Expense::availableForActivity($id ?: null),
            'linked'=> $id ? array_map(fn($e)=>(int)$e['id'], ActivityItem::expenses($id)) : [],
        ];
    }

    private function validate(array $in): ?string
    {
        if (empty($in['date']) || !\DateTime::createFromFormat('Y-m-d', $in['date'])) return 'A valid date is required.';
        if (trim($in['title'] ?? '') === '') return 'Title is required.';
        return null;
    }
}
```

- [ ] **Step 2: Write `views/activities/index.php`**

```php
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Activities</h1>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary" href="/activities/new">New activity</a>
  <?php endif; ?>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Date</th><th>Title</th><th>Project</th><th>Photos</th><th>Cost (TZS)</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e($row['date']) ?></td>
      <td><?= e($row['title']) ?></td>
      <td><?= e($row['project_name']) ?></td>
      <td><?= App\Models\ActivityItem::photoCount((int)$row['id']) ?></td>
      <td><?= number_format(App\Models\ActivityItem::cost((int)$row['id']), 2) ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/activities/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/activities/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this activity?">
            <button type="submit" class="btn-link-danger">Delete</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($rows)): ?><tr><td colspan="6">No activities yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
```

- [ ] **Step 3: Write `views/activities/form.php`**

```php
<?php $isNew = empty($a['id']); ?>
<h1><?= $isNew ? 'New activity' : 'Edit activity' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= $isNew ? '/activities' : '/activities/' . (int)$a['id'] ?>">
  <label>Date <input type="date" name="date" value="<?= e($a['date'] ?? date('Y-m-d')) ?>" required></label>
  <label>Title <input name="title" value="<?= e($a['title'] ?? '') ?>" required></label>
  <label>Description <textarea name="description" rows="4"><?= e($a['description'] ?? '') ?></textarea></label>
  <label>Project
    <select name="project_id">
      <option value="">—</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)($a['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <h3>Photos (max 5, JPG/PNG)</h3>
  <?php if (!empty($photos)): ?>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:8px">
      <?php foreach ($photos as $ph): ?>
        <div style="text-align:center">
          <img src="/activities/<?= (int)$a['id'] ?>/photo/<?= (int)$ph['id'] ?>" alt="" style="max-height:90px;border-radius:6px;border:1px solid var(--border)">
          <div><button type="submit" formaction="/activities/<?= (int)$a['id'] ?>/photo/<?= (int)$ph['id'] ?>/delete" formmethod="post" class="btn-link-danger" data-confirm="Delete this photo?">Delete</button></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <label>Add photos <input type="file" name="photos[]" accept=".jpg,.jpeg,.png" multiple></label>
  <small>Large photos are resized automatically. <?= !empty($a['id']) ? (5 - count($photos)) . ' slot(s) left.' : 'Up to 5.' ?></small>

  <h3>Linked expenses</h3>
  <p><small>Tick the expenses that belong to this activity (unassigned expenses + ones already on it).</small></p>
  <div class="table-wrap" style="max-height:260px;overflow:auto">
  <table class="data-table">
    <thead><tr><th></th><th>Date</th><th>Category</th><th>Description</th><th>Amount (TZS)</th></tr></thead>
    <tbody>
    <?php foreach ($available as $ex): ?>
      <tr>
        <td><input type="checkbox" name="expense_ids[]" value="<?= (int)$ex['id'] ?>" <?= in_array((int)$ex['id'], $linked, true) ? 'checked' : '' ?>></td>
        <td><?= e($ex['date']) ?></td>
        <td><?= e($ex['category_name']) ?></td>
        <td><?= e($ex['description']) ?></td>
        <td><?= number_format((float)$ex['amount_tzs'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($available)): ?><tr><td colspan="5">No expenses available to link.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>

  <p style="margin-top:14px">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="/activities" class="btn">Cancel</a>
  </p>
</form>
```

> Note: the photo "Delete" buttons use `formaction`/`formmethod` so they post to the
> delete-photo route from within the same form without nested forms. The main submit saves
> the activity (incl. new photo uploads + checked expenses).

- [ ] **Step 4: Add "Activities" to the nav in `views/_shell.php`** (after Projects, inside the second nav-group)

```php
          <?php if (Auth::is('admin','editor')): ?><a href="/projects">Projects</a><?php endif; ?>
          <a href="/activities">Activities</a>
          <a href="/reports">Reports</a>
```

- [ ] **Step 5: Add routes in `public/index.php`** (`use App\Controllers\ActivityController;`)

```php
$router->add('GET',  '/activities',                       fn() => (new ActivityController())->index());
$router->add('GET',  '/activities/new',                   fn() => (new ActivityController())->create());
$router->add('POST', '/activities',                       fn() => (new ActivityController())->store());
$router->add('GET',  '/activities/:id/edit',              fn($p) => (new ActivityController())->edit((int)$p['id']));
$router->add('POST', '/activities/:id',                   fn($p) => (new ActivityController())->update((int)$p['id']));
$router->add('POST', '/activities/:id/delete',            fn($p) => (new ActivityController())->delete((int)$p['id']));
$router->add('GET',  '/activities/:id/photo/:pid',        fn($p) => (new ActivityController())->photo((int)$p['id'], (int)$p['pid']));
$router->add('POST', '/activities/:id/photo/:pid/delete', fn($p) => (new ActivityController())->deletePhoto((int)$p['id'], (int)$p['pid']));
```

- [ ] **Step 6: Verify (lint + e2e)**

```bash
php -l src/Controllers/ActivityController.php && php -l views/activities/index.php && php -l views/activities/form.php && php -l views/_shell.php && php -l public/index.php
```
Start the dev server, log in as admin: create an activity (title + date), upload 1–2 photos
(use `curl -F "photos[]=@img.jpg"`), tick an expense → save. Confirm: it appears in the list with
the photo count + cost; the edit page shows the photos (each served via the photo route) and the
expense ticked; deleting a photo removes it. Log in as viewer → `/activities` 200 read-only;
`POST /activities` 403.

- [ ] **Step 7: Commit**

```bash
git add src/Controllers/ActivityController.php views/activities/ views/_shell.php public/index.php
git commit -m "feat: Activities CRUD (photos + expense linking) + nav"
```

---

### Task 6: Activity report (builder + print view + Reports form + route)

**Files:**
- Create: `src/Reports/ActivityReport.php`, `views/reports/activity_report.php`
- Modify: `src/Controllers/ReportController.php` (add `activityReport()`), `views/reports/index.php`,
  `public/index.php` (route)
- Test: `tests/ActivityReportTest.php`

**Interfaces:**
- Produces:
  - `App\Reports\ActivityReport::build(string $from, string $to, ?int $projectId = null): array` →
    `['from','to','activities'=>[ ['activity'=>row,'photos'=>[],'expenses'=>[],'cost'=>float], … ],'grand_total'=>float]`.
  - `ReportController::activityReport()` → standalone `views/reports/activity_report.php`.

- [ ] **Step 1: Write the failing test** `tests/ActivityReportTest.php`

```php
<?php
namespace Tests;
use App\Reports\ActivityReport;
use App\Models\ActivityItem;
use App\Models\Expense;

final class ActivityReportTest extends DatabaseTestCase
{
    public function test_build_groups_activities_with_cost_and_grand_total(): void
    {
        $base = ['contact_id'=>null,'project_id'=>null,'category_id'=>null,'description'=>'','reference'=>'','notes'=>'','created_by'=>null];
        $a1 = ActivityItem::create(['date'=>'2026-02-05','title'=>'Trip','description'=>'','project_id'=>null,'created_by'=>null]);
        $e1 = Expense::create($base + ['date'=>'2026-02-05','amount_tzs'=>1000]);
        $e2 = Expense::create($base + ['date'=>'2026-02-06','amount_tzs'=>500]);
        ActivityItem::setExpenses($a1, [$e1, $e2]);
        $a2 = ActivityItem::create(['date'=>'2026-02-20','title'=>'Workshop','description'=>'','project_id'=>null,'created_by'=>null]);
        $e3 = Expense::create($base + ['date'=>'2026-02-20','amount_tzs'=>700]);
        ActivityItem::setExpenses($a2, [$e3]);
        // out of period -> excluded
        ActivityItem::create(['date'=>'2026-03-10','title'=>'Later','description'=>'','project_id'=>null,'created_by'=>null]);

        $d = ActivityReport::build('2026-02-01', '2026-02-28');
        $this->assertCount(2, $d['activities']);
        $this->assertEqualsWithDelta(1500.0, $d['activities'][0]['cost'] + $d['activities'][1]['cost'] - 700.0, 0.001); // sanity
        $this->assertEqualsWithDelta(2200.0, $d['grand_total'], 0.001);
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ActivityReportTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `src/Reports/ActivityReport.php`**

```php
<?php
namespace App\Reports;

use App\Models\ActivityItem;

final class ActivityReport
{
    public static function build(string $from, string $to, ?int $projectId = null): array
    {
        $filters = ['date_from' => $from, 'date_to' => $to];
        if ($projectId) { $filters['project_id'] = $projectId; }

        $activities = [];
        $grand = 0.0;
        foreach (ActivityItem::all($filters) as $a) {
            $id = (int)$a['id'];
            $cost = ActivityItem::cost($id);
            $grand += $cost;
            $activities[] = [
                'activity' => $a,
                'photos'   => ActivityItem::photos($id),
                'expenses' => ActivityItem::expenses($id),
                'cost'     => $cost,
            ];
        }
        return ['from' => $from, 'to' => $to, 'activities' => $activities, 'grand_total' => round($grand, 2)];
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ActivityReportTest.php`
Expected: PASS.

- [ ] **Step 5: Add `activityReport()` to `src/Controllers/ReportController.php`** (after `orgStatement()`)

```php
    public function activityReport(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';
        if (!\DateTime::createFromFormat('Y-m-d', $from) || !\DateTime::createFromFormat('Y-m-d', $to)) {
            return '<p style="font-family:sans-serif;padding:24px">Please choose valid dates. <a href="/reports">Back to Reports</a>.</p>';
        }
        $projectId = (int)($_GET['project_id'] ?? 0) ?: null;
        $d = \App\Reports\ActivityReport::build($from, $to, $projectId);
        $s = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/reports/activity_report.php';
        return ob_get_clean();
    }
```

- [ ] **Step 6: Write `views/reports/activity_report.php`** (standalone print; `$d`, `$s` in scope)

```php
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activity Report — <?= e($s['org_name'] ?? 'Organisation') ?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:32px;max-width:900px;}
  h1{font-size:1.4rem;margin:0 0 2px;} h2{font-size:1.1rem;margin:20px 0 4px;} h3{font-size:1rem;margin:16px 0 2px;}
  .muted{color:#555;font-size:.9rem;}
  table{width:100%;border-collapse:collapse;margin:6px 0;}
  th,td{border-bottom:1px solid #ddd;padding:5px 8px;text-align:left;font-size:.86rem;}
  th{background:#f0f0f0;}
  .num{text-align:right;}
  .actions{margin:0 0 18px;}
  .btn{padding:8px 14px;border:1px solid #ccc;border-radius:6px;background:#f5f5f5;cursor:pointer;text-decoration:none;color:#111;font-size:.9rem;}
  .activity{border-top:2px solid #333;padding-top:10px;margin-top:18px;}
  .photos{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0;}
  .photos img{max-height:150px;border:1px solid #ccc;border-radius:4px;}
  @page { margin: 14mm; }
  @media print {
    .actions{display:none;} body{padding:0;max-width:none;}
    thead{display:table-header-group;}
    tr{break-inside:avoid;page-break-inside:avoid;}
    h2,h3{break-after:avoid;page-break-after:avoid;}
    .activity{break-inside:avoid;page-break-inside:avoid;}
  }
</style>
</head>
<body>
<div class="actions">
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
  <a class="btn" href="/reports">Back</a>
</div>

<h1><?= e($s['org_name'] ?? 'Organisation') ?></h1>
<?php if (!empty($s['tax_id']) || !empty($s['ngo_number'])): ?>
  <div class="muted">
    <?php if (!empty($s['tax_id'])): ?>Tax ID: <?= e($s['tax_id']) ?><?php endif; ?>
    <?php if (!empty($s['tax_id']) && !empty($s['ngo_number'])): ?> &middot; <?php endif; ?>
    <?php if (!empty($s['ngo_number'])): ?>Reg. No: <?= e($s['ngo_number']) ?><?php endif; ?>
  </div>
<?php endif; ?>

<h2>Activity Report</h2>
<p class="muted">Period: <?= e($d['from']) ?> to <?= e($d['to']) ?> &middot; Currency: TZS</p>

<?php foreach ($d['activities'] as $item): $a = $item['activity']; ?>
  <div class="activity">
    <h3><?= e($a['date']) ?> — <?= e($a['title']) ?><?php if (!empty($a['project_name'])): ?> <span class="muted">(<?= e($a['project_name']) ?>)</span><?php endif; ?></h3>
    <?php if (!empty($a['description'])): ?><p><?= nl2br(e($a['description'])) ?></p><?php endif; ?>
    <?php if (!empty($item['photos'])): ?>
      <div class="photos">
        <?php foreach ($item['photos'] as $ph): ?>
          <img src="/activities/<?= (int)$a['id'] ?>/photo/<?= (int)$ph['id'] ?>" alt="">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($item['expenses'])): ?>
      <table>
        <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th>Description</th><th class="num">Amount (TZS)</th></tr></thead>
        <tbody>
        <?php foreach ($item['expenses'] as $ex): ?>
          <tr><td><?= e($ex['date']) ?></td><td><?= e($ex['contact_name'] ?? '') ?></td>
            <td><?= e($ex['category_name'] ?? '') ?></td><td><?= e($ex['description'] ?? '') ?></td>
            <td class="num"><?= number_format((float)$ex['amount_tzs'], 2) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <p class="num"><strong>Activity cost (TZS): <?= number_format($item['cost'], 2) ?></strong></p>
  </div>
<?php endforeach; ?>
<?php if (empty($d['activities'])): ?><p>No activities in this period.</p><?php endif; ?>

<p style="border-top:2px solid #333;padding-top:8px;margin-top:18px" class="num"><strong>Grand total of activity costs (TZS): <?= number_format($d['grand_total'], 2) ?></strong></p>
<p class="muted">Generated <?= date('Y-m-d H:i') ?> &middot; LIPA</p>
</body>
</html>
```

- [ ] **Step 7: Add the route + the Reports form**

In `public/index.php`:
```php
$router->add('GET', '/reports/activity-report', fn() => (new ReportController())->activityReport());
```
Append to `views/reports/index.php` (after the Organisation section, before "Project / donor statement", or at the end):
```php
<h2 style="margin-top:30px">Activity report</h2>
<p>A printable report of activities for a period — descriptions, photos, and the expenses each one incurred.</p>
<form method="get" action="/reports/activity-report" target="_blank" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">Project
    <select name="project_id">
      <option value="">All</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="btn btn-primary" type="submit">Open report</button>
</form>
```

- [ ] **Step 8: Verify (lint + e2e + full suite)**

```bash
php -l src/Reports/ActivityReport.php && php -l views/reports/activity_report.php && php -l src/Controllers/ReportController.php && php -l views/reports/index.php && php -l public/index.php
```
Log in; Reports shows the **Activity report** form. With a seeded activity + photos + linked
expenses, `GET /reports/activity-report?date_from=…&date_to=…` renders HTTP 200 with the
activity block, the photos (`<img>` via the photo route), the expense table + activity cost, and
a grand total. `composer test` → all green.

- [ ] **Step 9: Commit**

```bash
git add src/Reports/ActivityReport.php views/reports/activity_report.php src/Controllers/ReportController.php views/reports/index.php public/index.php
git commit -m "feat: Activity report (printable, photos + expenses + costs)"
```

---

## Self-Review

**Spec coverage:**
- `activities` + `activity_photos` tables + `expenses.activity_id` (idempotent migration) → Task 1. ✓
- GD resize on upload, ≤1600 px JPEG, storage outside webroot, ≤10 MB, JPG/PNG → Task 2. ✓
- Max 5 photos per activity, deletable, served via authed route → Tasks 3 (count/CRUD), 5 (enforce + serve). ✓
- Activity → Expense linking from the activity side (picker on the activity form; no expense-side field) → Tasks 3 (`setExpenses`), 4 (`availableForActivity`), 5 (form). ✓
- Nav under Projects; view all / edit editor+admin → Task 5. ✓
- Activity report (period, photos, expenses, per-activity cost, grand total, print + page breaks) → Task 6. ✓
- Out of scope (videos, reorder, captions, expense-side field) — honoured.

**Naming clash:** the new model is `App\Models\ActivityItem` (table `activities`); the audit-log
model stays `App\Models\Activity`. Both are used in `ActivityController` (aliased only by class
name, no conflict since they are distinct classes). Verified all references say `ActivityItem`
except the audit `Activity::log(...)`.

**Placeholder scan:** none. All model/controller/view/report code is complete.

**Type consistency:** `ActivityItem` method names (`create/all/find/update/delete/photos/addPhoto/
findPhoto/deletePhoto/photoCount/expenses/cost/setExpenses`) match call sites in the controller,
report, and views. `ImageStorage::validate/store/path/extension/DIR` consistent. `ActivityReport::
build` returns `activities[].{activity,photos,expenses,cost}` + `grand_total`, consumed by the
print view. Routes reference controller methods that exist (`photo`, `deletePhoto`, `activityReport`).
