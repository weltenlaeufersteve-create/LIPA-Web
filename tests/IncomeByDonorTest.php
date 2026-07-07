<?php
namespace Tests;
use App\Models\Income;
use App\Models\Contact;
use App\Models\Account;
use App\Database;

final class IncomeByDonorTest extends DatabaseTestCase
{
    public function test_lists_only_donor_contacts_desc_excluding_nondonor_income(): void
    {
        $acc = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $big = Contact::create(['type'=>'donor','name'=>'Global Fund','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        $small = Contact::create(['type'=>'donor','name'=>'Diakonie','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        $vendor = Contact::create(['type'=>'vendor','name'=>'Some Vendor','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        $pdo = Database::pdo();
        // Global Fund: 500 + 300 = 800; Diakonie: 200
        $pdo->exec("INSERT INTO income (date,account_id,contact_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-01',$acc,$big,'TZS',500,1,500)");
        $pdo->exec("INSERT INTO income (date,account_id,contact_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-02',$acc,$big,'TZS',300,1,300)");
        $pdo->exec("INSERT INTO income (date,account_id,contact_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-03',$acc,$small,'TZS',200,1,200)");
        // excluded: bank interest with no contact (NULL)
        $pdo->exec("INSERT INTO income (date,account_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-04',$acc,'TZS',100,1,100)");
        // excluded: income linked to a vendor contact (shouldn't happen via UI, but guard the query)
        $pdo->exec("INSERT INTO income (date,account_id,contact_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-05',$acc,$vendor,'TZS',999,1,999)");

        $rows = Income::byDonor(['date_from'=>'2026-02-01','date_to'=>'2026-02-28']);

        // only donor-type contacts, descending by total: Global Fund 800, Diakonie 200
        $this->assertCount(2, $rows);
        $this->assertSame('Global Fund', $rows[0]['name']);
        $this->assertEqualsWithDelta(800.0, (float)$rows[0]['total'], 0.001);
        $this->assertSame('Diakonie', $rows[1]['name']);
        $this->assertEqualsWithDelta(200.0, (float)$rows[1]['total'], 0.001);
        $this->assertNotContains(null, array_column($rows, 'name'));
    }
}
