<?php
namespace App;

use App\Models\User;
use App\Models\Activity;

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
        Activity::log((int)$user['id'], 'login', 'user', (int)$user['id'], 'Logged in');
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
