<?php
namespace Tests;
use PHPUnit\Framework\TestCase;
use App\Budget\ScenarioCalc;

final class ScenarioCalcTest extends TestCase
{
    private function calc(array $p, array $i = [], array $a = [], float $funded = 0.0): array
    {
        return ScenarioCalc::compute(['funded_amount'=>$funded], $p, $i, $a);
    }

    public function test_single_product_profit_and_break_even(): void
    {
        $p = [['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'unit_cost'=>1250,
               'units_low'=>150,'units_mid'=>300,'units_high'=>500]];
        $items = [
            ['item_type'=>'one_time','amount'=>800000],
            ['item_type'=>'monthly_fixed','amount'=>200000],
        ];
        $r = ScenarioCalc::compute(['funded_amount'=>600000], $p, $items, []);
        $this->assertSame(800000.0, $r['one_time_total']);
        $this->assertSame(200000.0, $r['net_startup']);          // 800k - 600k funded
        $this->assertSame(200000.0, $r['fixed_total']);
        $this->assertSame(1250.0, $r['products'][0]['margin']);  // 2500 - 1250
        // realistic: 300*2500=750k rev, 300*1250=375k var, -200k fixed = 175k profit
        $this->assertSame(175000.0, $r['cases']['mid']['profit']);
        $this->assertEqualsWithDelta(1.14, $r['cases']['mid']['break_even'], 0.01); // 200k/175k
        $this->assertEqualsWithDelta(4.57, $r['cases']['mid']['break_even_unfunded'], 0.01); // 800k/175k
        // pessimistic 150 bars → 150*1250 - 200k = -12,500 loss → no break-even
        $this->assertSame(-12500.0, $r['cases']['low']['profit']);
        $this->assertNull($r['cases']['low']['break_even']);
    }

    public function test_multi_product_totals_sum_across_mix(): void
    {
        $p = [
            ['name'=>'Bowl','unit_name'=>'bowl','sale_price'=>5000,'unit_cost'=>2000,'units_low'=>10,'units_mid'=>20,'units_high'=>30],
            ['name'=>'Vase','unit_name'=>'vase','sale_price'=>20000,'unit_cost'=>8000,'units_low'=>2,'units_mid'=>5,'units_high'=>8],
        ];
        $r = ScenarioCalc::compute(['funded_amount'=>0], $p, [['item_type'=>'monthly_fixed','amount'=>50000]], []);
        // mid revenue = 20*5000 + 5*20000 = 100k + 100k = 200k
        $this->assertSame(200000.0, $r['cases']['mid']['revenue']);
        // mid variable = 20*2000 + 5*8000 = 40k + 40k = 80k
        $this->assertSame(80000.0, $r['cases']['mid']['variable']);
        $this->assertSame(70000.0, $r['cases']['mid']['profit']); // 200k-80k-50k
        $this->assertSame(25, $r['cases']['mid']['units_total']);  // 20 bowls + 5 vases
    }

    public function test_first_batch_seed_added_to_startup_only_when_enabled(): void
    {
        // unit_cost 950 × batch_yield 100 = 95,000 per batch
        $p = [['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'unit_cost'=>950,'batch_yield'=>100,
               'units_low'=>150,'units_mid'=>300,'units_high'=>500]];
        $items = [['item_type'=>'one_time','amount'=>800000], ['item_type'=>'monthly_fixed','amount'=>200000]];

        $off = ScenarioCalc::compute(['funded_amount'=>0, 'include_first_batch'=>0], $p, $items, []);
        $this->assertSame(95000.0, $off['first_batch_total']);   // always computed for display
        $this->assertFalse($off['first_batch_included']);
        $this->assertSame(800000.0, $off['one_time_total']);     // equipment only
        $this->assertSame(800000.0, $off['net_startup']);

        $on = ScenarioCalc::compute(['funded_amount'=>0, 'include_first_batch'=>1], $p, $items, []);
        $this->assertTrue($on['first_batch_included']);
        $this->assertSame(800000.0, $on['one_time_total']);      // Total start-up unchanged (equipment)
        $this->assertSame(895000.0, $on['net_startup']);         // 800k − 0 funded + 95k seed
        // profit unchanged (seed is one-time, not recurring): 300*(2500-950)-200k = 265,000
        $this->assertSame(265000.0, $on['cases']['mid']['profit']);
        $this->assertSame($off['cases']['mid']['profit'], $on['cases']['mid']['profit']);
    }

    public function test_negative_margin_flagged(): void
    {
        $p = [['name'=>'X','unit_name'=>'unit','sale_price'=>1000,'unit_cost'=>1500,'units_low'=>1,'units_mid'=>1,'units_high'=>1]];
        $r = $this->calc($p);
        $this->assertTrue($r['products'][0]['margin_negative']);
        $this->assertSame(-500.0, $r['products'][0]['margin']);
    }

    public function test_allocation_waterfall_order_and_leftover(): void
    {
        $p = [['name'=>'Bar','unit_name'=>'bar','sale_price'=>2500,'unit_cost'=>1250,'units_low'=>300,'units_mid'=>300,'units_high'=>300]];
        // mid profit = 300*1250 - 0 fixed = 375000
        $alloc = [['name'=>'Health','monthly_amount'=>80000], ['name'=>'Rent','monthly_amount'=>150000]];
        $r = ScenarioCalc::compute(['funded_amount'=>0], $p, [], $alloc);
        $this->assertSame(100, $r['allocations'][0]['coverage_pct']);        // health fully
        $this->assertSame(100, $r['allocations'][1]['coverage_pct']);        // rent fully (375k-80k=295k ≥150k)
        $this->assertSame(145000.0, $r['alloc_leftover']);                    // 295k-150k
    }
}
