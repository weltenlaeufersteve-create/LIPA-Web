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
