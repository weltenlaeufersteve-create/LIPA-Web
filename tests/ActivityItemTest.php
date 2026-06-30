<?php
namespace Tests;
use App\Models\ActivityItem;
use App\Models\Expense;
use App\Models\Project;

final class ActivityItemTest extends DatabaseTestCase
{
    public function test_crud_and_project_join(): void
    {
        $pid = Project::create(['name'=>'Farm','code'=>'','description'=>'']);
        $id = ActivityItem::create(['date'=>'2026-03-01','title'=>'Coffee Farm trip','description'=>'Field visit','project_id'=>$pid,'created_by'=>null]);
        $row = ActivityItem::find($id);
        $this->assertSame('Coffee Farm trip', $row['title']);
        $this->assertSame('Farm', ActivityItem::all()[0]['project_name']);
        ActivityItem::update($id, ['date'=>'2026-03-02','title'=>'Coffee Farm visit','description'=>'x','project_id'=>null]);
        $this->assertSame('Coffee Farm visit', ActivityItem::find($id)['title']);
        ActivityItem::delete($id);
        $this->assertNull(ActivityItem::find($id));
    }

    public function test_photos(): void
    {
        $id = ActivityItem::create(['date'=>'2026-03-01','title'=>'A','description'=>'','project_id'=>null,'created_by'=>null]);
        ActivityItem::addPhoto($id, 'act_aaa.jpg');
        ActivityItem::addPhoto($id, 'act_bbb.jpg');
        $this->assertSame(2, ActivityItem::photoCount($id));
        $photos = ActivityItem::photos($id);
        $this->assertSame('act_aaa.jpg', $photos[0]['filename']);
        ActivityItem::deletePhoto((int)$photos[0]['id']);
        $this->assertSame(1, ActivityItem::photoCount($id));
    }

    public function test_set_expenses_links_unlinks_and_cost(): void
    {
        $a = ActivityItem::create(['date'=>'2026-03-01','title'=>'Trip','description'=>'','project_id'=>null,'created_by'=>null]);
        $base = ['contact_id'=>null,'project_id'=>null,'category_id'=>null,'description'=>'','reference'=>'','notes'=>'','created_by'=>null,'date'=>'2026-03-01'];
        $e1 = Expense::create($base + ['amount_tzs'=>1000]);
        $e2 = Expense::create($base + ['amount_tzs'=>500]);
        $e3 = Expense::create($base + ['amount_tzs'=>200]);

        ActivityItem::setExpenses($a, [$e1, $e2]);
        $this->assertEqualsWithDelta(1500.0, ActivityItem::cost($a), 0.001);
        $this->assertCount(2, ActivityItem::expenses($a));

        // re-set to [e2,e3]: e1 unlinked, e3 linked
        ActivityItem::setExpenses($a, [$e2, $e3]);
        $this->assertEqualsWithDelta(700.0, ActivityItem::cost($a), 0.001);
        $this->assertNull(Expense::find($e1)['activity_id']);
        $this->assertEquals($a, (int)Expense::find($e2)['activity_id']);
    }
}
