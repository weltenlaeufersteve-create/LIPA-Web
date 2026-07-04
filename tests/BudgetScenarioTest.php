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
            ['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'batch_yield'=>100,'units_low'=>150,'units_mid'=>300,'units_high'=>500,'notes'=>'','sort'=>0,
             'materials'=>[['name'=>'Oils','amount'=>80000],['name'=>'Soda','amount'=>15000],['name'=>'Wrap','amount'=>30000]]],
        ]);
        // unit_cost is derived + cached: (80k+15k+30k) ÷ 100 = 1,250
        $prod = BudgetScenario::products($id)[0];
        $this->assertEquals(1250.0, (float)$prod['unit_cost']);
        $this->assertSame(100, (int)$prod['batch_yield']);
        $this->assertCount(3, BudgetScenario::materials((int)$prod['id']));
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
