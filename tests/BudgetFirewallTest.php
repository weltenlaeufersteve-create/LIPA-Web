<?php
namespace Tests;
use App\Models\BudgetScenario;
use App\Database;

final class BudgetFirewallTest extends DatabaseTestCase
{
    public function test_saving_a_scenario_never_touches_cashbook_tables(): void
    {
        $pdo = Database::pdo();
        $before = [];
        foreach (['income','expenses','transfers','accounts'] as $t) {
            $before[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        }
        $id = BudgetScenario::create(['name'=>'Soap','description'=>'','project_id'=>null,'status'=>'draft','funded_amount'=>0,'created_by'=>null]);
        BudgetScenario::setProducts($id, [['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'batch_yield'=>1,'units_low'=>1,'units_mid'=>1,'units_high'=>1,'notes'=>'','sort'=>0,'materials'=>[['name'=>'Mat','amount'=>1250]]]]);
        BudgetScenario::setItems($id, [['item_type'=>'one_time','name'=>'Molds','amount'=>800000,'notes'=>'','sort'=>0]]);
        BudgetScenario::setAllocations($id, [['name'=>'Rent','monthly_amount'=>150000,'sort'=>0]]);
        foreach (['income','expenses','transfers','accounts'] as $t) {
            $this->assertSame($before[$t], (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(), "$t must be untouched by budget");
        }
    }
}
