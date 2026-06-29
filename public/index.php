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
