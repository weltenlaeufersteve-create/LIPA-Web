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

if (!function_exists('App\\ini_bytes')) {
    /** Convert a PHP ini size string ("8M", "64M", "2G", "512K", "8388608") to bytes. */
    function ini_bytes(string $val): int
    {
        $val = trim($val);
        if ($val === '') { return 0; }
        $num = (int)$val;
        switch (strtolower($val[strlen($val) - 1])) {
            case 'g': $num *= 1024; // fall through
            case 'm': $num *= 1024; // fall through
            case 'k': $num *= 1024;
        }
        return $num;
    }
}

if (!function_exists('App\\role_label')) {
    /** Display label for a user role. Display-only — does not change the role enum or any guard. */
    function role_label(string $role): string
    {
        return ['admin' => 'Admin', 'editor' => 'Coordinator', 'viewer' => 'Accountant'][$role] ?? ucfirst($role);
    }
}
