<?php
namespace Tests;
use App\Reports\ProjectStatement;
use App\Models\Project;
use App\Database;

final class ProjectStatementTest extends DatabaseTestCase
{
    public function test_build_computes_opening_received_spent_closing(): void
    {
        $pid = Project::create(['name'=>'Verein 2026','code'=>'','description'=>'']);
        $pdo = Database::pdo();
        // before the period: income 1000, expense 300  -> opening 700
        $pdo->exec("INSERT INTO income (date,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-01-05',$pid,'TZS',1000,1,1000)");
        $pdo->exec("INSERT INTO expenses (date,project_id,amount_tzs) VALUES ('2026-01-06',$pid,300)");
        // in the period: income 500, expense 200
        $pdo->exec("INSERT INTO income (date,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-10',$pid,'TZS',500,1,500)");
        $pdo->exec("INSERT INTO expenses (date,project_id,amount_tzs) VALUES ('2026-02-15',$pid,200)");
        // after the period (must be ignored entirely)
        $pdo->exec("INSERT INTO income (date,project_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-03-10',$pid,'TZS',9999,1,9999)");

        $d = ProjectStatement::build($pid, '2026-02-01', '2026-02-28');
        $this->assertSame('Verein 2026', $d['project']['name']);
        $this->assertEqualsWithDelta(700.0, $d['opening'], 0.001);
        $this->assertEqualsWithDelta(500.0, $d['received'], 0.001);
        $this->assertEqualsWithDelta(200.0, $d['spent'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $d['closing'], 0.001);
        $this->assertCount(1, $d['income_lines']);   // only the in-period income
        $this->assertCount(1, $d['expense_lines']);   // only the in-period expense
    }

    public function test_build_returns_null_project_for_unknown_id(): void
    {
        $d = ProjectStatement::build(99999, '2026-01-01', '2026-12-31');
        $this->assertNull($d['project']);
        $this->assertEqualsWithDelta(0.0, $d['closing'], 0.001);
    }
}
