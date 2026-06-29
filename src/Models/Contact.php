<?php
namespace App\Models;

use App\Database;

final class Contact
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO contacts (type, name, email, phone, address, notes, active)
             VALUES (:type, :name, :email, :phone, :address, :notes, 1)'
        );
        $stmt->execute([
            ':type'=>$data['type'], ':name'=>$data['name'],
            ':email'=>$data['email'] ?: null, ':phone'=>$data['phone'] ?: null,
            ':address'=>$data['address'] ?: null, ':notes'=>$data['notes'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function all(?string $type = null): array
    {
        $pdo = Database::pdo();
        if ($type !== null) {
            $stmt = $pdo->prepare('SELECT * FROM contacts WHERE type = :type ORDER BY name');
            $stmt->execute([':type'=>$type]);
            return $stmt->fetchAll();
        }
        return $pdo->query('SELECT * FROM contacts ORDER BY name')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM contacts WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE contacts SET type=:type, name=:name, email=:email, phone=:phone,
             address=:address, notes=:notes, active=:active WHERE id=:id'
        );
        $stmt->execute([
            ':type'=>$data['type'], ':name'=>$data['name'],
            ':email'=>$data['email'] ?: null, ':phone'=>$data['phone'] ?: null,
            ':address'=>$data['address'] ?: null, ':notes'=>$data['notes'] ?: null,
            ':active'=>(int)$data['active'], ':id'=>$id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM contacts WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }
}
