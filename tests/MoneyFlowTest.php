<?php
namespace Tests;
use App\Reports\MoneyFlow;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Project;
use App\Database;

final class MoneyFlowTest extends DatabaseTestCase
{
    private int $bank;
    private int $cash;

    protected function seed(): array
    {
        $this->bank = Account::create(['name'=>'Bank','type'=>'bank','opening_balance'=>0,'opening_balance_date'=>null]);
        $this->cash = Account::create(['name'=>'Cash','type'=>'cash','opening_balance'=>0,'opening_balance_date'=>null]);
        $donor = Contact::create(['type'=>'donor','name'=>'Global Fund','email'=>'','phone'=>'','address'=>'','notes'=>'']);
        $cat = \App\Models\Category::create(['type'=>'expense','name'=>'Salaries','sort_order'=>0]);
        $pdo = Database::pdo();
        // income: donor→bank 800; non-donor (no contact) bank interest→bank 50
        $pdo->exec("INSERT INTO income (date,account_id,contact_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-10',$this->bank,$donor,'TZS',800,1,800)");
        $pdo->exec("INSERT INTO income (date,account_id,currency,amount_original,exchange_rate,amount_tzs) VALUES ('2026-02-11',$this->bank,'TZS',50,1,50)");
        // expense: bank→Salaries 300; cash→(no category) 40
        $pdo->exec("INSERT INTO expenses (date,account_id,category_id,amount_tzs) VALUES ('2026-02-15',$this->bank,$cat,300)");
        $pdo->exec("INSERT INTO expenses (date,account_id,amount_tzs) VALUES ('2026-02-16',$this->cash,40)");
        return ['donor'=>$donor,'cat'=>$cat];
    }

    private function link(array $d, string $s, string $t): ?array
    {
        foreach ($d['links'] as $l) { if ($l['s']===$s && $l['t']===$t) return $l; }
        return null;
    }

    public function test_income_groups_source_to_account_with_other_bucket(): void
    {
        $this->seed();
        $d = MoneyFlow::build('2026-02-01','2026-02-28');
        $this->assertSame(800.0, (float)$this->link($d,'src:Global Fund','acc:Bank')['v']);
        $this->assertSame('in', $this->link($d,'src:Global Fund','acc:Bank')['kind']);
        // non-donor income bucketed as "Other income"
        $this->assertSame(50.0, (float)$this->link($d,'src:Other income','acc:Bank')['v']);
    }

    public function test_expense_groups_account_to_category_with_uncategorised(): void
    {
        $this->seed();
        $d = MoneyFlow::build('2026-02-01','2026-02-28');
        $this->assertSame(300.0, (float)$this->link($d,'acc:Bank','exp:Salaries')['v']);
        $this->assertSame('out', $this->link($d,'acc:Bank','exp:Salaries')['kind']);
        $this->assertSame(40.0, (float)$this->link($d,'acc:Cash','exp:(uncategorised)')['v']);
    }

    public function test_transfer_links_present_and_columns_correct(): void
    {
        $this->seed();
        $pdo = Database::pdo();
        $pdo->exec("INSERT INTO transfers (date,from_account_id,to_account_id,amount_tzs) VALUES ('2026-02-20',$this->bank,$this->cash,150)");
        $d = MoneyFlow::build('2026-02-01','2026-02-28');
        $tr = $this->link($d,'acc:Bank','acc:Cash');
        $this->assertSame(150.0, (float)$tr['v']);
        $this->assertSame('transfer', $tr['kind']);
        // node columns: sources col0, accounts col1, expenses col2; ids unique
        $byId = [];
        foreach ($d['nodes'] as $n) { $byId[$n['id']] = $n['col']; }
        $this->assertSame(0, $byId['src:Global Fund']);
        $this->assertSame(1, $byId['acc:Bank']);
        $this->assertSame(2, $byId['exp:Salaries']);
        $this->assertCount(count($byId), $d['nodes'], 'node ids are unique');
    }

    public function test_no_transfers_yields_no_transfer_links(): void
    {
        $this->seed();
        $d = MoneyFlow::build('2026-02-01','2026-02-28');
        $kinds = array_column($d['links'], 'kind');
        $this->assertNotContains('transfer', $kinds);
    }

    public function test_respects_date_range(): void
    {
        $this->seed();
        $d = MoneyFlow::build('2026-03-01','2026-03-31');
        $this->assertSame([], $d['links']);
        $this->assertSame([], $d['nodes']);
    }
}
