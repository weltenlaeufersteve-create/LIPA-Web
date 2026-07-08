<?php
namespace Tests;
use App\Models\Transfer;
use App\Models\Account;
use App\Database;

final class TransferTotalsTest extends DatabaseTestCase
{
    public function test_total_and_received_by_account_respect_date_range(): void
    {
        $bank = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $cash = Account::create(['name'=>'Cash','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>null]);
        $pdo = Database::pdo();
        $pdo->exec("INSERT INTO transfers (date,from_account_id,to_account_id,amount_tzs) VALUES ('2026-02-10',$bank,$cash,2255000)");
        $pdo->exec("INSERT INTO transfers (date,from_account_id,to_account_id,amount_tzs) VALUES ('2026-02-15',$bank,$cash,100000)");
        // outside the period — must be excluded
        $pdo->exec("INSERT INTO transfers (date,from_account_id,to_account_id,amount_tzs) VALUES ('2026-03-01',$bank,$cash,999)");

        $f = ['date_from'=>'2026-02-01','date_to'=>'2026-02-28'];
        $this->assertEqualsWithDelta(2355000.0, Transfer::totalTzs($f), 0.001);

        $rec = Transfer::receivedByAccount($f);
        $this->assertEqualsWithDelta(2355000.0, (float)$rec[$cash], 0.001);
        $this->assertArrayNotHasKey($bank, $rec); // Bank received no transfers in
    }
}
