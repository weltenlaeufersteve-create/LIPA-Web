<?php
namespace App\Reports;

use App\Models\Income;
use App\Models\Expense;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

final class ExcelExport
{
    public static function build(array $filters): Spreadsheet
    {
        $book = new Spreadsheet();
        $income = Income::all($filters);
        $expense = Expense::all($filters);
        $incTotal = Income::totalTzs($filters);
        $expTotal = Expense::totalTzs($filters);

        // 1. Overview
        $s = $book->getActiveSheet();
        $s->setTitle('Overview');
        $s->fromArray([
            ['LIPA — Income & Expenditure'],
            ['Period', ($filters['date_from'] ?? 'all') . ' to ' . ($filters['date_to'] ?? 'all')],
            [],
            ['Total income (TZS)', $incTotal],
            ['Total expenses (TZS)', $expTotal],
            ['Balance (TZS)', $incTotal - $expTotal],
        ], null, 'A1');

        // 2. Income
        $s = $book->createSheet(); $s->setTitle('Income');
        $s->fromArray(['Date','Donor','Category','Project','Description','Currency','Amount (orig.)','Exchange rate','Amount (TZS)','Reference'], null, 'A1');
        $row = 2;
        foreach ($income as $r) {
            $s->fromArray([
                $r['date'], $r['contact_name'], $r['category_name'], $r['project_name'], $r['description'],
                $r['currency'], (float)$r['amount_original'], (float)$r['exchange_rate'], (float)$r['amount_tzs'], $r['reference'],
            ], null, 'A' . $row++);
        }

        // 3. Expenses
        $s = $book->createSheet(); $s->setTitle('Expenses');
        $s->fromArray(['Date','Vendor','Category','Project','Description','Amount (TZS)','Reference'], null, 'A1');
        $row = 2;
        foreach ($expense as $r) {
            $s->fromArray([
                $r['date'], $r['contact_name'], $r['category_name'], $r['project_name'], $r['description'],
                (float)$r['amount_tzs'], $r['reference'],
            ], null, 'A' . $row++);
        }

        // 4. Income by category
        $s = $book->createSheet(); $s->setTitle('Income by category');
        $s->fromArray(['Category','Total (TZS)'], null, 'A1');
        $row = 2;
        foreach (Income::byCategory($filters) as $r) { $s->fromArray([$r['name'] ?? '(none)', (float)$r['total']], null, 'A' . $row++); }

        // 5. Expenses by category
        $s = $book->createSheet(); $s->setTitle('Expenses by category');
        $s->fromArray(['Category','Total (TZS)'], null, 'A1');
        $row = 2;
        foreach (Expense::byCategory($filters) as $r) { $s->fromArray([$r['name'] ?? '(none)', (float)$r['total']], null, 'A' . $row++); }

        // 6. By project (income, expense, balance)
        $s = $book->createSheet(); $s->setTitle('By project');
        $s->fromArray(['Project','Income (TZS)','Expenses (TZS)','Balance (TZS)'], null, 'A1');
        $proj = [];
        foreach (Income::byProject($filters) as $r)  { $proj[$r['name'] ?? '(none)']['inc'] = (float)$r['total']; }
        foreach (Expense::byProject($filters) as $r) { $proj[$r['name'] ?? '(none)']['exp'] = (float)$r['total']; }
        $row = 2;
        foreach ($proj as $name => $v) {
            $inc = $v['inc'] ?? 0; $exp = $v['exp'] ?? 0;
            $s->fromArray([$name, $inc, $exp, $inc - $exp], null, 'A' . $row++);
        }

        $book->setActiveSheetIndex(0);
        return $book;
    }
}
