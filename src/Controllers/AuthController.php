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
