<?php
namespace App;

const VERSION = '1.0.0';

if (!function_exists('App\\hex_color')) {
    /** Return $v if it is a #rrggbb hex colour, else the fallback (guards against injection/bad input). */
    function hex_color(?string $v, string $fallback = '#C0175B'): string
    {
        return (is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ? $v : $fallback;
    }
}

if (!function_exists('App\\role_label')) {
    /** Display label for a user role. Display-only — does not change the role enum or any guard. */
    function role_label(string $role): string
    {
        return ['admin' => 'Admin', 'editor' => 'Coordinator', 'viewer' => 'Accountant'][$role] ?? ucfirst($role);
    }
}
