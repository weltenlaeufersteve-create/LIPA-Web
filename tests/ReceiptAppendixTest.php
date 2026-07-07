<?php
namespace Tests;
use App\Reports\ReceiptAppendix;

final class ReceiptAppendixTest extends \PHPUnit\Framework\TestCase
{
    private function rows(): array
    {
        return [
            ['id'=>1, 'date'=>'2026-03-10', 'receipt_path'=>'expense_1_aa.jpg'],
            ['id'=>2, 'date'=>'2026-03-02', 'receipt_path'=>'expense_2_bb.PDF'],
            ['id'=>3, 'date'=>'2026-03-05', 'receipt_path'=>''],            // no receipt
            ['id'=>4, 'date'=>'2026-03-01', 'receipt_path'=>'expense_4_cc.png'],
            ['id'=>5, 'date'=>'2026-03-08', 'receipt_path'=>'expense_5_dd.pdf'],
        ];
    }

    public function test_splits_by_extension_case_insensitively(): void
    {
        $out = ReceiptAppendix::fromExpenses($this->rows());
        $this->assertSame([4, 1], array_column($out['images'], 'id')); // png+jpg, date asc
        $this->assertSame([2, 5], array_column($out['pdfs'], 'id'));   // .PDF + .pdf, date asc
    }

    public function test_drops_rows_without_receipt(): void
    {
        $out = ReceiptAppendix::fromExpenses($this->rows());
        $ids = array_merge(array_column($out['images'], 'id'), array_column($out['pdfs'], 'id'));
        $this->assertNotContains(3, $ids);
    }

    public function test_empty_input(): void
    {
        $this->assertSame(['images'=>[], 'pdfs'=>[]], ReceiptAppendix::fromExpenses([]));
    }
}
