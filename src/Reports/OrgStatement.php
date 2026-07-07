<?php
namespace App\Reports;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Account;
use App\Reports\ReceiptAppendix;

final class OrgStatement
{
    public static function build(string $from, string $to): array
    {
        $before = (new \DateTime($from))->modify('-1 day')->format('Y-m-d');
        $period = ['date_from' => $from, 'date_to' => $to];

        $opening = 0.0; $closing = 0.0; $balances = [];
        foreach (Account::all(true) as $a) {
            $opening += Account::balance((int)$a['id'], $before);
            $bal = Account::balance((int)$a['id'], $to);
            $closing += $bal;
            $balances[] = ['name' => $a['name'], 'balance' => $bal];
        }

        $income   = round(Income::totalTzs($period), 2);
        $expenses = round(Expense::totalTzs($period), 2);

        // Merge income & expense per project (NULL project -> '—').
        $proj = [];
        foreach (Income::byProject($period) as $r)  { $k = $r['name'] ?? '—'; $proj[$k]['income']  = (float)$r['total']; }
        foreach (Expense::byProject($period) as $r) { $k = $r['name'] ?? '—'; $proj[$k]['expense'] = (float)$r['total']; }
        $byProject = [];
        foreach ($proj as $name => $v) {
            $inc = $v['income'] ?? 0; $exp = $v['expense'] ?? 0;
            $byProject[] = ['name' => $name, 'income' => $inc, 'expense' => $exp, 'balance' => $inc - $exp];
        }

        $appendix = ReceiptAppendix::fromExpenses(Expense::all($period));

        return [
            'from' => $from, 'to' => $to,
            'opening'  => round($opening, 2),
            'income'   => $income,
            'expenses' => $expenses,
            'net'      => round($income - $expenses, 2),
            'closing'  => round($closing, 2),
            'income_by_category'  => Income::byCategory($period),
            'expense_by_category' => Expense::byCategory($period),
            'by_project' => $byProject,
            'balances'   => $balances,
            'receipt_images' => $appendix['images'],
            'receipt_pdfs'   => $appendix['pdfs'],
        ];
    }
}
