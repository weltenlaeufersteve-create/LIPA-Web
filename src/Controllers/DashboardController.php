<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Income;
use App\Models\Expense;
use App\Models\Activity;

final class DashboardController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $f = [
            'date_from' => $_GET['date_from'] ?? (date('Y') . '-01-01'),
            'date_to'   => $_GET['date_to'] ?? (date('Y') . '-12-31'),
        ];
        $income = Income::totalTzs($f);
        $expense = Expense::totalTzs($f);

        // Merge per-project income & expense into one table keyed by project name.
        $proj = [];
        foreach (Income::byProject($f) as $r)  { $k = $r['name'] ?? '—'; $proj[$k]['income'] = (float)$r['total']; }
        foreach (Expense::byProject($f) as $r) { $k = $r['name'] ?? '—'; $proj[$k]['expense'] = (float)$r['total']; }

        return render('dashboard', [
            'f'=>$f, 'income'=>$income, 'expense'=>$expense, 'balance'=>$income - $expense,
            'projects'=>$proj, 'activity'=>Activity::recent(10),
        ], 'Dashboard');
    }
}
