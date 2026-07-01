<?php
namespace App;

/**
 * CSRF protection (synchroniser-token pattern).
 *
 * A single random token is stored in the session and required on every POST.
 * The token is injected into every rendered POST <form> by Csrf::inject()
 * (called from render()), and verified centrally in the front controller by
 * Csrf::check() before any POST route runs.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function check(): void
    {
        $sent = $_POST['_csrf'] ?? '';
        $real = $_SESSION['csrf_token'] ?? '';
        if (!is_string($sent) || $real === '' || !hash_equals($real, $sent)) {
            throw new ForbiddenException('Invalid or missing CSRF token.');
        }
    }

    /** Insert the hidden token field immediately after every POST <form> tag. */
    public static function inject(string $html): string
    {
        $field = self::field();
        return preg_replace_callback(
            '/<form\b[^>]*\bmethod=(["\'])post\1[^>]*>/i',
            static fn(array $m): string => $m[0] . $field,
            $html
        ) ?? $html;
    }
}
