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
use App\Controllers\ContactController;
use App\Controllers\ProjectController;
use App\Controllers\CategoryController;
use App\Controllers\IncomeController;
use App\Controllers\ExpenseController;
use App\Controllers\SettingController;
use App\Controllers\ReportController;
use App\Controllers\ActivityController;
use App\Controllers\AccountController;
use App\Controllers\TransferController;

$root = dirname(__DIR__);
$dotenv = Dotenv\Dotenv::createImmutable($root);
$dotenv->safeLoad();

$router = new Router();

// Controllers are instantiated lazily inside each closure so a single
// missing controller never breaks unrelated routes.
$router->add('GET',  '/login',  fn() => (new AuthController())->showLogin());
$router->add('POST', '/login',  fn() => (new AuthController())->login());
$router->add('POST', '/logout', fn() => (new AuthController())->logout());
$router->add('GET',  '/',       fn() => (new DashboardController())->index());

// Users (admin)
$router->add('GET',  '/users',            fn() => (new UserController())->index());
$router->add('GET',  '/users/new',        fn() => (new UserController())->create());
$router->add('POST', '/users',            fn() => (new UserController())->store());
$router->add('GET',  '/users/:id/edit',   fn($p) => (new UserController())->edit((int)$p['id']));
$router->add('POST', '/users/:id',        fn($p) => (new UserController())->update((int)$p['id']));
$router->add('POST', '/users/:id/delete', fn($p) => (new UserController())->delete((int)$p['id']));

// Contacts (donors + vendors)
$router->add('GET',  '/contacts',            fn() => (new ContactController())->index());
$router->add('GET',  '/contacts/new',        fn() => (new ContactController())->create());
$router->add('POST', '/contacts',            fn() => (new ContactController())->store());
$router->add('GET',  '/contacts/:id/edit',   fn($p) => (new ContactController())->edit((int)$p['id']));
$router->add('POST', '/contacts/:id',        fn($p) => (new ContactController())->update((int)$p['id']));
$router->add('POST', '/contacts/:id/delete', fn($p) => (new ContactController())->delete((int)$p['id']));

// Projects
$router->add('GET',  '/projects',            fn() => (new ProjectController())->index());
$router->add('GET',  '/projects/new',        fn() => (new ProjectController())->create());
$router->add('POST', '/projects',            fn() => (new ProjectController())->store());
$router->add('GET',  '/projects/:id/edit',   fn($p) => (new ProjectController())->edit((int)$p['id']));
$router->add('POST', '/projects/:id',        fn($p) => (new ProjectController())->update((int)$p['id']));
$router->add('POST', '/projects/:id/delete', fn($p) => (new ProjectController())->delete((int)$p['id']));

// Categories (admin)
$router->add('GET',  '/categories',            fn() => (new CategoryController())->index());
$router->add('GET',  '/categories/new',        fn() => (new CategoryController())->create());
$router->add('POST', '/categories',            fn() => (new CategoryController())->store());
$router->add('GET',  '/categories/:id/edit',   fn($p) => (new CategoryController())->edit((int)$p['id']));
$router->add('POST', '/categories/:id',        fn($p) => (new CategoryController())->update((int)$p['id']));
$router->add('POST', '/categories/:id/delete', fn($p) => (new CategoryController())->delete((int)$p['id']));

// Income
$router->add('GET',  '/income',            fn() => (new IncomeController())->index());
$router->add('GET',  '/income/new',        fn() => (new IncomeController())->create());
$router->add('POST', '/income',            fn() => (new IncomeController())->store());
$router->add('GET',  '/income/:id/edit',   fn($p) => (new IncomeController())->edit((int)$p['id']));
$router->add('POST', '/income/:id',        fn($p) => (new IncomeController())->update((int)$p['id']));
$router->add('POST', '/income/:id/delete', fn($p) => (new IncomeController())->delete((int)$p['id']));

// Expenses
$router->add('GET',  '/expenses',            fn() => (new ExpenseController())->index());
$router->add('GET',  '/expenses/new',        fn() => (new ExpenseController())->create());
$router->add('POST', '/expenses',            fn() => (new ExpenseController())->store());
$router->add('GET',  '/expenses/:id/edit',   fn($p) => (new ExpenseController())->edit((int)$p['id']));
$router->add('POST', '/expenses/:id',        fn($p) => (new ExpenseController())->update((int)$p['id']));
$router->add('POST', '/expenses/:id/delete', fn($p) => (new ExpenseController())->delete((int)$p['id']));

// Receipt downloads (authenticated, all roles)
$router->add('GET', '/income/:id/receipt',   fn($p) => (new IncomeController())->receipt((int)$p['id']));
$router->add('GET', '/expenses/:id/receipt', fn($p) => (new ExpenseController())->receipt((int)$p['id']));

// Settings (admin)
$router->add('GET',  '/settings', fn() => (new SettingController())->index());
$router->add('POST', '/settings', fn() => (new SettingController())->save());

// Reports + Excel export
$router->add('GET', '/reports',        fn() => (new ReportController())->index());
$router->add('GET', '/reports/export', fn() => (new ReportController())->export());
$router->add('GET', '/reports/statement', fn() => (new ReportController())->statement());
$router->add('GET', '/reports/org-statement', fn() => (new ReportController())->orgStatement());
$router->add('GET', '/reports/activity-report', fn() => (new ReportController())->activityReport());

// Activities
$router->add('GET',  '/activities',                       fn() => (new ActivityController())->index());
$router->add('GET',  '/activities/new',                   fn() => (new ActivityController())->create());
$router->add('POST', '/activities',                       fn() => (new ActivityController())->store());
$router->add('GET',  '/activities/:id/edit',              fn($p) => (new ActivityController())->edit((int)$p['id']));
$router->add('POST', '/activities/:id',                   fn($p) => (new ActivityController())->update((int)$p['id']));
$router->add('POST', '/activities/:id/delete',            fn($p) => (new ActivityController())->delete((int)$p['id']));
$router->add('GET',  '/activities/:id/photo/:pid',        fn($p) => (new ActivityController())->photo((int)$p['id'], (int)$p['pid']));
$router->add('POST', '/activities/:id/photo/:pid/delete', fn($p) => (new ActivityController())->deletePhoto((int)$p['id'], (int)$p['pid']));

// Activity log (admin, viewer)
$router->add('GET', '/activity', fn() => (new ActivityController())->index());

// Accounts (admin)
$router->add('GET',  '/accounts',            fn() => (new AccountController())->index());
$router->add('GET',  '/accounts/new',        fn() => (new AccountController())->create());
$router->add('POST', '/accounts',            fn() => (new AccountController())->store());
$router->add('GET',  '/accounts/:id/edit',   fn($p) => (new AccountController())->edit((int)$p['id']));
$router->add('POST', '/accounts/:id',        fn($p) => (new AccountController())->update((int)$p['id']));
$router->add('POST', '/accounts/:id/delete', fn($p) => (new AccountController())->delete((int)$p['id']));

// Transfers (view: all; write: admin/editor)
$router->add('GET',  '/transfers',            fn() => (new TransferController())->index());
$router->add('GET',  '/transfers/new',        fn() => (new TransferController())->create());
$router->add('POST', '/transfers',            fn() => (new TransferController())->store());
$router->add('GET',  '/transfers/:id/edit',   fn($p) => (new TransferController())->edit((int)$p['id']));
$router->add('POST', '/transfers/:id',        fn($p) => (new TransferController())->update((int)$p['id']));
$router->add('POST', '/transfers/:id/delete', fn($p) => (new TransferController())->delete((int)$p['id']));

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
