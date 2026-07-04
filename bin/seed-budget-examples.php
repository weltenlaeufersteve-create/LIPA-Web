<?php
// Seeds two sample budget scenarios (soap + pottery). Idempotent: skips if already present.
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Database;
use App\Models\BudgetScenario;

$names = ['Hard soap - Karatu', 'Pottery line - Karatu'];
$in = implode(',', array_fill(0, count($names), '?'));
$st = Database::pdo()->prepare("SELECT COUNT(*) FROM budget_scenarios WHERE name IN ($in)");
$st->execute($names);
if ((int)$st->fetchColumn() > 0) { echo "budget examples already present — skipping\n"; exit; }

// ── Soap (single product) ──
$soap = BudgetScenario::create(['name'=>'Hard soap - Karatu', 'description'=>'Vocational soap made and sold by the youth group', 'project_id'=>null, 'status'=>'active', 'funded_amount'=>600000, 'created_by'=>null]);
BudgetScenario::setProducts($soap, [[
    'name'=>'Soap bar', 'unit_name'=>'bar', 'sale_price'=>2500, 'batch_yield'=>100,
    'units_low'=>150, 'units_mid'=>300, 'units_high'=>500, 'notes'=>'', 'sort'=>0,
    'materials'=>[
        ['name'=>'Oils & fats (25 L)', 'amount'=>80000],
        ['name'=>'Caustic soda (5 kg)', 'amount'=>15000],
        ['name'=>'Fragrance / botanicals', 'amount'=>10000],
        ['name'=>'Wrapping & labels', 'amount'=>20000],
    ],
]]);
BudgetScenario::setItems($soap, [
    ['item_type'=>'one_time', 'name'=>'Soap molds', 'amount'=>250000, 'notes'=>'', 'sort'=>0],
    ['item_type'=>'one_time', 'name'=>'Pots & equipment', 'amount'=>180000, 'notes'=>'', 'sort'=>1],
    ['item_type'=>'one_time', 'name'=>'Training workshop', 'amount'=>300000, 'notes'=>'', 'sort'=>2],
    ['item_type'=>'one_time', 'name'=>'Permits / licenses', 'amount'=>70000, 'notes'=>'', 'sort'=>3],
    ['item_type'=>'monthly_fixed', 'name'=>'Workspace (rent share)', 'amount'=>50000, 'notes'=>'', 'sort'=>0],
    ['item_type'=>'monthly_fixed', 'name'=>'Production hours (paid)', 'amount'=>120000, 'notes'=>'', 'sort'=>1],
    ['item_type'=>'monthly_fixed', 'name'=>'Transport to market', 'amount'=>30000, 'notes'=>'', 'sort'=>2],
]);
BudgetScenario::setAllocations($soap, [
    ['name'=>'Health insurance', 'monthly_amount'=>80000, 'sort'=>0],
    ['name'=>'NGO rent', 'monthly_amount'=>150000, 'sort'=>1],
]);
echo "seeded soap scenario #$soap\n";

// ── Pottery (product line) ──
$pot = BudgetScenario::create(['name'=>'Pottery line - Karatu', 'description'=>'Decorative & practical pieces for tourists', 'project_id'=>null, 'status'=>'draft', 'funded_amount'=>0, 'created_by'=>null]);
BudgetScenario::setProducts($pot, [
    ['name'=>'Decorative bowl', 'unit_name'=>'bowl', 'sale_price'=>5000, 'batch_yield'=>10, 'units_low'=>10, 'units_mid'=>20, 'units_high'=>30, 'notes'=>'', 'sort'=>0,
     'materials'=>[['name'=>'Clay', 'amount'=>15000], ['name'=>'Glaze', 'amount'=>5000]]],
    ['name'=>'Vase', 'unit_name'=>'vase', 'sale_price'=>20000, 'batch_yield'=>5, 'units_low'=>2, 'units_mid'=>5, 'units_high'=>8, 'notes'=>'', 'sort'=>1,
     'materials'=>[['name'=>'Clay (large)', 'amount'=>30000], ['name'=>'Premium glaze', 'amount'=>10000]]],
]);
BudgetScenario::setItems($pot, [
    ['item_type'=>'one_time', 'name'=>'Pottery wheel', 'amount'=>400000, 'notes'=>'', 'sort'=>0],
    ['item_type'=>'one_time', 'name'=>'Kiln', 'amount'=>900000, 'notes'=>'', 'sort'=>1],
    ['item_type'=>'monthly_fixed', 'name'=>'Kiln power', 'amount'=>50000, 'notes'=>'', 'sort'=>0],
    ['item_type'=>'monthly_fixed', 'name'=>'Workshop rent share', 'amount'=>40000, 'notes'=>'', 'sort'=>1],
]);
BudgetScenario::setAllocations($pot, [
    ['name'=>'Health insurance', 'monthly_amount'=>80000, 'sort'=>0],
    ['name'=>'NGO rent', 'monthly_amount'=>150000, 'sort'=>1],
]);
echo "seeded pottery scenario #$pot\n";
echo "done\n";
