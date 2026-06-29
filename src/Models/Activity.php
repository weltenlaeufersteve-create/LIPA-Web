<?php
namespace App\Models;

use App\Database;

final class Activity
{
    public static function log(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $description = null): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO activity_log (user_id, action, entity_type, entity_id, description)
             VALUES (:uid, :action, :etype, :eid, :descr)'
        );
        $stmt->execute([
            ':uid'=>$userId, ':action'=>$action, ':etype'=>$entityType,
            ':eid'=>$entityId, ':descr'=>$description,
        ]);
        // Prune to newest 1000.
        $pdo->exec('DELETE FROM activity_log WHERE id <= (
            SELECT id FROM (
                SELECT id FROM activity_log ORDER BY id DESC LIMIT 1 OFFSET 1000
            ) t)');
    }

    public static function recent(int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT a.*, u.name AS user_name
             FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
