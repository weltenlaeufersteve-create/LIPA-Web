<?php
namespace Tests;
use App\Models\Transfer;
use App\Models\Account;

final class TransferTest extends DatabaseTestCase
{
    private function accs(): array
    {
        return [
            Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]),
            Account::create(['name'=>'Cash','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>null]),
        ];
    }

    public function test_create_find_with_joined_names(): void
    {
        [$bank,$cash] = $this->accs();
        $id = Transfer::create(['date'=>'2026-03-01','from_account_id'=>$bank,'to_account_id'=>$cash,'amount_tzs'=>500,'description'=>'cash withdrawal','created_by'=>null]);
        $row = Transfer::find($id);
        $this->assertEquals(500, (int)$row['amount_tzs']);
        $all = Transfer::all();
        $this->assertSame('Bank', $all[0]['from_name']);
        $this->assertSame('Cash', $all[0]['to_name']);
    }

    public function test_filter_by_date(): void
    {
        [$bank,$cash] = $this->accs();
        $base = ['from_account_id'=>$bank,'to_account_id'=>$cash,'amount_tzs'=>100,'description'=>'','created_by'=>null];
        Transfer::create($base + ['date'=>'2026-01-10']);
        Transfer::create($base + ['date'=>'2026-03-10']);
        $this->assertCount(1, Transfer::all(['date_from'=>'2026-02-01']));
    }

    public function test_update_and_delete(): void
    {
        [$bank,$cash] = $this->accs();
        $id = Transfer::create(['date'=>'2026-03-01','from_account_id'=>$bank,'to_account_id'=>$cash,'amount_tzs'=>500,'description'=>'','created_by'=>null]);
        Transfer::update($id, ['date'=>'2026-03-02','from_account_id'=>$cash,'to_account_id'=>$bank,'amount_tzs'=>250,'description'=>'reversed']);
        $row = Transfer::find($id);
        $this->assertEquals(250, (int)$row['amount_tzs']);
        $this->assertSame('reversed', $row['description']);
        Transfer::delete($id);
        $this->assertNull(Transfer::find($id));
    }
}
