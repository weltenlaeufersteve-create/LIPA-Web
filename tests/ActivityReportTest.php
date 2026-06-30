<?php
namespace Tests;
use App\Reports\ActivityReport;
use App\Models\ActivityItem;
use App\Models\Expense;

final class ActivityReportTest extends DatabaseTestCase
{
    public function test_build_groups_activities_with_cost_and_grand_total(): void
    {
        $base = ['contact_id'=>null,'project_id'=>null,'category_id'=>null,'description'=>'','reference'=>'','notes'=>'','created_by'=>null];
        $a1 = ActivityItem::create(['date'=>'2026-02-05','title'=>'Trip','description'=>'','project_id'=>null,'created_by'=>null]);
        $e1 = Expense::create($base + ['date'=>'2026-02-05','amount_tzs'=>1000]);
        $e2 = Expense::create($base + ['date'=>'2026-02-06','amount_tzs'=>500]);
        ActivityItem::setExpenses($a1, [$e1, $e2]);
        $a2 = ActivityItem::create(['date'=>'2026-02-20','title'=>'Workshop','description'=>'','project_id'=>null,'created_by'=>null]);
        $e3 = Expense::create($base + ['date'=>'2026-02-20','amount_tzs'=>700]);
        ActivityItem::setExpenses($a2, [$e3]);
        // out of period -> excluded
        ActivityItem::create(['date'=>'2026-03-10','title'=>'Later','description'=>'','project_id'=>null,'created_by'=>null]);

        $d = ActivityReport::build('2026-02-01', '2026-02-28');
        $this->assertCount(2, $d['activities']);
        $this->assertEqualsWithDelta(2200.0, $d['grand_total'], 0.001);
    }
}
