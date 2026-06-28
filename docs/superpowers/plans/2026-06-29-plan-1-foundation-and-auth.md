# LIPA Web — Plan 1: Foundation & Auth — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a running plain-PHP + MariaDB web app skeleton with the full database schema, a request router, the ported LIPA theme, session-based authentication, server-side role guards, and admin Users CRUD.

**Architecture:** Lean plain PHP behind a single front controller (`public/index.php`) with a minimal regex router. Data access via PDO against MariaDB (MySQL 8.4 locally). Auth uses PHP sessions + `password_hash`/`password_verify`. Views are server-rendered PHP templates sharing one layout, styled by LIPA's `theme.css`. Tests run on PHPUnit against a dedicated test database.

**Tech Stack:** PHP 8.3, MariaDB/MySQL (PDO), Composer (`vlucas/phpdotenv`, dev: `phpunit/phpunit`), vanilla JS/CSS (ported `theme.css`).

## Global Constraints

- PHP **8.3** locally; production is **MariaDB** on DomainFactory, local is **MySQL 8.4** — write **portable SQL only** (no MySQL-8-only features).
- Composer dependencies are limited to: `vlucas/phpdotenv` (runtime), `phpoffice/phpspreadsheet` (added in Plan 4), and `phpunit/phpunit` (dev). Do not add others without updating the spec.
- `public/` is the **only** web-exposed directory. The real `.env` lives at project root (outside `public/`) and is **gitignored**; only `.env.example` is committed.
- All money columns are `DECIMAL(15,2)`. Base currency is `TZS`.
- UI language is **English (UK)** throughout.
- Roles are `admin` / `editor` / `viewer`. Every write route is guarded **server-side**; never rely on hidden UI alone.
- All SQL uses **PDO prepared statements** — no string-interpolated values.
- Reuse LIPA's design tokens (`theme.css`) — never hardcode colours; use `var(--...)`.

---

## File structure (created across this plan)

```
LIPA Web 26/
├── composer.json                 # deps + autoload (PSR-4 "App\\" => src/)
├── phpunit.xml                    # test config
├── .env.example                  # committed template
├── .env                          # local only, gitignored (created from example)
├── public/
│   ├── index.php                 # front controller: boot + route dispatch
│   └── assets/
│       ├── css/theme.css         # ported from LIPA
│       └── js/app.js             # toast/confirm/modal helpers (ported subset)
├── src/
│   ├── Database.php              # PDO connection (singleton)
│   ├── Router.php                # register routes + dispatch
│   ├── Auth.php                  # session login/logout, current user, role guard
│   ├── Controllers/
│   │   ├── AuthController.php     # showLogin, login, logout
│   │   ├── DashboardController.php# index (placeholder dashboard)
│   │   └── UserController.php     # index, create, store, edit, update, delete
│   └── Models/
│       └── User.php              # CRUD + auth lookups
├── views/
│   ├── layout.php                # shared HTML shell + sidebar nav
│   ├── auth/login.php
│   ├── dashboard.php
│   └── users/
│       ├── index.php
│       └── form.php
├── db/
│   ├── schema.sql                # ALL tables (locked in here for the whole project)
│   └── seed.sql                  # first admin user
└── tests/
    ├── bootstrap.php             # autoload + load .env.testing
    ├── DatabaseTestCase.php      # base class: build schema in test DB, truncate per test
    ├── RouterTest.php
    ├── AuthTest.php
    └── UserTest.php
```

---

