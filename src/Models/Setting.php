<?php
namespace App\Models;

use App\Database;

final class Setting
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM settings WHERE setting_key = :k');
        $stmt->execute([':k'=>$key]);
        $val = $stmt->fetchColumn();
        return $val === false ? $default : $val;
    }

    public static function set(string $key, ?string $value): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = :v2'
        );
        $stmt->execute([':k'=>$key, ':v'=>$value, ':v2'=>$value]);
    }

    public static function all(): array
    {
        $rows = Database::pdo()->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[$r['setting_key']] = $r['setting_value']; }
        return $out;
    }
}
