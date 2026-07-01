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

if (!function_exists('csrf_field')) {
    // Hidden CSRF token for a form. Note: render() also injects one into every
    // POST form automatically, so views rarely need to call this directly.
    function csrf_field(): string { return App\Csrf::field(); }
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
        // Inject a CSRF token into every rendered POST <form> (matches the
        // central Csrf::check() in the front controller).
        return App\Csrf::inject(ob_get_clean());
    }
}
