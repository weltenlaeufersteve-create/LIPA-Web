<?php
namespace App\Models;

use App\Database;

final class User
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, active)
             VALUES (:name, :email, :hash, :role, 1)'
        );
        $stmt->execute([
            ':name'  => $data['name'],
            ':email' => $data['email'],
            ':hash'  => password_hash($data['password'], PASSWORD_DEFAULT),
            ':role'  => $data['role'],
        ]);
        return (int) $pdo->lastInsertId();
    }

    public static function all(): array
    {
        return Database::pdo()->query('SELECT * FROM users ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $pdo = Database::pdo();
        if (!empty($data['password'])) {
            $stmt = $pdo->prepare(
                'UPDATE users SET name=:name, email=:email, role=:role, active=:active,
                 password_hash=:hash WHERE id=:id'
            );
            $stmt->execute([
                ':name'=>$data['name'], ':email'=>$data['email'], ':role'=>$data['role'],
                ':active'=>(int)$data['active'],
                ':hash'=>password_hash($data['password'], PASSWORD_DEFAULT), ':id'=>$id,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET name=:name, email=:email, role=:role, active=:active WHERE id=:id'
            );
            $stmt->execute([
                ':name'=>$data['name'], ':email'=>$data['email'], ':role'=>$data['role'],
                ':active'=>(int)$data['active'], ':id'=>$id,
            ]);
        }
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
