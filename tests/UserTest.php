<?php
namespace Tests;
use App\Models\User;

final class UserTest extends DatabaseTestCase
{
    public function test_create_hashes_password_and_returns_id(): void
    {
        $id = User::create([
            'name' => 'Admin', 'email' => 'a@x.org',
            'password' => 'secret123', 'role' => 'admin',
        ]);
        $this->assertGreaterThan(0, $id);
        $row = User::find($id);
        $this->assertSame('a@x.org', $row['email']);
        $this->assertNotSame('secret123', $row['password_hash']);
        $this->assertTrue(password_verify('secret123', $row['password_hash']));
    }

    public function test_find_by_email(): void
    {
        User::create(['name'=>'B','email'=>'b@x.org','password'=>'pw','role'=>'editor']);
        $this->assertSame('editor', User::findByEmail('b@x.org')['role']);
        $this->assertNull(User::findByEmail('missing@x.org'));
    }

    public function test_update_changes_fields_and_optional_password(): void
    {
        $id = User::create(['name'=>'C','email'=>'c@x.org','password'=>'old','role'=>'viewer']);
        User::update($id, ['name'=>'C2','email'=>'c@x.org','role'=>'editor','active'=>1,'password'=>'new']);
        $row = User::find($id);
        $this->assertSame('C2', $row['name']);
        $this->assertSame('editor', $row['role']);
        $this->assertTrue(password_verify('new', $row['password_hash']));
    }

    public function test_update_keeps_password_when_blank(): void
    {
        $id = User::create(['name'=>'D','email'=>'d@x.org','password'=>'keep','role'=>'viewer']);
        User::update($id, ['name'=>'D','email'=>'d@x.org','role'=>'viewer','active'=>1,'password'=>'']);
        $this->assertTrue(password_verify('keep', User::find($id)['password_hash']));
    }

    public function test_delete_removes_row(): void
    {
        $id = User::create(['name'=>'E','email'=>'e@x.org','password'=>'pw','role'=>'viewer']);
        User::delete($id);
        $this->assertNull(User::find($id));
    }
}
