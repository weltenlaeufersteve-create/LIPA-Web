<?php
namespace Tests;
use App\Models\Income;
use App\Models\Contact;
use App\Models\Category;
use App\Models\Project;

final class IncomeTest extends DatabaseTestCase
{
    private function seedRefs(): array
    {
        return [
            'contact'  => Contact::create(['type'=>'donor','name'=>'Donor X','email'=>'','phone'=>'','address'=>'','notes'=>'']),
            'project'  => Project::create(['name'=>'Proj','code'=>'','description'=>'']),
            'category' => Category::create(['type'=>'income','name'=>'Grants','sort_order'=>1]),
        ];
    }

    public function test_tzs_value_rounds(): void
    {
        $this->assertSame(25000.00, Income::tzsValue(10.0, 2500.0));
        $this->assertSame(2500.50, Income::tzsValue(2500.5, 1.0));
    }

    public function test_create_find_with_joined_names(): void
    {
        $r = $this->seedRefs();
        $id = Income::create([
            'date'=>'2026-03-01','contact_id'=>$r['contact'],'project_id'=>$r['project'],
            'category_id'=>$r['category'],'description'=>'Q1 grant','currency'=>'USD',
            'amount_original'=>1000,'exchange_rate'=>2500,'amount_tzs'=>2500000,
            'reference'=>'WIRE-1','notes'=>'','created_by'=>null,
        ]);
        $row = Income::find($id);
        $this->assertSame('Q1 grant', $row['description']);
        $this->assertSame('USD', $row['currency']);
        $this->assertEquals(2500000, (int)$row['amount_tzs']);
        $all = Income::all();
        $this->assertSame('Donor X', $all[0]['contact_name']);
        $this->assertSame('Grants', $all[0]['category_name']);
        $this->assertSame('Proj', $all[0]['project_name']);
    }

    public function test_filters_and_total(): void
    {
        $r = $this->seedRefs();
        $base = ['contact_id'=>null,'project_id'=>$r['project'],'category_id'=>$r['category'],
                 'description'=>'','currency'=>'TZS','exchange_rate'=>1,'reference'=>'','notes'=>'','created_by'=>null];
        Income::create($base + ['date'=>'2026-01-10','amount_original'=>100,'amount_tzs'=>100]);
        Income::create($base + ['date'=>'2026-02-10','amount_original'=>200,'amount_tzs'=>200]);
        Income::create($base + ['date'=>'2026-03-10','amount_original'=>300,'amount_tzs'=>300]);
        $this->assertCount(2, Income::all(['date_from'=>'2026-02-01','date_to'=>'2026-03-31']));
        $this->assertEqualsWithDelta(500.0, Income::totalTzs(['date_from'=>'2026-02-01','date_to'=>'2026-03-31']), 0.001);
        $this->assertEqualsWithDelta(600.0, Income::totalTzs(), 0.001);
    }

    public function test_update_setReceipt_delete(): void
    {
        $r = $this->seedRefs();
        $id = Income::create(['date'=>'2026-03-01','contact_id'=>null,'project_id'=>null,
            'category_id'=>$r['category'],'description'=>'x','currency'=>'TZS','amount_original'=>50,
            'exchange_rate'=>1,'amount_tzs'=>50,'reference'=>'','notes'=>'','created_by'=>null]);
        Income::update($id, ['date'=>'2026-03-02','contact_id'=>null,'project_id'=>null,
            'category_id'=>$r['category'],'description'=>'y','currency'=>'TZS','amount_original'=>75,
            'exchange_rate'=>1,'amount_tzs'=>75,'reference'=>'R2','notes'=>'n']);
        $row = Income::find($id);
        $this->assertSame('y', $row['description']);
        $this->assertEquals(75, (int)$row['amount_tzs']);
        Income::setReceipt($id, 'income_1_abc.pdf');
        $this->assertSame('income_1_abc.pdf', Income::find($id)['receipt_path']);
        Income::delete($id);
        $this->assertNull(Income::find($id));
    }
}
