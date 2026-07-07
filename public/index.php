<?php
declare(strict_types=1);

session_start();
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/views/layout.php'; // defines render(), e()

use App\Router;
use App\NotFoundException;
use App\ForbiddenException;
use App\Auth;
use App\Csrf;
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
use App\Controllers\ActivitiesController;
use App\Controllers\BudgetController;
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
$router->add('GET', '/income/:id/receipt/print',   fn($p) => (new IncomeController())->receiptPrint((int)$p['id']));
$router->add('GET', '/expenses/:id/receipt/print', fn($p) => (new ExpenseController())->receiptPrint((int)$p['id']));

// Settings (admin)
$router->add('GET',  '/settings', fn() => (new SettingController())->index());
$router->add('POST', '/settings', fn() => (new SettingController())->save());

// Reports + Excel export
$router->add('GET', '/reports',        fn() => (new ReportController())->index());
$router->add('GET', '/reports/export', fn() => (new ReportController())->export());
$router->add('GET', '/reports/statement', fn() => (new ReportController())->statement());
$router->add('GET', '/reports/org-statement', fn() => (new ReportController())->orgStatement());
$router->add('GET', '/reports/activity-report', fn() => (new ReportController())->activityReport());
$router->add('GET', '/reports/sankey', fn() => (new ReportController())->sankey());

// Budget scenarios (planning layer — never touches the cashbook)
$router->add('GET',  '/budget',            fn() => (new BudgetController())->index());
$router->add('GET',  '/budget/new',        fn() => (new BudgetController())->create());
$router->add('POST', '/budget',            fn() => (new BudgetController())->store());
$router->add('GET',  '/budget/:id',        fn($p) => (new BudgetController())->show((int)$p['id']));
$router->add('POST', '/budget/:id',        fn($p) => (new BudgetController())->update((int)$p['id']));
$router->add('POST', '/budget/:id/delete', fn($p) => (new BudgetController())->delete((int)$p['id']));
$router->add('GET',  '/budget/:id/print',  fn($p) => (new BudgetController())->print((int)$p['id']));

// Activities
$router->add('GET',  '/activities',                       fn() => (new ActivitiesController())->index());
$router->add('GET',  '/activities/new',                   fn() => (new ActivitiesController())->create());
$router->add('POST', '/activities',                       fn() => (new ActivitiesController())->store());
$router->add('GET',  '/activities/:id/edit',              fn($p) => (new ActivitiesController())->edit((int)$p['id']));
$router->add('POST', '/activities/:id',                   fn($p) => (new ActivitiesController())->update((int)$p['id']));
$router->add('POST', '/activities/:id/delete',            fn($p) => (new ActivitiesController())->delete((int)$p['id']));
$router->add('GET',  '/activities/:id/photo/:pid',        fn($p) => (new ActivitiesController())->photo((int)$p['id'], (int)$p['pid']));
$router->add('POST', '/activities/:id/photo/:pid/delete', fn($p) => (new ActivitiesController())->deletePhoto((int)$p['id'], (int)$p['pid']));

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
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // A POST that arrives with a body but empty $_POST means PHP discarded it for
        // exceeding post_max_size (typical with several large photos). Show a clear
        // message instead of the misleading CSRF "Forbidden".
        if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413);
            $max = htmlspecialchars((string)ini_get('post_max_size'), ENT_QUOTES);
            echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
               . '<div style="font-family:system-ui,sans-serif;max-width:520px;margin:12vh auto;padding:24px;text-align:center">'
               . '<h1 style="font-size:1.2rem">Upload too large</h1>'
               . '<p>The photos you selected exceed the server limit (max ' . $max . ' per upload). '
               . 'Please add fewer or smaller photos and try again.</p>'
               . '<p><a href="javascript:history.back()">&larr; Go back</a></p></div>';
            exit;
        }
        Csrf::check();
    }
    echo $router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
} catch (ForbiddenException $ex) {
    if (!Auth::check()) { header('Location: /login'); exit; }
    http_response_code(403);
    echo 'Forbidden';
} catch (NotFoundException $ex) {
    http_response_code(404);
    echo 'Not found';
}
