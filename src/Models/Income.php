<?php
namespace App\Models;

use App\Database;

final class Income
{
    public static function tzsValue(float $amount, float $rate): float
    {
        return round($amount * $rate, 2);
    }

    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO income
             (date, contact_id, project_id, category_id, description, currency,
              amount_original, exchange_rate, amount_tzs, reference, receipt_path, notes, created_by)
             VALUES
             (:date, :contact_id, :project_id, :category_id, :description, :currency,
              :amount_original, :exchange_rate, :amount_tzs, :reference, :receipt_path, :notes, :created_by)'
        );
        $stmt->execute(self::bind($data) + [':created_by' => $data['created_by'] ?: null]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE income SET date=:date, contact_id=:contact_id, project_id=:project_id,
             category_id=:category_id, description=:description, currency=:currency,
             amount_original=:amount_original, exchange_rate=:exchange_rate, amount_tzs=:amount_tzs,
             reference=:reference, notes=:notes WHERE id=:id'
        );
        $params = self::bind($data);
        unset($params[':receipt_path']); // receipts handled by setReceipt()
        $stmt->execute($params + [':id' => $id]);
    }

    private static function bind(array $d): array
    {
        return [
            ':date'=>$d['date'],
            ':contact_id'=>$d['contact_id'] ?: null,
            ':project_id'=>$d['project_id'] ?: null,
            ':category_id'=>$d['category_id'] ?: null,
            ':description'=>$d['description'] ?: null,
            ':currency'=>$d['currency'],
            ':amount_original'=>$d['amount_original'],
            ':exchange_rate'=>$d['exchange_rate'],
            ':amount_tzs'=>$d['amount_tzs'],
            ':reference'=>$d['reference'] ?: null,
            ':receipt_path'=>$d['receipt_path'] ?? null,
            ':notes'=>$d['notes'] ?: null,
        ];
    }

    public static function setReceipt(int $id, ?string $path): void
    {
        $stmt = Database::pdo()->prepare('UPDATE income SET receipt_path=:p WHERE id=:id');
        $stmt->execute([':p'=>$path, ':id'=>$id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM income WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT i.*, c.name AS contact_name, p.name AS project_name, cat.name AS category_name
                FROM income i
                LEFT JOIN contacts c   ON c.id = i.contact_id
                LEFT JOIN projects p   ON p.id = i.project_id
                LEFT JOIN categories cat ON cat.id = i.category_id
                ' . $where . ' ORDER BY i.date DESC, i.id DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function totalTzs(array $filters = []): float
    {
        [$where, $params] = self::whereClause($filters);
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount_tzs),0) FROM income i ' . $where);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /** @return array{0:string,1:array} */
    private static function whereClause(array $f): array
    {
        $cond = []; $params = [];
        if (!empty($f['date_from'])) { $cond[] = 'i.date >= :date_from'; $params[':date_from'] = $f['date_from']; }
        if (!empty($f['date_to']))   { $cond[] = 'i.date <= :date_to';   $params[':date_to']   = $f['date_to']; }
        if (!empty($f['project_id']))  { $cond[] = 'i.project_id = :project_id';   $params[':project_id']  = (int)$f['project_id']; }
        if (!empty($f['category_id'])) { $cond[] = 'i.category_id = :category_id'; $params[':category_id'] = (int)$f['category_id']; }
        return [$cond ? 'WHERE ' . implode(' AND ', $cond) : '', $params];
    }

    public static function byCategory(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT cat.name AS name, COALESCE(SUM(i.amount_tzs),0) AS total
                FROM income i LEFT JOIN categories cat ON cat.id = i.category_id
                ' . $where . ' GROUP BY i.category_id, cat.name ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function byProject(array $filters = []): array
    {
        [$where, $params] = self::whereClause($filters);
        $sql = 'SELECT p.id AS id, p.name AS name, COALESCE(SUM(i.amount_tzs),0) AS total
                FROM income i LEFT JOIN projects p ON p.id = i.project_id
                ' . $where . ' GROUP BY i.project_id, p.id, p.name ORDER BY total DESC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM income WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
