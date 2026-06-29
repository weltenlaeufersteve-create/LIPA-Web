<?php
namespace Tests;
use App\Models\Activity;
use App\Models\User;

final class ActivityTest extends DatabaseTestCase
{
    public function test_log_and_recent_with_user_name(): void
    {
        $uid = User::create(['name'=>'Ada','email'=>'ada@x.org','password'=>'pw12345','role'=>'admin']);
        Activity::log($uid, 'create', 'income', 5, 'Created income #5');
        Activity::log($uid, 'delete', 'expense', 9, 'Deleted expense #9');
        $recent = Activity::recent(10);
        $this->assertCount(2, $recent);
        $this->assertSame('delete', $recent[0]['action']); // newest first
        $this->assertSame('Ada', $recent[0]['user_name']);
    }

    public function test_recent_respects_limit(): void
    {
        for ($i = 0; $i < 5; $i++) { Activity::log(null, 'create', 'contact', $i, "c{$i}"); }
        $this->assertCount(3, Activity::recent(3));
    }
}
