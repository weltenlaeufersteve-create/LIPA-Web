<?php
namespace Tests;
use App\Auth;
use App\ForbiddenException;
use App\Models\User;

final class AuthTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        User::create(['name'=>'Ada','email'=>'ada@x.org','password'=>'pw12345','role'=>'admin']);
    }

    public function test_attempt_succeeds_with_correct_password(): void
    {
        $this->assertTrue(Auth::attempt('ada@x.org', 'pw12345'));
        $this->assertTrue(Auth::check());
        $this->assertSame('admin', Auth::user()['role']);
    }

    public function test_attempt_fails_with_wrong_password(): void
    {
        $this->assertFalse(Auth::attempt('ada@x.org', 'wrong'));
        $this->assertFalse(Auth::check());
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $id = User::create(['name'=>'Off','email'=>'off@x.org','password'=>'pw','role'=>'viewer']);
        User::update($id, ['name'=>'Off','email'=>'off@x.org','role'=>'viewer','active'=>0,'password'=>'']);
        $this->assertFalse(Auth::attempt('off@x.org', 'pw'));
    }

    public function test_require_role_allows_and_blocks(): void
    {
        Auth::attempt('ada@x.org', 'pw12345');
        Auth::requireRole('admin');            // no exception
        $this->assertTrue(Auth::is('admin'));
        $this->expectException(ForbiddenException::class);
        Auth::requireRole('editor');           // admin is not editor
    }
}
