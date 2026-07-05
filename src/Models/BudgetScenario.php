<?php
namespace App\Models;

use App\Database;

final class BudgetScenario
{
    /** Parse a possibly comma-formatted number ("600,000" → 600000.0). */
    private static function n($v): float { return (float) str_replace([',', ' '], '', (string)$v); }
    private static function ni($v): int { return (int) round(self::n($v)); }

    public static function create(array $d): int
    {
        $pdo = Database::pdo();
        $st = $pdo->prepare('INSERT INTO budget_scenarios (name, description, project_id, status, funded_amount, include_first_batch, created_by, created_at, updated_at)
            VALUES (:n,:d,:p,:s,:f,:fb,:u,NOW(),NOW())');
        $st->execute([':n'=>$d['name'], ':d'=>$d['description'] ?: null, ':p'=>$d['project_id'] ?: null,
            ':s'=>$d['status'] ?? 'draft', ':f'=>self::n($d['funded_amount'] ?? 0),
            ':fb'=>!empty($d['include_first_batch']) ? 1 : 0, ':u'=>$d['created_by'] ?: null]);
        return (int)$pdo->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        $st = Database::pdo()->prepare('UPDATE budget_scenarios SET name=:n, description=:d, project_id=:p, status=:s, funded_amount=:f, include_first_batch=:fb, updated_at=NOW() WHERE id=:id');
        $st->execute([':n'=>$d['name'], ':d'=>$d['description'] ?: null, ':p'=>$d['project_id'] ?: null,
            ':s'=>$d['status'] ?? 'draft', ':f'=>self::n($d['funded_amount'] ?? 0),
            ':fb'=>!empty($d['include_first_batch']) ? 1 : 0, ':id'=>$id]);
    }

    public static function find(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM budget_scenarios WHERE id=:id');
        $st->execute([':id'=>$id]);
        return $st->fetch() ?: null;
    }

    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT b.*, p.name AS project_name FROM budget_scenarios b
             LEFT JOIN projects p ON p.id = b.project_id ORDER BY b.updated_at DESC, b.id DESC'
        )->fetchAll();
    }

    public static function delete(int $id): void
    {
        $st = Database::pdo()->prepare('DELETE FROM budget_scenarios WHERE id=:id');
        $st->execute([':id'=>$id]);
    }

    public static function products(int $id): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM budget_products WHERE scenario_id=:id ORDER BY sort, id');
        $st->execute([':id'=>$id]);
        return $st->fetchAll();
    }

    public static function items(int $id, ?string $type = null): array
    {
        $sql = 'SELECT * FROM budget_items WHERE scenario_id=:id' . ($type ? ' AND item_type=:t' : '') . ' ORDER BY sort, id';
        $st = Database::pdo()->prepare($sql);
        $st->execute($type ? [':id'=>$id, ':t'=>$type] : [':id'=>$id]);
        return $st->fetchAll();
    }

    public static function allocations(int $id): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM budget_allocations WHERE scenario_id=:id ORDER BY sort, id');
        $st->execute([':id'=>$id]);
        return $st->fetchAll();
    }

    public static function materials(int $productId): array
    {
        $st = Database::pdo()->prepare('SELECT * FROM budget_product_materials WHERE product_id=:id ORDER BY sort, id');
        $st->execute([':id'=>$productId]);
        return $st->fetchAll();
    }

    public static function setProducts(int $id, array $rows): void
    {
        $pdo = Database::pdo();
        // deleting the scenario's products cascades their materials
        $pdo->prepare('DELETE FROM budget_products WHERE scenario_id=:id')->execute([':id'=>$id]);
        $ins = $pdo->prepare('INSERT INTO budget_products (scenario_id,name,unit_name,sale_price,unit_cost,batch_yield,units_low,units_mid,units_high,notes,sort)
            VALUES (:s,:n,:u,:sp,:uc,:by,:l,:m,:h,:no,:so)');
        $insM = $pdo->prepare('INSERT INTO budget_product_materials (product_id,name,amount,sort) VALUES (:p,:n,:a,:so)');
        foreach ($rows as $i => $r) {
            $yield = max(self::ni($r['batch_yield'] ?? 1), 1);
            $materials = $r['materials'] ?? [];
            $batchTotal = 0.0;
            foreach ($materials as $m) { $batchTotal += self::n($m['amount'] ?? 0); }
            $unitCost = round($batchTotal / $yield, 2);   // derived, cached
            $ins->execute([':s'=>$id, ':n'=>$r['name'], ':u'=>$r['unit_name'] ?: 'unit',
                ':sp'=>self::n($r['sale_price'] ?? 0), ':uc'=>$unitCost, ':by'=>$yield,
                ':l'=>self::ni($r['units_low'] ?? 0), ':m'=>self::ni($r['units_mid'] ?? 0), ':h'=>self::ni($r['units_high'] ?? 0),
                ':no'=>$r['notes'] ?: null, ':so'=>$r['sort'] ?? $i]);
            $pid = (int)$pdo->lastInsertId();
            foreach ($materials as $j => $m) {
                if (trim((string)($m['name'] ?? '')) === '') { continue; }
                $insM->execute([':p'=>$pid, ':n'=>$m['name'], ':a'=>self::n($m['amount'] ?? 0), ':so'=>$m['sort'] ?? $j]);
            }
        }
    }

    public static function setItems(int $id, array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM budget_items WHERE scenario_id=:id')->execute([':id'=>$id]);
        $ins = $pdo->prepare('INSERT INTO budget_items (scenario_id,item_type,name,amount,notes,sort) VALUES (:s,:t,:n,:a,:no,:so)');
        foreach ($rows as $i => $r) {
            $ins->execute([':s'=>$id, ':t'=>$r['item_type'], ':n'=>$r['name'], ':a'=>self::n($r['amount'] ?? 0), ':no'=>$r['notes'] ?: null, ':so'=>$r['sort'] ?? $i]);
        }
    }

    public static function setAllocations(int $id, array $rows): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM budget_allocations WHERE scenario_id=:id')->execute([':id'=>$id]);
        $ins = $pdo->prepare('INSERT INTO budget_allocations (scenario_id,name,monthly_amount,sort) VALUES (:s,:n,:a,:so)');
        foreach ($rows as $i => $r) {
            $ins->execute([':s'=>$id, ':n'=>$r['name'], ':a'=>self::n($r['monthly_amount'] ?? 0), ':so'=>$r['sort'] ?? $i]);
        }
    }
}
