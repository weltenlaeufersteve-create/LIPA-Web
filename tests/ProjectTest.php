<?php
namespace Tests;
use App\Models\Project;

final class ProjectTest extends DatabaseTestCase
{
    public function test_create_and_find(): void
    {
        $id = Project::create(['name'=>'Clean Water','code'=>'CW-01','description'=>'Wells']);
        $row = Project::find($id);
        $this->assertSame('Clean Water', $row['name']);
        $this->assertSame('CW-01', $row['code']);
        $this->assertSame(1, (int)$row['active']);
    }

    public function test_all_ordered_by_name(): void
    {
        Project::create(['name'=>'Zebra','code'=>'','description'=>'']);
        Project::create(['name'=>'Alpha','code'=>'','description'=>'']);
        $all = Project::all();
        $this->assertSame('Alpha', $all[0]['name']);
        $this->assertSame('Zebra', $all[1]['name']);
    }

    public function test_update_and_delete(): void
    {
        $id = Project::create(['name'=>'Old','code'=>'','description'=>'']);
        Project::update($id, ['name'=>'New','code'=>'X1','description'=>'desc','active'=>0]);
        $row = Project::find($id);
        $this->assertSame('New', $row['name']);
        $this->assertSame(0, (int)$row['active']);
        Project::delete($id);
        $this->assertNull(Project::find($id));
    }
}
