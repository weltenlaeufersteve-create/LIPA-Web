<?php
namespace App\Reports;

use App\Models\Income;
use App\Models\Expense;
use App\Models\Project;

final class ProjectStatement
{
    public static function build(int $projectId, string $from, string $to): array
    {
        $project = Project::find($projectId);
        $before  = (new \DateTime($from))->modify('-1 day')->format('Y-m-d');
        $period  = ['project_id' => $projectId, 'date_from' => $from, 'date_to' => $to];
        $prior   = ['project_id' => $projectId, 'date_to' => $before];

        $opening  = round(Income::totalTzs($prior) - Expense::totalTzs($prior), 2);
        $received = round(Income::totalTzs($period), 2);
        $spent    = round(Expense::totalTzs($period), 2);

        return [
            'project'  => $project,
            'from'     => $from,
            'to'       => $to,
            'opening'  => $opening,
            'received' => $received,
            'spent'    => $spent,
            'closing'  => round($opening + $received - $spent, 2),
            'income_lines'        => Income::all($period),
            'expense_by_category' => Expense::byCategory($period),
            'expense_lines'       => Expense::all($period),
        ];
    }
}
