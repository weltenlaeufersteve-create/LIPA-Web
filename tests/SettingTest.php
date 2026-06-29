<?php
namespace Tests;
use App\Models\Setting;

final class SettingTest extends DatabaseTestCase
{
    public function test_set_get_upsert(): void
    {
        $this->assertNull(Setting::get('org_name'));
        $this->assertSame('fallback', Setting::get('org_name', 'fallback'));
        Setting::set('org_name', 'Pepea');
        $this->assertSame('Pepea', Setting::get('org_name'));
        Setting::set('org_name', 'Pepea Africa');
        $this->assertSame('Pepea Africa', Setting::get('org_name'));
    }

    public function test_all_returns_map(): void
    {
        Setting::set('a', '1');
        Setting::set('b', '2');
        $all = Setting::all();
        $this->assertSame('1', $all['a']);
        $this->assertSame('2', $all['b']);
    }
}
