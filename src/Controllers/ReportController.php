<?php
namespace App\Controllers;

use App\Auth;
use App\Reports\ExcelExport;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ReportController
{
    public function index(): string
    {
        Auth::requireRole('admin','editor','viewer');
        return render('reports/index', [
            'date_from'=>$_GET['date_from'] ?? (date('Y') . '-01-01'),
            'date_to'=>$_GET['date_to'] ?? (date('Y') . '-12-31'),
            'projects'=>\App\Models\Project::all(true),
        ], 'Reports');
    }

    public function export(): never
    {
        Auth::requireRole('admin','editor','viewer');
        $filters = [
            'date_from'=>$_GET['date_from'] ?? '', 'date_to'=>$_GET['date_to'] ?? '',
        ];
        $book = ExcelExport::build($filters);
        $name = 'lipa-report-' . date('Ymd-His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($book))->save('php://output');
        exit;
    }

    public function statement(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $projectId = (int)($_GET['project_id'] ?? 0);
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';
        $valid = $projectId > 0
            && \DateTime::createFromFormat('Y-m-d', $from)
            && \DateTime::createFromFormat('Y-m-d', $to);

        if (!$valid) {
            return '<p style="font-family:sans-serif;padding:24px">Please choose a project and valid dates. <a href="/reports">Back to Reports</a>.</p>';
        }
        $d = \App\Reports\ProjectStatement::build($projectId, $from, $to);
        if (!$d['project']) {
            return '<p style="font-family:sans-serif;padding:24px">Project not found. <a href="/reports">Back to Reports</a>.</p>';
        }
        $s = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/reports/statement.php';
        return ob_get_clean();
    }

    public function orgStatement(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';
        if (!\DateTime::createFromFormat('Y-m-d', $from) || !\DateTime::createFromFormat('Y-m-d', $to)) {
            return '<p style="font-family:sans-serif;padding:24px">Please choose valid dates. <a href="/reports">Back to Reports</a>.</p>';
        }
        $d = \App\Reports\OrgStatement::build($from, $to);
        $s = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/reports/org_statement.php';
        return ob_get_clean();
    }

    public function sankey(): string
    {
        Auth::requireRole('admin');
        $from = $_GET['date_from'] ?? (date('Y') . '-01-01');
        $to   = $_GET['date_to'] ?? (date('Y') . '-12-31');
        if (!\DateTime::createFromFormat('Y-m-d', $from) || !\DateTime::createFromFormat('Y-m-d', $to)) {
            return '<p style="font-family:sans-serif;padding:24px">Please choose valid dates. <a href="/reports">Back to Reports</a>.</p>';
        }
        $d = \App\Reports\MoneyFlow::build($from, $to);
        $s = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/reports/sankey.php';
        return ob_get_clean();
    }

    public function activityReport(): string
    {
        Auth::requireRole('admin','editor','viewer');
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';
        if (!\DateTime::createFromFormat('Y-m-d', $from) || !\DateTime::createFromFormat('Y-m-d', $to)) {
            return '<p style="font-family:sans-serif;padding:24px">Please choose valid dates. <a href="/reports">Back to Reports</a>.</p>';
        }
        $projectId = (int)($_GET['project_id'] ?? 0) ?: null;
        $d = \App\Reports\ActivityReport::build($from, $to, $projectId);
        $s = \App\Models\Setting::all();
        ob_start();
        include dirname(__DIR__, 2) . '/views/reports/activity_report.php';
        return ob_get_clean();
    }
}