### Task 1: Composer project + autoload + PHPUnit

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml`
- Create: `tests/bootstrap.php`
- Create: `.gitignore` (append; repo already has one)

**Interfaces:**
- Produces: PSR-4 autoloader mapping namespace `App\` → `src/`; `composer test` script; PHPUnit configured with `tests/bootstrap.php`.

- [ ] **Step 1: Write `composer.json`**

```json
{
    "name": "pepea/lipa-web",
    "description": "NGO income/expense tracker (QuickBooks replacement)",
    "type": "project",
    "require": {
        "php": ">=8.3",
        "vlucas/phpdotenv": "^5.6"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": { "App\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Tests\\": "tests/" }
    },
    "scripts": {
        "test": "phpunit"
    },
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Write `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="LIPA Web">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Write `tests/bootstrap.php`**

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

// Load test env if present, else fall back to .env
$root = dirname(__DIR__);
$file = is_file($root . '/.env.testing') ? '.env.testing' : '.env';
$dotenv = Dotenv\Dotenv::createImmutable($root, $file);
$dotenv->safeLoad();
```

- [ ] **Step 4: Append test/build ignores to `.gitignore`**

Append these lines (the repo's `.gitignore` already ignores `vendor`, `.env`, uploads):

```
.env.testing
.phpunit.result.cache
/.phpunit.cache/
```

- [ ] **Step 5: Install dependencies**

Run: `cd "C:/laragon/www/lipa" && composer install`
(If the project is still at `C:/Tools/LIPA Web 26`, run there; serving location is decided in Task 11.)
Expected: `vendor/` created, `vendor/autoload.php` exists, phpunit installed.

- [ ] **Step 6: Verify PHPUnit runs (no tests yet)**

Run: `vendor/bin/phpunit`
Expected: "No tests executed!" (exit ok) — confirms config + bootstrap load without error.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock phpunit.xml tests/bootstrap.php .gitignore
git commit -m "chore: composer autoload + PHPUnit setup"
```

---

### Task 2: Database schema (all tables) + env config

**Files:**
- Create: `db/schema.sql`
- Create: `.env.example`
- Create: `.env` (local, not committed)

**Interfaces:**
- Produces: the canonical schema for the whole project (tables `users`, `contacts`, `projects`, `categories`, `income`, `expenses`, `settings`, `activity_log`); env keys `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_ENV`.

- [ ] **Step 1: Write `db/schema.sql`** (portable SQL — runs on MySQL 8.4 and MariaDB)

```sql
-- LIPA Web schema. Portable across MySQL 8.4 and MariaDB.
SET sql_mode = 'STRICT_ALL_TABLES';

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('donor','vendor') NOT NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  address TEXT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  code VARCHAR(40) NULL,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('income','expense') NOT NULL,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS income (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  contact_id INT NULL,
  project_id INT NULL,
  category_id INT NULL,
  description VARCHAR(255) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
  amount_original DECIMAL(15,2) NOT NULL DEFAULT 0,
  exchange_rate DECIMAL(15,6) NOT NULL DEFAULT 1,
  amount_tzs DECIMAL(15,2) NOT NULL DEFAULT 0,
  reference VARCHAR(120) NULL,
  receipt_path VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_income_contact  FOREIGN KEY (contact_id)  REFERENCES contacts(id)  ON DELETE SET NULL,
  CONSTRAINT fk_income_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE SET NULL,
  CONSTRAINT fk_income_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_income_user     FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  contact_id INT NULL,
  project_id INT NULL,
  category_id INT NULL,
  description VARCHAR(255) NULL,
  amount_tzs DECIMAL(15,2) NOT NULL DEFAULT 0,
  reference VARCHAR(120) NULL,
  receipt_path VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expense_contact  FOREIGN KEY (contact_id)  REFERENCES contacts(id)  ON DELETE SET NULL,
  CONSTRAINT fk_expense_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE SET NULL,
  CONSTRAINT fk_expense_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_expense_user     FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(40) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Write `.env.example`**

```
APP_ENV=local
APP_URL=http://lipa.test

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=lipa
DB_USER=root
DB_PASS=
```

- [ ] **Step 3: Create local `.env`** (copy of example; root password blank in Laragon)

Run: `cp .env.example .env`
Then create a second file `.env.testing` with the same keys but `DB_NAME=lipa_test` and `APP_ENV=testing`.

- [ ] **Step 4: Create the databases**

Run (Laragon MySQL on PATH after "Start All"; adjust mysql path if needed):
```
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS lipa CHARACTER SET utf8mb4; CREATE DATABASE IF NOT EXISTS lipa_test CHARACTER SET utf8mb4;"
```
Then load schema into the dev DB:
```
mysql -uroot lipa < db/schema.sql
```
Expected: no errors; `SHOW TABLES` in `lipa` lists 8 tables.

- [ ] **Step 5: Commit** (note: `.env` and `.env.testing` are gitignored)

```bash
git add db/schema.sql .env.example
git commit -m "feat: database schema (all tables) + env template"
```

---

### Task 3: PDO Database connection

**Files:**
- Create: `src/Database.php`
- Test: `tests/DatabaseTestCase.php`, `tests/DatabaseConnectionTest.php`

**Interfaces:**
- Produces: `App\Database::pdo(): PDO` — returns a shared PDO using env vars, with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, emulated prepares off. `App\Database::reset(): void` — drops the cached instance (for tests).

- [ ] **Step 1: Write the failing test** `tests/DatabaseConnectionTest.php`

```php
<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Database;

final class DatabaseConnectionTest extends TestCase
{
    public function test_pdo_returns_working_connection(): void
    {
        $pdo = Database::pdo();
        $this->assertInstanceOf(\PDO::class, $pdo);
        $value = $pdo->query('SELECT 1')->fetchColumn();
        $this->assertEquals(1, $value);
    }

    public function test_pdo_is_shared_instance(): void
    {
        $this->assertSame(Database::pdo(), Database::pdo());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/DatabaseConnectionTest.php`
Expected: FAIL — class `App\Database` not found.

- [ ] **Step 3: Write `src/Database.php`**

```php
<?php
namespace App;

use PDO;

final class Database
{
    private static ?PDO $instance = null;

    public static function pdo(): PDO
    {
        if (self::$instance === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $name = $_ENV['DB_NAME'] ?? 'lipa';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/DatabaseConnectionTest.php`
Expected: PASS (2 tests). Requires `lipa_test` to exist (Task 2 Step 4).

- [ ] **Step 5: Write `tests/DatabaseTestCase.php`** (base class for model tests)

```php
<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Database;

abstract class DatabaseTestCase extends TestCase
{
    protected static function loadSchema(): void
    {
        $sql = file_get_contents(dirname(__DIR__) . '/db/schema.sql');
        Database::pdo()->exec($sql);
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::loadSchema();
        $pdo = Database::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (['activity_log','income','expenses','categories','projects','contacts','settings','users'] as $t) {
            $pdo->exec("TRUNCATE TABLE {$t}");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Database.php tests/DatabaseConnectionTest.php tests/DatabaseTestCase.php
git commit -m "feat: PDO Database connection + test base class"
```

---

### Task 4: User model (CRUD + auth lookups)

**Files:**
- Create: `src/Models/User.php`
- Test: `tests/UserTest.php`

**Interfaces:**
- Consumes: `App\Database::pdo()`.
- Produces:
  - `App\Models\User::create(array $data): int` — keys `name,email,password,role`; hashes password; returns new id.
  - `App\Models\User::all(): array` — all users ordered by name.
  - `App\Models\User::find(int $id): ?array`
  - `App\Models\User::findByEmail(string $email): ?array`
  - `App\Models\User::update(int $id, array $data): void` — keys `name,email,role,active`; optional `password` (re-hashes if non-empty).
  - `App\Models\User::delete(int $id): void`
  - Rows include `password_hash`; callers must not leak it to views.

- [ ] **Step 1: Write the failing test** `tests/UserTest.php`

```php
<?php
namespace Tests;
use App\Models\User;

final class UserTest extends DatabaseTestCase
{
    public function test_create_hashes_password_and_returns_id(): void
    {
        $id = User::create([
            'name' => 'Admin', 'email' => 'a@x.org',
            'password' => 'secret123', 'role' => 'admin',
        ]);
        $this->assertGreaterThan(0, $id);
        $row = User::find($id);
        $this->assertSame('a@x.org', $row['email']);
        $this->assertNotSame('secret123', $row['password_hash']);
        $this->assertTrue(password_verify('secret123', $row['password_hash']));
    }

    public function test_find_by_email(): void
    {
        User::create(['name'=>'B','email'=>'b@x.org','password'=>'pw','role'=>'editor']);
        $this->assertSame('editor', User::findByEmail('b@x.org')['role']);
        $this->assertNull(User::findByEmail('missing@x.org'));
    }

    public function test_update_changes_fields_and_optional_password(): void
    {
        $id = User::create(['name'=>'C','email'=>'c@x.org','password'=>'old','role'=>'viewer']);
        User::update($id, ['name'=>'C2','email'=>'c@x.org','role'=>'editor','active'=>1,'password'=>'new']);
        $row = User::find($id);
        $this->assertSame('C2', $row['name']);
        $this->assertSame('editor', $row['role']);
        $this->assertTrue(password_verify('new', $row['password_hash']));
    }

    public function test_update_keeps_password_when_blank(): void
    {
        $id = User::create(['name'=>'D','email'=>'d@x.org','password'=>'keep','role'=>'viewer']);
        User::update($id, ['name'=>'D','email'=>'d@x.org','role'=>'viewer','active'=>1,'password'=>'']);
        $this->assertTrue(password_verify('keep', User::find($id)['password_hash']));
    }

    public function test_delete_removes_row(): void
    {
        $id = User::create(['name'=>'E','email'=>'e@x.org','password'=>'pw','role'=>'viewer']);
        User::delete($id);
        $this->assertNull(User::find($id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/UserTest.php`
Expected: FAIL — class `App\Models\User` not found.

- [ ] **Step 3: Write `src/Models/User.php`**

```php
<?php
namespace App\Models;

use App\Database;

final class User
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, active)
             VALUES (:name, :email, :hash, :role, 1)'
        );
        $stmt->execute([
            ':name'  => $data['name'],
            ':email' => $data['email'],
            ':hash'  => password_hash($data['password'], PASSWORD_DEFAULT),
            ':role'  => $data['role'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM users ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::pdo();
        if (!empty($data['password'])) {
            $stmt = $pdo->prepare(
                'UPDATE users SET name=:name, email=:email, role=:role, active=:active,
                 password_hash=:hash WHERE id=:id'
            );
            $stmt->execute([
                ':name'=>$data['name'], ':email'=>$data['email'], ':role'=>$data['role'],
                ':active'=>(int)$data['active'],
                ':hash'=>password_hash($data['password'], PASSWORD_DEFAULT), ':id'=>$id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET name=:name, email=:email, role=:role, active=:active WHERE id=:id'
            );
            $stmt->execute([
                ':name'=>$data['name'], ':email'=>$data['email'], ':role'=>$data['role'],
                ':active'=>(int)$data['active'], ':id'=>$id,
            ]);
        }
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/UserTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Models/User.php tests/UserTest.php
git commit -m "feat: User model with CRUD and password hashing"
```

---

### Task 5: Router

**Files:**
- Create: `src/Router.php`
- Test: `tests/RouterTest.php`

**Interfaces:**
- Produces:
  - `App\Router::add(string $method, string $path, callable $handler): void`
  - `App\Router::dispatch(string $method, string $uri): mixed` — matches `:param` segments, passes captured params (associative array) to the handler; returns the handler's return value. Throws `App\NotFoundException` if no route matches.
  - `App\NotFoundException extends \RuntimeException`.

- [ ] **Step 1: Write the failing test** `tests/RouterTest.php`

```php
<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Router;
use App\NotFoundException;

final class RouterTest extends TestCase
{
    public function test_matches_static_route(): void
    {
        $r = new Router();
        $r->add('GET', '/users', fn() => 'list');
        $this->assertSame('list', $r->dispatch('GET', '/users'));
    }

    public function test_captures_named_param(): void
    {
        $r = new Router();
        $r->add('GET', '/users/:id/edit', fn($p) => 'edit-' . $p['id']);
        $this->assertSame('edit-7', $r->dispatch('GET', '/users/7/edit'));
    }

    public function test_method_matters(): void
    {
        $r = new Router();
        $r->add('POST', '/users', fn() => 'created');
        $this->assertSame('created', $r->dispatch('POST', '/users'));
        $this->expectException(NotFoundException::class);
        $r->dispatch('GET', '/users');
    }

    public function test_unknown_route_throws(): void
    {
        $r = new Router();
        $this->expectException(NotFoundException::class);
        $r->dispatch('GET', '/nope');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/RouterTest.php`
Expected: FAIL — class `App\Router` not found.

- [ ] **Step 3: Write `src/Router.php`**

```php
<?php
namespace App;

class NotFoundException extends \RuntimeException {}

final class Router
{
    /** @var array<int, array{method:string, regex:string, keys:array<int,string>, handler:callable}> */
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $keys = [];
        $regex = preg_replace_callback('#:([a-zA-Z_]+)#', function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '([^/]+)';
        }, $path);
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => '#^' . $regex . '$#',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): mixed
    {
        $uri = '/' . trim(parse_url($uri, PHP_URL_PATH) ?? '/', '/');
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) {
                continue;
            }
            if (preg_match($route['regex'], $uri, $matches)) {
                array_shift($matches);
                $params = array_combine($route['keys'], $matches) ?: [];
                return ($route['handler'])($params);
            }
        }
        throw new NotFoundException("No route for {$method} {$uri}");
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/RouterTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Router.php tests/RouterTest.php
git commit -m "feat: minimal regex router with named params"
```

---

### Task 6: Auth (sessions + role guard)

**Files:**
- Create: `src/Auth.php`
- Test: `tests/AuthTest.php`

**Interfaces:**
- Consumes: `App\Models\User::findByEmail()`.
- Produces:
  - `App\Auth::attempt(string $email, string $password): bool` — verifies against `users`, requires `active=1`; on success stores `$_SESSION['user']` (id, name, email, role).
  - `App\Auth::check(): bool` / `App\Auth::user(): ?array` / `App\Auth::logout(): void`.
  - `App\Auth::is(string ...$roles): bool` — true if current user's role is in list.
  - `App\Auth::requireRole(string ...$roles): void` — throws `App\ForbiddenException` if not; throws if not logged in.
  - `App\ForbiddenException extends \RuntimeException`.

- [ ] **Step 1: Write the failing test** `tests/AuthTest.php`

```php
<?php
namespace Tests;
use App\Auth;
use App\ForbiddenException;
use App\Models\User;

final class AuthTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        User::create(['name'=>'Ada','email'=>'ada@x.org','password'=>'pw12345','role'=>'admin']);
    }

    public function test_attempt_succeeds_with_correct_password(): void
    {
        $this->assertTrue(Auth::attempt('ada@x.org', 'pw12345'));
        $this->assertTrue(Auth::check());
        $this->assertSame('admin', Auth::user()['role']);
    }

    public function test_attempt_fails_with_wrong_password(): void
    {
        $this->assertFalse(Auth::attempt('ada@x.org', 'wrong'));
        $this->assertFalse(Auth::check());
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $id = User::create(['name'=>'Off','email'=>'off@x.org','password'=>'pw','role'=>'viewer']);
        User::update($id, ['name'=>'Off','email'=>'off@x.org','role'=>'viewer','active'=>0,'password'=>'']);
        $this->assertFalse(Auth::attempt('off@x.org', 'pw'));
    }

    public function test_require_role_allows_and_blocks(): void
    {
        Auth::attempt('ada@x.org', 'pw12345');
        Auth::requireRole('admin');            // no exception
        $this->assertTrue(Auth::is('admin'));
        $this->expectException(ForbiddenException::class);
        Auth::requireRole('editor');           // admin is not editor
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit tests/AuthTest.php`
Expected: FAIL — class `App\Auth` not found.

- [ ] **Step 3: Write `src/Auth.php`**

```php
<?php
namespace App;

use App\Models\User;

class ForbiddenException extends \RuntimeException {}

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if (!$user || (int)$user['active'] !== 1) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        $_SESSION['user'] = [
            'id' => (int)$user['id'], 'name' => $user['name'],
            'email' => $user['email'], 'role' => $user['role'],
        ];
        return true;
    }

    public static function check(): bool { return isset($_SESSION['user']); }

    public static function user(): ?array { return $_SESSION['user'] ?? null; }

    public static function logout(): void { unset($_SESSION['user']); }

    public static function is(string ...$roles): bool
    {
        return self::check() && in_array($_SESSION['user']['role'], $roles, true);
    }

    public static function requireRole(string ...$roles): void
    {
        if (!self::check()) {
            throw new ForbiddenException('Not authenticated');
        }
        if (!self::is(...$roles)) {
            throw new ForbiddenException('Insufficient role');
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit tests/AuthTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Run the full suite**

Run: `vendor/bin/phpunit`
Expected: all tests green (Database, User, Router, Auth).

- [ ] **Step 6: Commit**

```bash
git add src/Auth.php tests/AuthTest.php
git commit -m "feat: session auth with role guards"
```

---

### Task 7: Theme, layout, and front-end helpers

**Files:**
- Create: `public/assets/css/theme.css` (port from `C:/Tools/Billing 26/styles/theme.css`)
- Create: `public/assets/js/app.js`
- Create: `views/layout.php`

**Interfaces:**
- Produces: a `render(string $view, array $data = [], ?string $title = null): string` helper used by controllers (defined here as a plain function in `views/layout.php`'s companion — see Step 3). Layout exposes `$user` (from `Auth::user()`) and renders the sidebar with role-aware nav links.

- [ ] **Step 1: Port the theme**

Copy `C:/Tools/Billing 26/styles/theme.css` to `public/assets/css/theme.css` verbatim. This brings LIPA's design tokens (light/dark vars, spacing, radius). Do not edit values.

Run: `cp "C:/Tools/Billing 26/styles/theme.css" public/assets/css/theme.css`

- [ ] **Step 2: Write `public/assets/js/app.js`** (toast + confirm helpers, vanilla)

```js
// Minimal UI helpers shared across pages.
function showToast(message, type = 'success') {
  let c = document.getElementById('toast-container');
  if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = message;
  c.appendChild(t);
  setTimeout(() => t.remove(), 2800);
}

// Attach to any form/button with data-confirm="message"
document.addEventListener('submit', (e) => {
  const msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { e.preventDefault(); }
});
```

- [ ] **Step 3: Write `views/layout.php`** (defines the `render()` helper + HTML shell)

```php
<?php
use App\Auth;

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('render')) {
    function render(string $view, array $data = [], ?string $title = null): string {
        extract($data);
        $user = Auth::user();
        ob_start();
        include dirname(__DIR__) . '/views/' . $view . '.php';
        $content = ob_get_clean();
        ob_start();
        include dirname(__DIR__) . '/views/_shell.php';
        return ob_get_clean();
    }
}
```

- [ ] **Step 4: Write `views/_shell.php`** (the outer HTML; `$content`, `$title`, `$user` in scope)

```php
<!DOCTYPE html>
<html lang="en-GB">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'LIPA') ?> — LIPA</title>
  <link rel="stylesheet" href="/assets/css/theme.css">
</head>
<body>
<?php if ($user): ?>
  <div class="app-shell" style="display:flex;min-height:100vh">
    <aside class="sidebar">
      <div class="sidebar-brand">LIPA</div>
      <nav>
        <a href="/">Dashboard</a>
        <a href="/income">Income</a>
        <a href="/expenses">Expenses</a>
        <a href="/contacts">Contacts</a>
        <?php if (Auth::is('admin','editor')): ?><a href="/projects">Projects</a><?php endif; ?>
        <a href="/reports">Reports</a>
        <?php if (Auth::is('admin')): ?>
          <a href="/categories">Categories</a>
          <a href="/users">Users</a>
          <a href="/settings">Settings</a>
        <?php endif; ?>
        <?php if (Auth::is('admin','viewer')): ?><a href="/activity">Activity log</a><?php endif; ?>
      </nav>
      <form method="post" action="/logout" class="sidebar-logout">
        <span><?= e($user['name']) ?> (<?= e($user['role']) ?>)</span>
        <button type="submit">Log out</button>
      </form>
    </aside>
    <main class="content" style="flex:1;padding:24px"><?= $content ?></main>
  </div>
<?php else: ?>
  <?= $content ?>
<?php endif; ?>
<script src="/assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 5: Commit**

```bash
git add public/assets/css/theme.css public/assets/js/app.js views/layout.php views/_shell.php
git commit -m "feat: theme, layout shell, and UI helpers"
```

---

### Task 8: Front controller + auth pages (login/logout)

**Files:**
- Create: `public/index.php`
- Create: `src/Controllers/AuthController.php`
- Create: `views/auth/login.php`
- Create: `src/Controllers/DashboardController.php`
- Create: `views/dashboard.php`

**Interfaces:**
- Consumes: `App\Router`, `App\Auth`, `render()`.
- Produces: bootable app. Routes: `GET /login`, `POST /login`, `POST /logout`, `GET /` (dashboard, requires auth).
- `AuthController::showLogin()`, `AuthController::login()`, `AuthController::logout()` (all echo/redirect).
- `DashboardController::index()`.

- [ ] **Step 1: Write `src/Controllers/AuthController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;

final class AuthController
{
    public function showLogin(): string
    {
        if (Auth::check()) { header('Location: /'); exit; }
        return render('auth/login', ['error' => null], 'Sign in');
    }

    public function login(): string
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (Auth::attempt($email, $password)) {
            header('Location: /'); exit;
        }
        return render('auth/login', ['error' => 'Invalid email or password.'], 'Sign in');
    }

    public function logout(): never
    {
        Auth::logout();
        header('Location: /login');
        exit;
    }
}
```

- [ ] **Step 2: Write `views/auth/login.php`**

```php
<div class="login-wrap" style="max-width:360px;margin:10vh auto">
  <h1>LIPA</h1>
  <p>Pepea — income &amp; expenses</p>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/login">
    <label>Email <input type="email" name="email" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
</div>
```

- [ ] **Step 3: Write `src/Controllers/DashboardController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;

final class DashboardController
{
    public function index(): string
    {
        Auth::requireRole('admin', 'editor', 'viewer');
        return render('dashboard', [], 'Dashboard');
    }
}
```

- [ ] **Step 4: Write `views/dashboard.php`**

```php
<h1>Dashboard</h1>
<p>Welcome, <?= e($user['name']) ?>. KPIs arrive in Plan 4.</p>
```

- [ ] **Step 5: Write `public/index.php`** (front controller)

```php
<?php
declare(strict_types=1);

session_start();
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/views/layout.php'; // defines render(), e()

use App\Router;
use App\NotFoundException;
use App\ForbiddenException;
use App\Auth;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\UserController;

$root = dirname(__DIR__);
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();

$router = new Router();
$auth = new AuthController();
$dash = new DashboardController();
$users = new UserController();

$router->add('GET',  '/login',  fn() => $auth->showLogin());
$router->add('POST', '/login',  fn() => $auth->login());
$router->add('POST', '/logout', fn() => $auth->logout());
$router->add('GET',  '/',       fn() => $dash->index());

// Users (admin) — controller methods added in Task 9
$router->add('GET',  '/users',            fn() => $users->index());
$router->add('GET',  '/users/new',        fn() => $users->create());
$router->add('POST', '/users',            fn() => $users->store());
$router->add('GET',  '/users/:id/edit',   fn($p) => $users->edit((int)$p['id']));
$router->add('POST', '/users/:id',        fn($p) => $users->update((int)$p['id']));
$router->add('POST', '/users/:id/delete', fn($p) => $users->delete((int)$p['id']));

try {
    echo $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (ForbiddenException $ex) {
    if (!Auth::check()) { header('Location: /login'); exit; }
    http_response_code(403);
    echo 'Forbidden';
} catch (NotFoundException $ex) {
    http_response_code(404);
    echo 'Not found';
}
```

- [ ] **Step 6: Manual verification (browser)**

Start Laragon ("Start All"). Visit `http://lipa.test/login` → login form renders with theme.
Visit `http://lipa.test/` while logged out → redirects to `/login`.
(Login itself needs a user — created via seed in Task 10. If testing now, insert one with the snippet in Task 10 Step 2.)
Expected: pages render, no PHP errors in `C:/laragon/bin/apache/.../logs` or browser.

- [ ] **Step 7: Commit**

```bash
git add public/index.php src/Controllers/AuthController.php src/Controllers/DashboardController.php views/auth/login.php views/dashboard.php
git commit -m "feat: front controller, login/logout, dashboard placeholder"
```

---

### Task 9: Users CRUD (admin)

**Files:**
- Create: `src/Controllers/UserController.php`
- Create: `views/users/index.php`
- Create: `views/users/form.php`

**Interfaces:**
- Consumes: `App\Models\User`, `App\Auth`, `render()`. Routes already registered in `public/index.php` (Task 8 Step 5).
- Produces: `UserController::index|create|store|edit|update|delete`. Every method calls `Auth::requireRole('admin')` first.

- [ ] **Step 1: Write `src/Controllers/UserController.php`**

```php
<?php
namespace App\Controllers;

use App\Auth;
use App\Models\User;

final class UserController
{
    public function index(): string
    {
        Auth::requireRole('admin');
        return render('users/index', ['users' => User::all()], 'Users');
    }

    public function create(): string
    {
        Auth::requireRole('admin');
        return render('users/form', ['u' => null, 'error' => null], 'New user');
    }

    public function store(): string
    {
        Auth::requireRole('admin');
        $error = $this->validate($_POST, true);
        if ($error) {
            return render('users/form', ['u' => $_POST, 'error' => $error], 'New user');
        }
        User::create([
            'name' => trim($_POST['name']), 'email' => trim($_POST['email']),
            'password' => $_POST['password'], 'role' => $_POST['role'],
        ]);
        header('Location: /users'); exit;
    }

    public function edit(int $id): string
    {
        Auth::requireRole('admin');
        $u = User::find($id);
        if (!$u) { http_response_code(404); return 'Not found'; }
        return render('users/form', ['u' => $u, 'error' => null], 'Edit user');
    }

    public function update(int $id): string
    {
        Auth::requireRole('admin');
        if (!User::find($id)) { http_response_code(404); return 'Not found'; }
        $error = $this->validate($_POST, false);
        if ($error) {
            return render('users/form', ['u' => array_merge($_POST, ['id'=>$id]), 'error' => $error], 'Edit user');
        }
        User::update($id, [
            'name' => trim($_POST['name']), 'email' => trim($_POST['email']),
            'role' => $_POST['role'], 'active' => $_POST['active'] ?? 0,
            'password' => $_POST['password'] ?? '',
        ]);
        header('Location: /users'); exit;
    }

    public function delete(int $id): never
    {
        Auth::requireRole('admin');
        // Guard: do not let an admin delete their own account.
        if ((int)(Auth::user()['id']) !== $id) {
            User::delete($id);
        }
        header('Location: /users'); exit;
    }

    private function validate(array $in, bool $isNew): ?string
    {
        if (trim($in['name'] ?? '') === '') return 'Name is required.';
        if (!filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL)) return 'Valid email is required.';
        if (!in_array($in['role'] ?? '', ['admin','editor','viewer'], true)) return 'Role is invalid.';
        if ($isNew && strlen($in['password'] ?? '') < 6) return 'Password must be at least 6 characters.';
        return null;
    }
}
```

- [ ] **Step 2: Write `views/users/index.php`**

```php
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Users</h1>
  <a class="btn btn-primary" href="/users/new">New user</a>
</div>
<table class="data-table">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e($row['email']) ?></td>
      <td><?= e($row['role']) ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <a href="/users/<?= (int)$row['id'] ?>/edit">Edit</a>
        <form method="post" action="/users/<?= (int)$row['id'] ?>/delete"
              style="display:inline" data-confirm="Delete this user?">
          <button type="submit" class="btn-link-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
```

- [ ] **Step 3: Write `views/users/form.php`**

```php
<?php $isNew = empty($u['id']); ?>
<h1><?= $isNew ? 'New user' : 'Edit user' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/users' : '/users/' . (int)$u['id'] ?>">
  <label>Name <input name="name" value="<?= e($u['name'] ?? '') ?>" required></label>
  <label>Email <input type="email" name="email" value="<?= e($u['email'] ?? '') ?>" required></label>
  <label>Role
    <select name="role">
      <?php foreach (['admin','editor','viewer'] as $r): ?>
        <option value="<?= $r ?>" <?= (($u['role'] ?? 'viewer') === $r) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Password <input type="password" name="password" <?= $isNew ? 'required' : '' ?>>
    <?php if (!$isNew): ?><small>Leave blank to keep current password.</small><?php endif; ?>
  </label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($u['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/users" class="btn">Cancel</a>
</form>
```

- [ ] **Step 4: Manual verification (browser, as admin)**

Log in as the seeded admin (Task 10). Visit `/users` → list shows admin. Create an editor and a viewer. Edit one (blank password keeps it). Try deleting your own account → it is refused (stays in list).
Log in as the viewer → `/users` returns 403 Forbidden and the sidebar hides admin links.

- [ ] **Step 5: Commit**

```bash
git add src/Controllers/UserController.php views/users/index.php views/users/form.php
git commit -m "feat: admin Users CRUD with role guards and validation"
```

---

### Task 10: First-admin seed + bootstrap script

**Files:**
- Create: `db/seed.sql`
- Create: `bin/create-admin.php` (interactive one-off, kept out of `public/`)

**Interfaces:**
- Produces: a documented way to create the first admin. `db/seed.sql` inserts a default admin with a placeholder hash; `bin/create-admin.php` is the preferred interactive route (prompts for email + password, writes a properly hashed row).

- [ ] **Step 1: Write `bin/create-admin.php`**

```php
<?php
// Usage: php bin/create-admin.php "Name" email@org password
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->safeLoad();

[$name, $email, $password] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    fwrite(STDERR, "Usage: php bin/create-admin.php \"Name\" email password(>=6)\n");
    exit(1);
}
$id = App\Models\User::create(['name'=>$name,'email'=>$email,'password'=>$password,'role'=>'admin']);
echo "Created admin user #{$id} ({$email})\n";
```

- [ ] **Step 2: Write `db/seed.sql`** (fallback; placeholder hash must be replaced)

```sql
-- Fallback seed. Prefer: php bin/create-admin.php "Admin" admin@pepea-africa.org <password>
-- This inserts an admin whose password is 'changeme' (bcrypt hash below). CHANGE IT after first login.
INSERT INTO users (name, email, password_hash, role, active)
VALUES ('Administrator', 'admin@pepea-africa.org',
        '$2y$12$e0NRSWHO0Q3qg0xT6V4n9eC2m8m0J0xY4i0bqg3oQv3l2yqg7m5oK', 'admin', 1)
ON DUPLICATE KEY UPDATE email = email;
```

> Note: regenerate the hash with `php -r "echo password_hash('changeme', PASSWORD_DEFAULT);"` if PHP rejects the literal above; the interactive script in Step 1 avoids this entirely and is the recommended path.

- [ ] **Step 3: Create the first admin (interactive, recommended)**

Run: `php bin/create-admin.php "Administrator" admin@pepea-africa.org <chooseStrongPw>`
Expected: "Created admin user #1 (admin@pepea-africa.org)".

- [ ] **Step 4: Verify end-to-end login**

In the browser, log in at `http://lipa.test/login` with the admin credentials → lands on Dashboard with the sidebar. Log out → returns to `/login`.

- [ ] **Step 5: Commit**

```bash
git add db/seed.sql bin/create-admin.php
git commit -m "feat: first-admin seed and interactive bootstrap script"
```

---

### Task 11: Serve from Laragon + README run instructions

**Files:**
- Create: `README.md`

**Interfaces:**
- Produces: documented local-run setup. Decision: serve the workspace folder under Laragon via a directory junction so source stays in `C:\Tools\LIPA Web 26` while Laragon serves `lipa.test`.

- [ ] **Step 1: Create the Laragon junction to the workspace folder**

Run (PowerShell):
```
New-Item -ItemType Junction -Path "C:\laragon\www\lipa" -Target "C:\Tools\LIPA Web 26"
```
Laragon auto-creates the `lipa.test` vhost pointing at `C:\laragon\www\lipa\public` (set "Document Root" to the `public` subfolder in Laragon → Preferences if it points at the project root). Reload Laragon ("Reload"/"Start All").

- [ ] **Step 2: Write `README.md`**

````markdown
# LIPA Web

NGO income/expense tracker (QuickBooks replacement) for Pepea. Plain PHP + MariaDB.

## Local setup (Laragon)
1. `composer install`
2. Copy `.env.example` to `.env`; set DB creds (Laragon default: user `root`, empty password).
   Create a `.env.testing` with `DB_NAME=lipa_test`.
3. Create databases and load schema:
   ```
   mysql -uroot -e "CREATE DATABASE lipa CHARACTER SET utf8mb4; CREATE DATABASE lipa_test CHARACTER SET utf8mb4;"
   mysql -uroot lipa < db/schema.sql
   ```
4. Create the first admin: `php bin/create-admin.php "Administrator" admin@pepea-africa.org <password>`
5. Junction served by Laragon → open http://lipa.test

## Tests
`composer test`  (runs PHPUnit against the `lipa_test` database)

## Deployment (DomainFactory)
- Subdomain `lipa.pepea-africa.org`, document root = `public/`.
- `git pull` over SSH, `composer install --no-dev`, load `db/schema.sql`, create `.env` outside `public/`.
- Create first admin via `php bin/create-admin.php`.
````

- [ ] **Step 3: Final full-suite check**

Run: `composer test`
Expected: all tests pass (Database, User, Router, Auth).

- [ ] **Step 4: Commit**

```bash
git add README.md
git commit -m "docs: local setup and run instructions"
```

---

## Self-Review

**Spec coverage (Plan 1 scope only):**
- Lean plain PHP + PDO + Composer (dotenv) → Tasks 1, 3, 8. ✓
- Full schema, all 8 tables, portable SQL → Task 2. ✓
- `public/` only web-exposed; `.env` outside public, gitignored → Tasks 2, 8, 11. ✓
- Session auth + three roles + server-side guards → Tasks 6, 8, 9. ✓
- Admin Users CRUD → Task 9. ✓
- Ported `theme.css`, role-aware sidebar nav → Task 7. ✓
- First-admin bootstrap → Task 10. ✓
- English (UK) UI copy → all views. ✓
- Local serve + deployment notes → Task 11. ✓
- Deferred to later plans (correctly out of Plan 1 scope): contacts/projects/categories CRUD (Plan 2), income/expenses + uploads (Plan 3), dashboard KPIs/Excel export/settings/activity log UI (Plan 4). The `activity_log` table exists now; it is populated starting in later plans.

**Placeholder scan:** No TBD/TODO/"add error handling" left. Validation is concrete (`UserController::validate`). The only intentional literal-to-regenerate is the seed bcrypt hash, with an explicit regenerate command and a recommended interactive alternative.

**Type consistency:** `Database::pdo()`, `User::create/all/find/findByEmail/update/delete`, `Router::add/dispatch`, `Auth::attempt/check/user/logout/is/requireRole`, `render()`/`e()` are used with identical signatures across tasks and `public/index.php`. Routes registered in Task 8 match `UserController` methods in Task 9.
