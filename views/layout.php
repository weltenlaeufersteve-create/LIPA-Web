<?php
use App\Auth;

if (!function_exists('e')) {
    function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}

if (!function_exists('asset')) {
    // Versioned asset URL (cache-bust on file change via mtime).
    function asset(string $path): string {
        $file = dirname(__DIR__) . '/public' . $path;
        $v = is_file($file) ? filemtime($file) : '1';
        return $path . '?v=' . $v;
    }
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
