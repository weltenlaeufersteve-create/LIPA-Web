<?php
namespace App\Models;

use App\Database;
use PDO;

final class Account
{
    public static function create(array $data): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO accounts (name, type, opening_balance, opening_balance_date, active)
             VALUES (:name, :type, :ob, :obd, 1)'
        );
        $stmt->execute([
            ':name'=>$data['name'], ':type'=>$data['type'] ?: 'bank',
            ':ob'=>(float)($data['opening_balance'] ?? 0),
            ':obd'=>$data['opening_balance_date'] ?: null,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM accounts' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY name';
        return Database::pdo()->query($sql)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM accounts WHERE id = :id');
        $stmt->execute([':id'=>$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE accounts SET name=:name, type=:type, opening_balance=:ob,
             opening_balance_date=:obd, active=:active WHERE id=:id'
        );
        $stmt->execute([
            ':name'=>$data['name'], ':type'=>$data['type'] ?: 'bank',
            ':ob'=>(float)($data['opening_balance'] ?? 0),
            ':obd'=>$data['opening_balance_date'] ?: null,
            ':active'=>(int)$data['active'], ':id'=>$id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM accounts WHERE id = :id');
        $stmt->execute([':id'=>$id]);
    }

    public static function balance(int $id, ?string $asOf = null): float
    {
        $acc = self::find($id);
        if (!$acc) { return 0.0; }
        $pdo = Database::pdo();
        $cond = $asOf !== null ? ' AND date <= :asOf' : '';
        $sum = function (string $col, string $table) use ($pdo, $id, $asOf, $cond): float {
            $st = $pdo->prepare("SELECT COALESCE(SUM(amount_tzs),0) FROM {$table} WHERE {$col} = :id{$cond}");
            $st->bindValue(':id', $id, PDO::PARAM_INT);
            if ($asOf !== null) { $st->bindValue(':asOf', $asOf); }
            $st->execute();
            return (float)$st->fetchColumn();
        };
        $income   = $sum('account_id', 'income');
        $expense  = $sum('account_id', 'expenses');
        $transIn  = $sum('to_account_id', 'transfers');
        $transOut = $sum('from_account_id', 'transfers');
        return round((float)$acc['opening_balance'] + $income - $expense + $transIn - $transOut, 2);
    }

    public static function balancesAll(?string $asOf = null): array
    {
        $out = [];
        foreach (self::all(true) as $a) {
            $out[] = ['id'=>(int)$a['id'], 'name'=>$a['name'], 'balance'=>self::balance((int)$a['id'], $asOf)];
        }
        return $out;
    }
}
