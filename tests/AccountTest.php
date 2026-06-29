<?php
namespace Tests;
use App\Models\Account;
use App\Database;

final class AccountTest extends DatabaseTestCase
{
    public function test_create_find_update_delete(): void
    {
        $id = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>1000,'opening_balance_date'=>'2026-01-01']);
        $row = Account::find($id);
        $this->assertSame('Bank', $row['name']);
        $this->assertEquals(1000, (int)$row['opening_balance']);
        $this->assertSame(1, (int)$row['active']);
        Account::update($id, ['name'=>'Bank 2','type'=>'bank','opening_balance'=>1500,'opening_balance_date'=>'2026-01-01','active'=>0]);
        $this->assertSame('Bank 2', Account::find($id)['name']);
        $this->assertSame(0, (int)Account::find($id)['active']);
        Account::delete($id);
        $this->assertNull(Account::find($id));
    }

    public function test_active_only_listing(): void
    {
        $a = Account::create(['name'=>'A','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $b = Account::create(['name'=>'B','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>null]);
        Account::update($b, ['name'=>'B','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>null,'active'=>0]);
        $this->assertCount(2, Account::all());
        $this->assertCount(1, Account::all(true));
    }

    public function test_balance_combines_opening_income_expense_transfers(): void
    {
        $bank = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>1000,'opening_balance_date'=>'2026-01-01']);
        $cash = Account::create(['name'=>'Cash','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>'2026-01-01']);
        $pdo = Database::pdo();
        $pdo->exec("INSERT INTO income (date,account_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-01',$bank,'TZS',500,1,500)");
        $pdo->exec("INSERT INTO expenses (date,account_id,amount_tzs) VALUES ('2026-02-02',$bank,200)");
        $pdo->exec("INSERT INTO transfers (date,from_account_id,to_account_id,amount_tzs) VALUES ('2026-02-03',$bank,$cash,300)");
        // bank = 1000 + 500 - 200 - 300 = 1000 ; cash = 0 + 300 = 300
        $this->assertEqualsWithDelta(1000.0, Account::balance($bank), 0.001);
        $this->assertEqualsWithDelta(300.0, Account::balance($cash), 0.001);
        // as-of before the transfer: bank = 1000 + 500 - 200 = 1300
        $this->assertEqualsWithDelta(1300.0, Account::balance($bank, '2026-02-02'), 0.001);
    }

    public function test_balances_all_active(): void
    {
        $bank = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>100,'opening_balance_date'=>null]);
        $all = Account::balancesAll();
        $this->assertSame('Bank', $all[0]['name']);
        $this->assertEqualsWithDelta(100.0, $all[0]['balance'], 0.001);
    }
}
