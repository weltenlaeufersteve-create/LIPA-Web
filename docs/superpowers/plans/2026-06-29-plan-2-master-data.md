# LIPA Web — Plan 2: Master Data — Implementation Plan

> **For agentic workers:** Implement task-by-task with TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Add CRUD for the three master-data entities — Contacts (donors + vendors), Projects, and Categories — plus seed the starter category lists, following the patterns established in Plan 1.

**Architecture:** Same lean plain-PHP stack. Each entity gets a `Model` (PDO, TDD-tested) and a `Controller` (role-guarded, server-rendered views) wired into `public/index.php`. Reuses `render()`, `e()`, `Auth`, `Database`, and `app.css` from Plan 1.

**Tech Stack:** PHP 8.3, MariaDB/MySQL (PDO), PHPUnit, vanilla PHP views.

## Global Constraints

- PHP **8.3** locally; production **MariaDB**, local **MySQL 8.4** — **portable SQL only**.
- All SQL uses **PDO prepared statements**.
- UI language **English (UK)**.
- **Mobile-first responsive**: list tables wrapped in `<div class="table-wrap">`; use existing `app.css` classes (`btn`, `btn-primary`, `data-table`, `alert-error`); no inline layout beyond the small `page-header` flex already used in Plan 1.
- Roles enforced **server-side** on every action:
  - **Contacts** — view (index/edit form render) allowed for `admin`, `editor`, `viewer`; create/store/update/delete require `admin`, `editor`.
  - **Projects** — all actions require `admin`, `editor` (viewer has no access; the nav already hides it).
  - **Categories** — all actions require `admin`.
- Never hardcode colours; use existing themed classes.
- Tests run against the `lipa_test` database via the `Tests\DatabaseTestCase` base class.

---

## File structure (created across this plan)

```
src/Models/Contact.php        Category.php        Project.php
src/Controllers/ContactController.php  ProjectController.php  CategoryController.php
views/contacts/index.php      contacts/form.php
views/projects/index.php      projects/form.php
views/categories/index.php    categories/form.php
tests/ContactTest.php  ProjectTest.php  CategoryTest.php
db/seed.sql                   (extended with starter categories)
public/index.php              (routes added)
```

Toolchain note (local): prefix shell commands with
`export PATH="$PATH:/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64:/c/laragon/bin/composer:/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin"`.

---

### Task 1: Contact model (TDD)

**Files:**
- Create: `src/Models/Contact.php`
- Test: `tests/ContactTest.php`

**Interfaces:**
- Consumes: `App\Database::pdo()`.
- Produces:
  - `App\Models\Contact::create(array $data): int` — keys `type,name,email,phone,address,notes`; returns id.
  - `App\Models\Contact::all(?string $type = null): array` — all, or filtered by `type` (`donor`/`vendor`), ordered by name.
  - `App\Models\Contact::find(int $id): ?array`
  - `App\Models\Contact::update(int $id, array $data): void` — keys `type,name,email,phone,address,notes,active`.
  - `App\Models\Contact::delete(int $id): void`

- [ ] **Step 1: Write the failing test** `tests/ContactTest.php`

