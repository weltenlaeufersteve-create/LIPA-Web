<?php
namespace App\Models;

use App\Database;

final class Project
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO projects (name, code, description, active)
             VALUES (:name, :code, :description, 1)'
        );
        $stmt->execute([
            ':name'=>$data['name'], ':code'=>$data['code'] ?: null,
            ':description'=>$data['description'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM projects ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM projects WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE projects SET name=:name, code=:code, description=:description, active=:active WHERE id=:id'
        );
        $stmt->execute([
            ':name'=>$data['name'], ':code'=>$data['code'] ?: null,
            ':description'=>$data['description'] ?: null,
            ':active'=>(int)$data['active'], ':id'=>$id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM projects WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
