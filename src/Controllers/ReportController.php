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
}