```php
<?php
namespace Tests;
use App\Models\Contact;

final class ContactTest extends DatabaseTestCase
{
    public function test_create_and_find(): void
    {
        $id = Contact::create(['type'=>'donor','name'=>'Global Fund','email'=>'g@f.org','phone'=>'','address'=>'','notes'=>'']);
        $this->assertGreaterThan(0, $id);
        $row = Contact::find($id);
        $this->assertSame('Global Fund', $row['name']);
        $this->assertSame('donor', $row['type']);
        $this->assertSame(1, (int)$row['active']);
    }

    public function test_all_filters_by_type(): void
    {
        Contact::create(['type'=>'donor','name'=>'Donor A','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        Contact::create(['type'=>'vendor','name'=>'Vendor B','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        $this->assertCount(2, Contact::all());
        $this->assertCount(1, Contact::all('donor'));
        $this->assertSame('Vendor B', Contact::all('vendor')[0]['name']);
    }

    public function test_update_and_delete(): void
    {
        $id = Contact::create(['type'=>'vendor','name'=>'Old','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        Contact::update($id, ['type'=>'vendor','name'=>'New','email'=>'n@x.org','phone'=>'123','address'=>'Road','notes'=>'hi','active'=>0]);
        $row = Contact::find($id);
        $this->assertSame('New', $row['name']);
        $this->assertSame('n@x.org', $row['email']);
        $this->assertSame(0, (int)$row['active']);
        Contact::delete($id);
        $this->assertNull(Contact::find($id));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ContactTest.php`
Expected: FAIL — class `App\Models\Contact` not found.

- [ ] **Step 3: Write `src/Models/Contact.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class Contact
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO contacts (type, name, email, phone, address, notes, active)
             VALUES (:type, :name, :email, :phone, :address, :notes, 1)'
        );
        $stmt->execute([
            ':type'=>$data['type'], ':name'=>$data['name'],
            ':email'=>$data['email'] ?: null, ':phone'=>$data['phone'] ?: null,
            ':address'=>$data['address'] ?: null, ':notes'=>$data['notes'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function all(?string $type = null): array
    {
        $pdo = Database::pdo();
        if ($type !== null) {
            $stmt = $pdo->prepare('SELECT * FROM contacts WHERE type = :type ORDER BY name');
            $stmt->execute([':type'=>$type]);
            return $stmt->fetchAll();
        }
        return $pdo->query('SELECT * FROM contacts ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM contacts WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE contacts SET type=:type, name=:name, email=:email, phone=:phone,
             address=:address, notes=:notes, active=:active WHERE id=:id'
        );
        $stmt->execute([
            ':type'=>$data['type'], ':name'=>$data['name'],
            ':email'=>$data['email'] ?: null, ':phone'=>$data['phone'] ?: null,
            ':address'=>$data['address'] ?: null, ':notes'=>$data['notes'] ?: null,
            ':active'=>(int)$data['active'], ':id'=>$id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM contacts WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ContactTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/Contact.php tests/ContactTest.php
git commit -m "feat: Contact model (donors + vendors)"
```

---

### Task 2: Contacts CRUD (controller + views + routes)

**Files:**
- Create: `src/Controllers/ContactController.php`
- Create: `views/contacts/index.php`, `views/contacts/form.php`
- Modify: `public/index.php` (add contact routes)

**Interfaces:**
- Consumes: `App\Models\Contact`, `App\Auth`, `render()`.
- Produces: `ContactController::index|create|store|edit|update|delete`. `index` accepts an optional `?type=donor|vendor` query filter.

- [ ] **Step 1: Write `src/Controllers/ContactController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Contact;

final class ContactController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $type = $_GET['type'] ?? null;
        if (!in_array($type, ['donor','vendor'], true)) { $type = null; }
        return render('contacts/index', ['contacts'=>Contact::all($type), 'type'=>$type], 'Contacts');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('contacts/form', ['c'=>null, 'error'=>null], 'New contact');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('contacts/form', ['c'=>$_POST, 'error'=>$error], 'New contact'); }
        Contact::create($this->fields($_POST));
        header('Location: /contacts'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $c = Contact::find($id);
        if (!$c) { http_response_code(404); return 'Not found'; }
        return render('contacts/form', ['c'=>$c, 'error'=>null], 'Edit contact');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Contact::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('contacts/form', ['c'=>array_merge($_POST,['id'=>$id]), 'error'=>$error], 'Edit contact'); }
        Contact::update($id, $this->fields($_POST) + ['active'=>$_POST['active'] ?? 0]);
        header('Location: /contacts'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Contact::delete($id);
        header('Location: /contacts'); exit;
    }

    private function fields(array $in): array
    {
        return [
            'type'=>$in['type'] ?? 'donor', 'name'=>trim($in['name'] ?? ''),
            'email'=>trim($in['email'] ?? ''), 'phone'=>trim($in['phone'] ?? ''),
            'address'=>trim($in['address'] ?? ''), 'notes'=>trim($in['notes'] ?? ''),
        ];
    }

    private function validate(array $in): ?string
    {
        if (!in_array($in['type'] ?? '', ['donor','vendor'], true)) return 'Type is invalid.';
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        if (($in['email'] ?? '') !== '' && !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) return 'Email is invalid.';
        return null;
    }
}
```

