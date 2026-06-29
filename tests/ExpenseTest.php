<?php
namespace Tests;
use App\Models\Expense;
use App\Models\Contact;
use App\Models\Category;
use App\Models\Project;

final class ExpenseTest extends DatabaseTestCase
{
    private function refs(): array
    {
        return [
            'vendor'   => Contact::create(['type'=>'vendor','name'=>'Vendor Y','email'=>'','phone'=>'','address'=>'','notes'=>'']),
            'project'  => Project::create(['name'=>'Proj','code'=>'','description'=>'']),
            'category' => Category::create(['type'=>'expense','name'=>'Rent','sort_order'=>1]),
        ];
    }

    public function test_create_find_with_joined_names(): void
    {
        $r = $this->refs();
        $id = Expense::create(['date'=>'2026-03-01','contact_id'=>$r['vendor'],'project_id'=>$r['project'],
            'category_id'=>$r['category'],'description'=>'March rent','amount_tzs'=>450000,
            'reference'=>'INV-9','notes'=>'','created_by'=>null]);
        $row = Expense::find($id);
        $this->assertSame('March rent', $row['description']);
        $this->assertEquals(450000, (int)$row['amount_tzs']);
        $all = Expense::all();
        $this->assertSame('Vendor Y', $all[0]['contact_name']);
        $this->assertSame('Rent', $all[0]['category_name']);
    }

    public function test_filters_and_total(): void
    {
        $r = $this->refs();
        $base = ['contact_id'=>null,'project_id'=>$r['project'],'category_id'=>$r['category'],
                 'description'=>'','reference'=>'','notes'=>'','created_by'=>null];
        Expense::create($base + ['date'=>'2026-01-05','amount_tzs'=>100]);
        Expense::create($base + ['date'=>'2026-02-05','amount_tzs'=>200]);
        $this->assertCount(1, Expense::all(['date_from'=>'2026-02-01']));
        $this->assertEqualsWithDelta(200.0, Expense::totalTzs(['date_from'=>'2026-02-01']), 0.001);
        $this->assertEqualsWithDelta(300.0, Expense::totalTzs(), 0.001);
    }

    public function test_update_setReceipt_delete(): void
    {
        $r = $this->refs();
        $id = Expense::create(['date'=>'2026-03-01','contact_id'=>null,'project_id'=>null,
            'category_id'=>$r['category'],'description'=>'x','amount_tzs'=>50,'reference'=>'','notes'=>'','created_by'=>null]);
        Expense::update($id, ['date'=>'2026-03-02','contact_id'=>null,'project_id'=>null,
            'category_id'=>$r['category'],'description'=>'y','amount_tzs'=>75,'reference'=>'R2','notes'=>'n']);
        $row = Expense::find($id);
        $this->assertSame('y', $row['description']);
        $this->assertEquals(75, (int)$row['amount_tzs']);
        Expense::setReceipt($id, 'expense_1_x.pdf');
        $this->assertSame('expense_1_x.pdf', Expense::find($id)['receipt_path']);
        Expense::delete($id);
        $this->assertNull(Expense::find($id));
    }

    public function test_account_id_round_trip_and_join(): void
    {
        $acc = \App\Models\Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $id = Expense::create(['date'=>'2026-03-01','contact_id'=>null,'project_id'=>null,'category_id'=>null,
            'description'=>'x','amount_tzs'=>10,'reference'=>'','notes'=>'','created_by'=>null,'account_id'=>$acc]);
        $this->assertEquals($acc, (int)Expense::find($id)['account_id']);
        $this->assertSame('Bank', Expense::all()[0]['account_name']);
    }
}
