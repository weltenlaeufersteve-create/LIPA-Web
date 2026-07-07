<?php
namespace Tests;
use App\Models\Income;
use App\Models\Contact;
use App\Models\Account;
use App\Database;

final class IncomeByDonorTest extends DatabaseTestCase
{
    public function test_groups_income_by_donor_desc_and_buckets_null_donor(): void
    {
        $acc = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $big = Contact::create(['type'=>'donor','name'=>'Global Fund','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        $small = Contact::create(['type'=>'donor','name'=>'Diakonie','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        $pdo = Database::pdo();
        // Global Fund: 500 + 300 = 800; Diakonie: 200; no donor (NULL): 100
        $pdo->exec("INSERT INTO income (date,account_id,contact_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-01',$acc,$big,'TZS',500,1,500)");
        $pdo->exec("INSERT INTO income (date,account_id,contact_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-02',$acc,$big,'TZS',300,1,300)");
        $pdo->exec("INSERT INTO income (date,account_id,contact_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-03',$acc,$small,'TZS',200,1,200)");
        $pdo->exec("INSERT INTO income (date,account_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-04',$acc,'TZS',100,1,100)");

        $rows = Income::byDonor(['date_from'=>'2026-02-01','date_to'=>'2026-02-28']);

        // descending by total: Global Fund 800, Diakonie 200, (null) 100
        $this->assertSame('Global Fund', $rows[0]['name']);
        $this->assertEqualsWithDelta(800.0, (float)$rows[0]['total'], 0.001);
        $this->assertSame('Diakonie', $rows[1]['name']);
        $this->assertEqualsWithDelta(200.0, (float)$rows[1]['total'], 0.001);
        $this->assertNull($rows[2]['name']); // no-donor bucket
        $this->assertEqualsWithDelta(100.0, (float)$rows[2]['total'], 0.001);
    }
}
