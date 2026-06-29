<?php
namespace App\Models;

use App\Database;

final class Category
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO categories (type, name, sort_order, active)
             VALUES (:type, :name, :sort_order, 1)'
        );
        $stmt->execute([
            ':type'=>$data['type'], ':name'=>$data['name'],
            ':sort_order'=>(int)($data['sort_order'] ?? 0),
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function all(?string $type = null): array
    {
        $pdo = Database::pdo();
        if ($type !== null) {
            $stmt = $pdo->prepare('SELECT * FROM categories WHERE type = :type ORDER BY sort_order, name');
            $stmt->execute([':type'=>$type]);
            return $stmt->fetchAll();
        }
        return $pdo->query('SELECT * FROM categories ORDER BY type, sort_order, name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM categories WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE categories SET type=:type, name=:name, sort_order=:sort_order, active=:active WHERE id=:id'
        );
        $stmt->execute([
            ':type'=>$data['type'], ':name'=>$data['name'],
            ':sort_order'=>(int)($data['sort_order'] ?? 0),
            ':active'=>(int)$data['active'], ':id'=>$id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
