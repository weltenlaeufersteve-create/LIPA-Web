<?php
namespace Tests;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Category;
use App\Models\Project;

final class AggregateTest extends DatabaseTestCase
{
    public function test_income_by_category_and_project(): void
    {
        $grants = Category::create(['type'=>'income','name'=>'Grants','sort_order'=>1]);
        $don = Category::create(['type'=>'income','name'=>'Donations','sort_order'=>2]);
        $pa = Project::create(['name'=>'A','code'=>'','description'=>'']);
        $base = ['contact_id'=>null,'description'=>'','currency'=>'TZS','exchange_rate'=>1,'reference'=>'','notes'=>'','created_by'=>null,'date'=>'2026-03-01'];
        Income::create($base + ['category_id'=>$grants,'project_id'=>$pa,'amount_original'=>1000,'amount_tzs'=>1000]);
        Income::create($base + ['category_id'=>$grants,'project_id'=>null,'amount_original'=>500,'amount_tzs'=>500]);
        Income::create($base + ['category_id'=>$don,'project_id'=>$pa,'amount_original'=>200,'amount_tzs'=>200]);
        $byCat = Income::byCategory();
        $this->assertSame('Grants', $byCat[0]['name']);
        $this->assertEqualsWithDelta(1500.0, (float)$byCat[0]['total'], 0.001);
        $byProj = Income::byProject();
        $totals = [];
        foreach ($byProj as $r) { $totals[$r['name'] ?? '—'] = (float)$r['total']; }
        $this->assertEqualsWithDelta(1200.0, $totals['A'], 0.001);
        $this->assertEqualsWithDelta(500.0, $totals['—'], 0.001);
    }

    public function test_expense_by_category_respects_filter(): void
    {
        $rent = Category::create(['type'=>'expense','name'=>'Rent','sort_order'=>1]);
        $base = ['contact_id'=>null,'project_id'=>null,'description'=>'','reference'=>'','notes'=>'','created_by'=>null,'category_id'=>$rent];
        Expense::create($base + ['date'=>'2026-01-10','amount_tzs'=>100]);
        Expense::create($base + ['date'=>'2026-03-10','amount_tzs'=>300]);
        $byCat = Expense::byCategory(['date_from'=>'2026-02-01']);
        $this->assertCount(1, $byCat);
        $this->assertEqualsWithDelta(300.0, (float)$byCat[0]['total'], 0.001);
    }
}
