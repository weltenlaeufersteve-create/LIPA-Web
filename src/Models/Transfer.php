<?php
namespace App\Models;

use App\Database;

final class Transfer
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO transfers (date, from_account_id, to_account_id, amount_tzs, description, created_by)
             VALUES (:date, :from, :to, :amt, :descr, :by)'
        );
        $stmt->execute([
            ':date'=>$data['date'], ':from'=>$data['from_account_id'] ?: null,
            ':to'=>$data['to_account_id'] ?: null, ':amt'=>(float)$data['amount_tzs'],
            ':descr'=>$data['description'] ?: null, ':by'=>$data['created_by'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE transfers SET date=:date, from_account_id=:from, to_account_id=:to,
             amount_tzs=:amt, description=:descr WHERE id=:id'
        );
        $stmt->execute([
            ':date'=>$data['date'], ':from'=>$data['from_account_id'] ?: null,
            ':to'=>$data['to_account_id'] ?: null, ':amt'=>(float)$data['amount_tzs'],
            ':descr'=>$data['description'] ?: null, ':id'=>$id,
        ]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM transfers WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function all(array $filters = []): array
    {
        $cond = []; $params = [];
        if (!empty($filters['date_from'])) { $cond[] = 't.date >= :date_from'; $params[':date_from'] = $filters['date_from']; }
        if (!empty($filters['date_to']))   { $cond[] = 't.date <= :date_to';   $params[':date_to']   = $filters['date_to']; }
        $where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';
        $sql = "SELECT t.*, f.name AS from_name, d.name AS to_name
                FROM transfers t
                LEFT JOIN accounts f ON f.id = t.from_account_id
                LEFT JOIN accounts d ON d.id = t.to_account_id
                {$where} ORDER BY t.date DESC, t.id DESC";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM transfers WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
