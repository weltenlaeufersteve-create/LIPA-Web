<?php
namespace App\Models;

use App\Database;

final class ActivityItem
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO activities (date, title, description, project_id, created_by)
             VALUES (:date, :title, :descr, :project_id, :created_by)'
        );
        $stmt->execute([
            ':date'=>$data['date'], ':title'=>$data['title'],
            ':descr'=>$data['description'] ?: null,
            ':project_id'=>$data['project_id'] ?: null,
            ':created_by'=>$data['created_by'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE activities SET date=:date, title=:title, description=:descr, project_id=:project_id WHERE id=:id'
        );
        $stmt->execute([
            ':date'=>$data['date'], ':title'=>$data['title'],
            ':descr'=>$data['description'] ?: null,
            ':project_id'=>$data['project_id'] ?: null, ':id'=>$id,
        ]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM activities WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        $cond = []; $params = [];
        if (!empty($filters['date_from'])) { $cond[] = 'a.date >= :date_from'; $params[':date_from'] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $cond[] = 'a.date <= :date_to';   $params[':date_to']   = $filters['date_to']; }
        if (!empty($filters['project_id'])) { $cond[] = 'a.project_id = :project_id'; $params[':project_id'] = (int)$filters['project_id']; }
        $where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';
        $sql = "SELECT a.*, p.name AS project_name
                FROM activities a LEFT JOIN projects p ON p.id = a.project_id
                {$where} ORDER BY a.date DESC, a.id DESC";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM activities WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }

    // ---- photos ----
    public static function photos(int $id): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM activity_photos WHERE activity_id = :id ORDER BY id ASC');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetchAll();
    }

    public static function addPhoto(int $id, string $filename): void
    {
        $stmt = Database::pdo()->prepare('INSERT INTO activity_photos (activity_id, filename) VALUES (:id, :f)');
        $stmt->execute([':id'=>$id, ':f'=>$filename]);
    }

    public static function findPhoto(int $photoId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM activity_photos WHERE id = :id');
        $stmt->execute([':id'=>$photoId]);
        return $stmt->fetch() ?: null;
    }

    public static function deletePhoto(int $photoId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM activity_photos WHERE id = :id');
        $stmt->execute([':id'=>$photoId]);
    }

    public static function photoCount(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM activity_photos WHERE activity_id = :id');
        $stmt->execute([':id'=>$id]);
        return (int)$stmt->fetchColumn();
    }

    // ---- linked expenses ----
    public static function expenses(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.*, c.name AS contact_name, cat.name AS category_name
             FROM expenses e
             LEFT JOIN contacts c ON c.id = e.contact_id
             LEFT JOIN categories cat ON cat.id = e.category_id
             WHERE e.activity_id = :id ORDER BY e.date, e.id'
        );
        $stmt->execute([':id'=>$id]);
        return $stmt->fetchAll();
    }

    public static function cost(int $id): float
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount_tzs),0) FROM expenses WHERE activity_id = :id');
        $stmt->execute([':id'=>$id]);
        return (float)$stmt->fetchColumn();
    }

    public static function setExpenses(int $id, array $expenseIds): void
    {
        $pdo = Database::pdo();
        $ids = array_values(array_unique(array_map('intval', $expenseIds)));
        if ($ids) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            // unlink expenses currently on this activity that are no longer selected
            $stmt = $pdo->prepare("UPDATE expenses SET activity_id = NULL WHERE activity_id = ? AND id NOT IN ($ph)");
            $stmt->execute(array_merge([$id], $ids));
            // link selected expenses (only those unassigned or already on this activity)
            $stmt = $pdo->prepare("UPDATE expenses SET activity_id = ? WHERE id IN ($ph) AND (activity_id IS NULL OR activity_id = ?)");
            $stmt->execute(array_merge([$id], $ids, [$id]));
        } else {
            $stmt = $pdo->prepare('UPDATE expenses SET activity_id = NULL WHERE activity_id = ?');
            $stmt->execute([$id]);
        }
    }
}
