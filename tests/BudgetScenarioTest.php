<?php
namespace Tests;
use App\Models\BudgetScenario;

final class BudgetScenarioTest extends DatabaseTestCase
{
    public function test_crud_and_children_roundtrip_and_cascade(): void
    {
        $id = BudgetScenario::create(['name'=>'Soap','description'=>'','project_id'=>null,'status'=>'draft','funded_amount'=>600000,'created_by'=>null]);
        $this->assertSame('Soap', BudgetScenario::find($id)['name']);

        BudgetScenario::setProducts($id, [
            ['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'unit_cost'=>1250,'units_low'=>150,'units_mid'=>300,'units_high'=>500,'notes'=>'','sort'=>0],
        ]);
        BudgetScenario::setItems($id, [
            ['item_type'=>'one_time','name'=>'Molds','amount'=>800000,'notes'=>'','sort'=>0],
            ['item_type'=>'monthly_fixed','name'=>'Rent','amount'=>200000,'notes'=>'','sort'=>0],
        ]);
        BudgetScenario::setAllocations($id, [['name'=>'Health','monthly_amount'=>80000,'sort'=>0]]);

        $this->assertCount(1, BudgetScenario::products($id));
        $this->assertCount(1, BudgetScenario::items($id, 'one_time'));
        $this->assertCount(2, BudgetScenario::items($id));
        $this->assertCount(1, BudgetScenario::allocations($id));

        // replace-all semantics
        BudgetScenario::setProducts($id, []);
        $this->assertCount(0, BudgetScenario::products($id));

        // delete cascades children
        BudgetScenario::delete($id);
        $this->assertNull(BudgetScenario::find($id));
        $this->assertCount(0, BudgetScenario::items($id));
    }
}