- [ ] **Step 2: Write `views/contacts/index.php`**

```php
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Contacts</h1>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary" href="/contacts/new">New contact</a>
  <?php endif; ?>
</div>
<p>
  <a class="btn" href="/contacts">All</a>
  <a class="btn" href="/contacts?type=donor">Donors</a>
  <a class="btn" href="/contacts?type=vendor">Vendors</a>
</p>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Name</th><th>Type</th><th>Email</th><th>Phone</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($contacts as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e(ucfirst($row['type'])) ?></td>
      <td><?= e($row['email']) ?></td>
      <td><?= e($row['phone']) ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/contacts/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/contacts/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this contact?">
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

- [ ] **Step 3: Write `views/contacts/form.php`**

```php
<?php $isNew = empty($c['id']); ?>
<h1><?= $isNew ? 'New contact' : 'Edit contact' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/contacts' : '/contacts/' . (int)$c['id'] ?>">
  <label>Type
    <select name="type">
      <?php foreach (['donor','vendor'] as $t): ?>
        <option value="<?= $t ?>" <?= (($c['type'] ?? 'donor') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Name <input name="name" value="<?= e($c['name'] ?? '') ?>" required></label>
  <label>Email <input type="email" name="email" value="<?= e($c['email'] ?? '') ?>"></label>
  <label>Phone <input name="phone" value="<?= e($c['phone'] ?? '') ?>"></label>
  <label>Address <textarea name="address"><?= e($c['address'] ?? '') ?></textarea></label>
  <label>Notes <textarea name="notes"><?= e($c['notes'] ?? '') ?></textarea></label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($c['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/contacts" class="btn">Cancel</a>
</form>
```

- [ ] **Step 4: Add routes in `public/index.php`** (after the user routes block, before the `try {`)

```php
use App\Controllers\ContactController;

$router->add('GET',  '/contacts',            fn() => (new ContactController())->index());
$router->add('GET',  '/contacts/new',        fn() => (new ContactController())->create());
$router->add('POST', '/contacts',            fn() => (new ContactController())->store());
$router->add('GET',  '/contacts/:id/edit',   fn($p) => (new ContactController())->edit((int)$p['id']));
$router->add('POST', '/contacts/:id',        fn($p) => (new ContactController())->update((int)$p['id']));
$router->add('POST', '/contacts/:id/delete', fn($p) => (new ContactController())->delete((int)$p['id']));
```

(Place the `use` statement with the other `use App\Controllers\...` lines at the top.)

- [ ] **Step 5: Verify (lint + e2e)**

```bash
php -l src/Controllers/ContactController.php && php -l views/contacts/index.php && php -l views/contacts/form.php
```
Then start `php -S 127.0.0.1:8771 -t public`, log in as admin, and confirm:
- `GET /contacts` → 200, empty table.
- `POST /contacts` (type=donor, name=Global Fund) → 302 → `/contacts`; the donor appears.
- `GET /contacts?type=vendor` → does not list the donor.
- Log in as viewer → `GET /contacts` → 200 but no "New contact"/Edit/Delete controls; `POST /contacts` → 403.

- [ ] **Step 6: Commit**

```bash
git add src/Controllers/ContactController.php views/contacts/ public/index.php
git commit -m "feat: Contacts CRUD with donor/vendor filter and role guards"
```

---

### Task 3: Project model (TDD)

**Files:**
- Create: `src/Models/Project.php`
- Test: `tests/ProjectTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Project::create(array $data): int` — keys `name,code,description`.
  - `App\Models\Project::all(): array` — ordered by name.
  - `App\Models\Project::find(int $id): ?array`
  - `App\Models\Project::update(int $id, array $data): void` — keys `name,code,description,active`.
  - `App\Models\Project::delete(int $id): void`

- [ ] **Step 1: Write the failing test** `tests/ProjectTest.php`

```php
<?php
namespace Tests;
use App\Models\Project;

final class ProjectTest extends DatabaseTestCase
{
    public function test_create_and_find(): void
    {
        $id = Project::create(['name'=>'Clean Water','code'=>'CW-01','description'=>'Wells']);
        $row = Project::find($id);
        $this->assertSame('Clean Water', $row['name']);
        $this->assertSame('CW-01', $row['code']);
        $this->assertSame(1, (int)$row['active']);
    }

    public function test_all_ordered_by_name(): void
    {
        Project::create(['name'=>'Zebra','code'=>'','description'=>'']);
        Project::create(['name'=>'Alpha','code'=>'','description'=>'']);
        $all = Project::all();
        $this->assertSame('Alpha', $all[0]['name']);
        $this->assertSame('Zebra', $all[1]['name']);
    }

    public function test_update_and_delete(): void
    {
        $id = Project::create(['name'=>'Old','code'=>'','description'=>'']);
        Project::update($id, ['name'=>'New','code'=>'X1','description'=>'desc','active'=>0]);
        $row = Project::find($id);
        $this->assertSame('New', $row['name']);
        $this->assertSame(0, (int)$row['active']);
        Project::delete($id);
        $this->assertNull(Project::find($id));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/ProjectTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `src/Models/Project.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class Project
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO projects (name, code, description, active)
             VALUES (:name, :code, :description, 1)'
        );
        $stmt->execute([
            ':name'=>$data['name'], ':code'=>$data['code'] ?: null,
            ':description'=>$data['description'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM projects ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE projects SET name=:name, code=:code, description=:description, active=:active WHERE id=:id'
        );
        $stmt->execute([
            ':name'=>$data['name'], ':code'=>$data['code'] ?: null,
            ':description'=>$data['description'] ?: null,
            ':active'=>(int)$data['active'], ':id'=>$id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/ProjectTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/Project.php tests/ProjectTest.php
git commit -m "feat: Project model"
```

---

### Task 4: Projects CRUD (controller + views + routes)

**Files:**
- Create: `src/Controllers/ProjectController.php`
- Create: `views/projects/index.php`, `views/projects/form.php`
- Modify: `public/index.php`

**Interfaces:**
- Consumes: `App\Models\Project`, `App\Auth`, `render()`.
- Produces: `ProjectController::index|create|store|edit|update|delete`. All require `admin`,`editor`.

- [ ] **Step 1: Write `src/Controllers/ProjectController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Project;

final class ProjectController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor');
        return render('projects/index', ['projects'=>Project::all()], 'Projects');
    }

    public function create(): string
    {
        Auth::requireRole('admin','editor');
        return render('projects/form', ['p'=>null, 'error'=>null], 'New project');
    }

    public function store(): string
    {
        Auth::requireRole('admin','editor');
        $error = $this->validate($_POST);
        if ($error) { return render('projects/form', ['p'=>$_POST, 'error'=>$error], 'New project'); }
        Project::create($this->fields($_POST));
        header('Location: /projects'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin','editor');
        $p = Project::find($id);
        if (!$p) { http_response_code(404); return 'Not found'; }
        return render('projects/form', ['p'=>$p, 'error'=>null], 'Edit project');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin','editor');
        if (!Project::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('projects/form', ['p'=>array_merge($_POST,['id'=>$id]), 'error'=>$error], 'Edit project'); }
        Project::update($id, $this->fields($_POST) + ['active'=>$_POST['active'] ?? 0]);
        header('Location: /projects'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin','editor');
        Project::delete($id);
        header('Location: /projects'); exit;
    }

    private function fields(array $in): array
    {
        return [
            'name'=>trim($in['name'] ?? ''), 'code'=>trim($in['code'] ?? ''),
            'description'=>trim($in['description'] ?? ''),
        ];
    }

    private function validate(array $in): ?string
    {
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        return null;
    }
}
```

- [ ] **Step 2: Write `views/projects/index.php`**

```php
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Projects</h1>
  <a class="btn btn-primary" href="/projects/new">New project</a>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Name</th><th>Code</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($projects as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e($row['code']) ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <a href="/projects/<?= (int)$row['id'] ?>/edit">Edit</a>
        <form method="post" action="/projects/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this project?">
          <button type="submit" class="btn-link-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
```

- [ ] **Step 3: Write `views/projects/form.php`**

```php
<?php $isNew = empty($p['id']); ?>
<h1><?= $isNew ? 'New project' : 'Edit project' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/projects' : '/projects/' . (int)$p['id'] ?>">
  <label>Name <input name="name" value="<?= e($p['name'] ?? '') ?>" required></label>
  <label>Code <input name="code" value="<?= e($p['code'] ?? '') ?>"></label>
  <label>Description <textarea name="description"><?= e($p['description'] ?? '') ?></textarea></label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($p['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/projects" class="btn">Cancel</a>
</form>
```

- [ ] **Step 4: Add routes in `public/index.php`**

```php
use App\Controllers\ProjectController;

$router->add('GET',  '/projects',            fn() => (new ProjectController())->index());
$router->add('GET',  '/projects/new',        fn() => (new ProjectController())->create());
$router->add('POST', '/projects',            fn() => (new ProjectController())->store());
$router->add('GET',  '/projects/:id/edit',   fn($p) => (new ProjectController())->edit((int)$p['id']));
$router->add('POST', '/projects/:id',        fn($p) => (new ProjectController())->update((int)$p['id']));
$router->add('POST', '/projects/:id/delete', fn($p) => (new ProjectController())->delete((int)$p['id']));
```

- [ ] **Step 5: Verify (lint + e2e)**

```bash
php -l src/Controllers/ProjectController.php && php -l views/projects/index.php && php -l views/projects/form.php
```
Start the dev server, log in as admin: create a project, see it listed, edit, delete. Log in as viewer → `GET /projects` → 403 (and nav hides the link).

- [ ] **Step 6: Commit**

```bash
git add src/Controllers/ProjectController.php views/projects/ public/index.php
git commit -m "feat: Projects CRUD with role guards"
```

---

### Task 5: Category model (TDD)

**Files:**
- Create: `src/Models/Category.php`
- Test: `tests/CategoryTest.php`

**Interfaces:**
- Produces:
  - `App\Models\Category::create(array $data): int` — keys `type,name,sort_order`.
  - `App\Models\Category::all(?string $type = null): array` — ordered by `sort_order, name`; optional `type` filter (`income`/`expense`).
  - `App\Models\Category::find(int $id): ?array`
  - `App\Models\Category::update(int $id, array $data): void` — keys `type,name,sort_order,active`.
  - `App\Models\Category::delete(int $id): void`

- [ ] **Step 1: Write the failing test** `tests/CategoryTest.php`

```php
<?php
namespace Tests;
use App\Models\Category;

final class CategoryTest extends DatabaseTestCase
{
    public function test_create_and_find(): void
    {
        $id = Category::create(['type'=>'income','name'=>'Grants','sort_order'=>5]);
        $row = Category::find($id);
        $this->assertSame('Grants', $row['name']);
        $this->assertSame('income', $row['type']);
        $this->assertSame(5, (int)$row['sort_order']);
        $this->assertSame(1, (int)$row['active']);
    }

    public function test_all_filters_by_type_and_orders(): void
    {
        Category::create(['type'=>'expense','name'=>'Rent','sort_order'=>2]);
        Category::create(['type'=>'expense','name'=>'Salaries','sort_order'=>1]);
        Category::create(['type'=>'income','name'=>'Donations','sort_order'=>1]);
        $this->assertCount(3, Category::all());
        $expense = Category::all('expense');
        $this->assertCount(2, $expense);
        $this->assertSame('Salaries', $expense[0]['name']); // sort_order 1 first
    }

    public function test_update_and_delete(): void
    {
        $id = Category::create(['type'=>'income','name'=>'Old','sort_order'=>0]);
        Category::update($id, ['type'=>'income','name'=>'New','sort_order'=>9,'active'=>0]);
        $row = Category::find($id);
        $this->assertSame('New', $row['name']);
        $this->assertSame(0, (int)$row['active']);
        Category::delete($id);
        $this->assertNull(Category::find($id));
    }
}
```

- [ ] **Step 2: Run test, verify it fails**

Run: `vendor/bin/phpunit tests/CategoryTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Write `src/Models/Category.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class Category
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO categories (type, name, sort_order, active)
             VALUES (:type, :name, :sort_order, 1)'
        );
        $stmt->execute([
            ':type'=>$data['type'], ':name'=>$data['name'],
            ':sort_order'=>(int)($data['sort_order'] ?? 0),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function all(?string $type = null): array
    {
        $pdo = Database::pdo();
        if ($type !== null) {
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE type = :type ORDER BY sort_order, name');
            $stmt->execute([':type'=>$type]);
            return $stmt->fetchAll();
        }
        return $pdo->query('SELECT * FROM categories ORDER BY type, sort_order, name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE categories SET type=:type, name=:name, sort_order=:sort_order, active=:active WHERE id=:id'
        );
        $stmt->execute([
            ':type'=>$data['type'], ':name'=>$data['name'],
            ':sort_order'=>(int)($data['sort_order'] ?? 0),
            ':active'=>(int)$data['active'], ':id'=>$id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
```

- [ ] **Step 4: Run test, verify it passes**

Run: `vendor/bin/phpunit tests/CategoryTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/Category.php tests/CategoryTest.php
git commit -m "feat: Category model (income/expense)"
```

---

### Task 6: Categories CRUD (controller + views + routes)

**Files:**
- Create: `src/Controllers/CategoryController.php`
- Create: `views/categories/index.php`, `views/categories/form.php`
- Modify: `public/index.php`

**Interfaces:**
- Consumes: `App\Models\Category`, `App\Auth`, `render()`.
- Produces: `CategoryController::index|create|store|edit|update|delete`. All require `admin`.

- [ ] **Step 1: Write `src/Controllers/CategoryController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Category;

final class CategoryController
{
    public function index(): string
    {
        Auth::requireRole('admin');
        return render('categories/index', ['categories'=>Category::all()], 'Categories');
    }

    public function create(): string
    {
        Auth::requireRole('admin');
        return render('categories/form', ['cat'=>null, 'error'=>null], 'New category');
    }

    public function store(): string
    {
        Auth::requireRole('admin');
        $error = $this->validate($_POST);
        if ($error) { return render('categories/form', ['cat'=>$_POST, 'error'=>$error], 'New category'); }
        Category::create($this->fields($_POST));
        header('Location: /categories'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin');
        $cat = Category::find($id);
        if (!$cat) { http_response_code(404); return 'Not found'; }
        return render('categories/form', ['cat'=>$cat, 'error'=>null], 'Edit category');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin');
        if (!Category::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST);
        if ($error) { return render('categories/form', ['cat'=>array_merge($_POST,['id'=>$id]), 'error'=>$error], 'Edit category'); }
        Category::update($id, $this->fields($_POST) + ['active'=>$_POST['active'] ?? 0]);
        header('Location: /categories'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin');
        Category::delete($id);
        header('Location: /categories'); exit;
    }

    private function fields(array $in): array
    {
        return [
            'type'=>$in['type'] ?? 'expense', 'name'=>trim($in['name'] ?? ''),
            'sort_order'=>(int)($in['sort_order'] ?? 0),
        ];
    }

    private function validate(array $in): ?string
    {
        if (!in_array($in['type'] ?? '', ['income','expense'], true)) return 'Type is invalid.';
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        return null;
    }
}
```

- [ ] **Step 2: Write `views/categories/index.php`**

```php
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Categories</h1>
  <a class="btn btn-primary" href="/categories/new">New category</a>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Type</th><th>Name</th><th>Sort</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($categories as $row): ?>
    <tr>
      <td><?= e(ucfirst($row['type'])) ?></td>
      <td><?= e($row['name']) ?></td>
      <td><?= (int)$row['sort_order'] ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <a href="/categories/<?= (int)$row['id'] ?>/edit">Edit</a>
        <form method="post" action="/categories/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this category?">
          <button type="submit" class="btn-link-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
```

- [ ] **Step 3: Write `views/categories/form.php`**

```php
<?php $isNew = empty($cat['id']); ?>
<h1><?= $isNew ? 'New category' : 'Edit category' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/categories' : '/categories/' . (int)$cat['id'] ?>">
  <label>Type
    <select name="type">
      <?php foreach (['income','expense'] as $t): ?>
        <option value="<?= $t ?>" <?= (($cat['type'] ?? 'expense') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Name <input name="name" value="<?= e($cat['name'] ?? '') ?>" required></label>
  <label>Sort order <input type="number" name="sort_order" value="<?= (int)($cat['sort_order'] ?? 0) ?>"></label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($cat['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/categories" class="btn">Cancel</a>
</form>
```

- [ ] **Step 4: Add routes in `public/index.php`**

```php
use App\Controllers\CategoryController;

$router->add('GET',  '/categories',            fn() => (new CategoryController())->index());
$router->add('GET',  '/categories/new',        fn() => (new CategoryController())->create());
$router->add('POST', '/categories',            fn() => (new CategoryController())->store());
$router->add('GET',  '/categories/:id/edit',   fn($p) => (new CategoryController())->edit((int)$p['id']));
$router->add('POST', '/categories/:id',        fn($p) => (new CategoryController())->update((int)$p['id']));
$router->add('POST', '/categories/:id/delete', fn($p) => (new CategoryController())->delete((int)$p['id']));
```

- [ ] **Step 5: Verify (lint + e2e)**

```bash
php -l src/Controllers/CategoryController.php && php -l views/categories/index.php && php -l views/categories/form.php
```
Start the dev server, log in as admin: create an income and an expense category, list shows both grouped by type; edit and delete work. Log in as editor → `GET /categories` → 403.

- [ ] **Step 6: Commit**

```bash
git add src/Controllers/CategoryController.php views/categories/ public/index.php
git commit -m "feat: Categories CRUD (admin only)"
```

---

### Task 7: Seed starter categories

**Files:**
- Modify: `db/seed.sql` (append category inserts)
- Create: `bin/seed-categories.php` (idempotent loader for an existing DB)

**Interfaces:**
- Produces: the spec's starter income + expense categories, inserted only if the `categories` table is empty (idempotent).

- [ ] **Step 1: Append starter categories to `db/seed.sql`**

```sql

-- Starter categories (income then expense). Safe to run once on an empty categories table.
INSERT INTO categories (type, name, sort_order, active) VALUES
('income','Grants (Restricted)',1,1),
('income','Grants (Unrestricted)',2,1),
('income','Individual Donations',3,1),
('income','Corporate Donations',4,1),
('income','Membership & Contributions',5,1),
('income','Bank/Interest Income',6,1),
('income','Other Income',7,1),
('expense','Salaries & Wages',1,1),
('expense','Staff Benefits',2,1),
('expense','Office Rent',3,1),
('expense','Utilities',4,1),
('expense','Travel & Transport',5,1),
('expense','Programme/Project Costs',6,1),
('expense','Training & Workshops',7,1),
('expense','Office Supplies',8,1),
('expense','Equipment',9,1),
('expense','Professional Fees (Audit/Legal)',10,1),
('expense','Bank Charges',11,1),
('expense','Communication',12,1),
('expense','Repairs & Maintenance',13,1),
('expense','Fundraising Costs',14,1),
('expense','Miscellaneous',15,1);
```

- [ ] **Step 2: Write `bin/seed-categories.php`** (idempotent: inserts only if table empty)

```php
<?php
// Usage: php bin/seed-categories.php  — seeds starter categories if none exist.
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Database;
use App\Models\Category;

$count = (int) Database::pdo()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
if ($count > 0) {
    echo "Categories already present ({$count}); nothing to do.\n";
    exit(0);
}

$income = ['Grants (Restricted)','Grants (Unrestricted)','Individual Donations','Corporate Donations',
    'Membership & Contributions','Bank/Interest Income','Other Income'];
$expense = ['Salaries & Wages','Staff Benefits','Office Rent','Utilities','Travel & Transport',
    'Programme/Project Costs','Training & Workshops','Office Supplies','Equipment',
    'Professional Fees (Audit/Legal)','Bank Charges','Communication','Repairs & Maintenance',
    'Fundraising Costs','Miscellaneous'];

$n = 0;
foreach ($income as $i => $name)  { Category::create(['type'=>'income','name'=>$name,'sort_order'=>$i+1]);  $n++; }
foreach ($expense as $i => $name) { Category::create(['type'=>'expense','name'=>$name,'sort_order'=>$i+1]); $n++; }
echo "Seeded {$n} categories.\n";
```

- [ ] **Step 3: Verify the seeder**

```bash
php -l bin/seed-categories.php
php bin/seed-categories.php          # seeds (expect "Seeded 22 categories.")
php bin/seed-categories.php          # idempotent (expect "already present")
mysql --host=127.0.0.1 --protocol=tcp -uroot lipa -e "SELECT type, COUNT(*) FROM categories GROUP BY type;"
```
Expected: income 7, expense 15.

- [ ] **Step 4: Commit**

```bash
git add db/seed.sql bin/seed-categories.php
git commit -m "feat: seed starter income/expense categories (idempotent)"
```

---

## Self-Review

**Spec coverage (Plan 2 scope):**
- Contacts (donors + vendors) with type filter → Tasks 1, 2. ✓
- Projects CRUD → Tasks 3, 4. ✓
- Categories CRUD (income/expense) → Tasks 5, 6. ✓
- Starter category seed (spec §6 lists) → Task 7. ✓
- Role matrix (contacts view-all/edit-staff; projects staff-only; categories admin-only) → guards in Tasks 2, 4, 6, matching `_shell.php` nav visibility from Plan 1. ✓
- Mobile: every list wrapped in `table-wrap`; themed classes only. ✓
- Deferred to later plans: income/expenses + receipt uploads (Plan 3); dashboard, Excel export, settings, activity-log UI (Plan 4).

**Placeholder scan:** None. All controller/model/view code is complete; validation is concrete in each controller.

**Type consistency:** Model signatures (`create/all/find/update/delete`) are consistent across Contact/Project/Category and match controller call sites. Route closures instantiate controllers lazily, matching Plan 1's `public/index.php` pattern. View variable names (`$contacts`/`$c`, `$projects`/`$p`, `$categories`/`$cat`) are consistent between each controller and its views.
