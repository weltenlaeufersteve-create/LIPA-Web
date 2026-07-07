<?php
namespace App\Reports;

final class ReceiptAppendix
{
    /**
     * Split expense rows into image vs PDF receipts, each sorted ascending by date.
     * Rows without a receipt_path are dropped.
     *
     * @param array<int,array> $expenseLines
     * @return array{images: array<int,array>, pdfs: array<int,array>}
     */
    public static function fromExpenses(array $expenseLines): array
    {
        $images = [];
        $pdfs = [];
        foreach ($expenseLines as $r) {
            if (empty($r['receipt_path'])) {
                continue;
            }
            $ext = strtolower(pathinfo((string)$r['receipt_path'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $pdfs[] = $r;
            } else {
                $images[] = $r;
            }
        }
        $byDate = static fn(array $a, array $b): int => strcmp((string)$a['date'], (string)$b['date']);
        usort($images, $byDate);
        usort($pdfs, $byDate);
        return ['images' => $images, 'pdfs' => $pdfs];
    }
}
