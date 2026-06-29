<?php
namespace Tests;
use App\Models\Contact;

final class ContactTest extends DatabaseTestCase
{
    public function test_create_and_find(): void
    {
        $id = Contact::create(['type'=>'donor','name'=>'Global Fund','email'=>'g@f.org','phone'=>'','address'=>'','notes'=>'']);
        $this->assertGreaterThan(0, $id);
        $row = Contact::find($id);
        $this->assertSame('Global Fund', $row['name']);
        $this->assertSame('donor', $row['type']);
        $this->assertSame(1, (int)$row['active']);
    }

    public function test_all_filters_by_type(): void
    {
        Contact::create(['type'=>'donor','name'=>'Donor A','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        Contact::create(['type'=>'vendor','name'=>'Vendor B','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        $this->assertCount(2, Contact::all());
        $this->assertCount(1, Contact::all('donor'));
        $this->assertSame('Vendor B', Contact::all('vendor')[0]['name']);
    }

    public function test_update_and_delete(): void
    {
        $id = Contact::create(['type'=>'vendor','name'=>'Old','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        Contact::update($id, ['type'=>'vendor','name'=>'New','email'=>'n@x.org','phone'=>'123','address'=>'Road','notes'=>'hi','active'=>0]);
        $row = Contact::find($id);
        $this->assertSame('New', $row['name']);
        $this->assertSame('n@x.org', $row['email']);
        $this->assertSame(0, (int)$row['active']);
        Contact::delete($id);
        $this->assertNull(Contact::find($id));
    }
}
