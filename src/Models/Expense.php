<?php
namespace App\Models;

use App\Database;

final class Expense
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO expenses
             (date, contact_id, project_id, category_id, description, amount_tzs, reference, receipt_path, notes, created_by)
             VALUES
             (:date, :contact_id, :project_id, :category_id, :description, :amount_tzs, :reference, :receipt_path, :notes, :created_by)'
        );
        $stmt->execute(self::bind($data) + [':created_by'=>$data['created_by'] ?: null]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE expenses SET date=:date, contact_id=:contact_id, project_id=:project_id,
             category_id=:category_id, description=:description, amount_tzs=:amount_tzs,
             reference=:reference, notes=:notes WHERE id=:id'
        );
        $params = self::bind($data);
        unset($params[':receipt_path']);
        $stmt->execute($params + [':id'=>$id]);
    }

    private static function bind(array $d): array
    {
        return [
            ':date'=>$d['date'],
            ':contact_id'=>$d['contact_id'] ?: null,
            ':project_id'=>$d['project_id'] ?: null,
            ':category_id'=>$d['category_id'] ?: null,
            ':description'=>$d['description'] ?: null,
            ':amount_tzs'=>$d['amount_tzs'],
            ':reference'=>$d['reference'] ?: null,
            ':receipt_path'=>$d['receipt_path'] ?? null,
            ':notes'=>$d['notes'] ?: null,
        ];
    }

    public static function setReceipt(int $id, ?string $path): void
    {
        $stmt = Database::pdo()->prepare('UPDATE expenses SET receipt_path=:p WHERE id=:id');
        $stmt->execute([':p'=>$path, ':id'=>$id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM expenses WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT e.*, c.name AS contact_name, p.name AS project_name, cat.name AS category_name
                FROM expenses e
                LEFT JOIN contacts c   ON c.id = e.contact_id
                LEFT JOIN projects p   ON p.id = e.project_id
                LEFT JOIN categories cat ON cat.id = e.category_id
                ' . $where . ' ORDER BY e.date DESC, e.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function totalTzs(array $filters = []): float
    {
        [$where, $params] = self::whereClause($filters);
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount_tzs),0) FROM expenses e ' . $where);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /** @return array{0:string,1:array} */
    private static function whereClause(array $f): array
    {
        $cond = []; $params = [];
        if (!empty($f['date_from'])) { $cond[] = 'e.date >= :date_from'; $params[':date_from'] = $f['date_from']; }
        if (!empty($f['date_to']))   { $cond[] = 'e.date <= :date_to';   $params[':date_to']   = $f['date_to']; }
        if (!empty($f['project_id']))  { $cond[] = 'e.project_id = :project_id';   $params[':project_id']  = (int)$f['project_id']; }
        if (!empty($f['category_id'])) { $cond[] = 'e.category_id = :category_id'; $params[':category_id'] = (int)$f['category_id']; }
        return [$cond ? 'WHERE ' . implode(' AND ', $cond) : '', $params];
    }

    public static function byCategory(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT cat.name AS name, COALESCE(SUM(e.amount_tzs),0) AS total
                FROM expenses e LEFT JOIN categories cat ON cat.id = e.category_id
                ' . $where . ' GROUP BY e.category_id, cat.name ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byProject(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT p.id AS id, p.name AS name, COALESCE(SUM(e.amount_tzs),0) AS total
                FROM expenses e LEFT JOIN projects p ON p.id = e.project_id
                ' . $where . ' GROUP BY e.project_id, p.id, p.name ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM expenses WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
