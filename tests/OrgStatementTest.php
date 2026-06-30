<?php
namespace Tests;
use App\Reports\OrgStatement;
use App\Models\Account;
use App\Models\Project;
use App\Database;

final class OrgStatementTest extends DatabaseTestCase
{
    public function test_build_reconciles_opening_net_closing(): void
    {
        $acc = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>1000,'opening_balance_date'=>'2026-01-01']);
        $pid = Project::create(['name'=>'Water','code'=>'','description'=>'']);
        $pdo = Database::pdo();
        // before period: income 500 (counts into Opening)
        $pdo->exec("INSERT INTO income (date,account_id,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-01-10',$acc,$pid,'TZS',500,1,500)");
        // in period: income 800, expense 300
        $pdo->exec("INSERT INTO income (date,account_id,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-10',$acc,$pid,'TZS',800,1,800)");
        $pdo->exec("INSERT INTO expenses (date,account_id,project_id,amount_tzs) VALUES ('2026-02-15',$acc,$pid,300)");

        $d = OrgStatement::build('2026-02-01', '2026-02-28');
        // opening = account opening 1000 + prior income 500 = 1500
        $this->assertEqualsWithDelta(1500.0, $d['opening'], 0.001);
        $this->assertEqualsWithDelta(800.0, $d['income'], 0.001);
        $this->assertEqualsWithDelta(300.0, $d['expenses'], 0.001);
        $this->assertEqualsWithDelta(500.0, $d['net'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $d['closing'], 0.001);
        // reconciliation invariant
        $this->assertEqualsWithDelta($d['opening'] + $d['net'], $d['closing'], 0.001);
    }

    public function test_build_by_project_and_balances(): void
    {
        $acc = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $pid = Project::create(['name'=>'Water','code'=>'','description'=>'']);
        $pdo = Database::pdo();
        $pdo->exec("INSERT INTO income (date,account_id,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-10',$acc,$pid,'TZS',800,1,800)");
        $pdo->exec("INSERT INTO expenses (date,account_id,project_id,amount_tzs) VALUES ('2026-02-15',$acc,$pid,300)");

        $d = OrgStatement::build('2026-02-01', '2026-02-28');
        $this->assertSame('Water', $d['by_project'][0]['name']);
        $this->assertEqualsWithDelta(800.0, $d['by_project'][0]['income'], 0.001);
        $this->assertEqualsWithDelta(300.0, $d['by_project'][0]['expense'], 0.001);
        $this->assertEqualsWithDelta(500.0, $d['by_project'][0]['balance'], 0.001);
        $this->assertSame('Bank', $d['balances'][0]['name']);
        $this->assertEqualsWithDelta(500.0, $d['balances'][0]['balance'], 0.001);
    }
}
