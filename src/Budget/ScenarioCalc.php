<?php
namespace App\Budget;

/**
 * Pure calculation for a budget scenario — the single source of truth.
 * public/assets/js/budget.js mirrors these formulas for live preview only;
 * this PHP result is what is stored/printed/tested. No HTTP, no DB.
 */
final class ScenarioCalc
{
    public static function compute(array $scenario, array $products, array $items, array $allocations): array
    {
        $r2 = static fn($v) => round((float)$v, 2);
        $sum = static fn(array $rows, string $type) => array_sum(array_map(
            static fn($i) => ($i['item_type'] ?? '') === $type ? (float)$i['amount'] : 0.0, $rows));

        // Seed money for the first batch of each product = Σ (unit cost × batch yield).
        // One-time working capital added to the NGO's own share after partner funding;
        // never a recurring cost (the recurring batches live in variable costs).
        $firstBatch = 0.0;
        foreach ($products as $p) {
            $firstBatch += (float)($p['unit_cost'] ?? 0) * max((int)($p['batch_yield'] ?? 1), 1);
        }
        $seedIncluded = !empty($scenario['include_first_batch']);
        $seed         = $seedIncluded ? $firstBatch : 0.0;

        $oneTime     = $sum($items, 'one_time');   // equipment / one-off items only
        $fixed       = $sum($items, 'monthly_fixed');
        $funded      = (float)($scenario['funded_amount'] ?? 0);
        $fullStartup = $oneTime + $seed;           // total the venture must fund up front
        $net         = max($fullStartup - $funded, 0.0);

        $totals = [];
        foreach (['low', 'mid', 'high'] as $k) {
            $totals[$k] = ['units_total' => 0, 'revenue' => 0.0, 'variable' => 0.0];
        }
        $prodOut = [];
        foreach ($products as $p) {
            $price  = (float)($p['sale_price'] ?? 0);
            $cost   = (float)($p['unit_cost'] ?? 0);
            $margin = $price - $cost;
            $units  = ['low'=>(int)($p['units_low'] ?? 0), 'mid'=>(int)($p['units_mid'] ?? 0), 'high'=>(int)($p['units_high'] ?? 0)];
            $contrib = [];
            foreach ($units as $k => $u) {
                $contrib[$k] = $r2($u * $margin);
                $totals[$k]['units_total'] += $u;
                $totals[$k]['revenue']  += $u * $price;
                $totals[$k]['variable'] += $u * $cost;
            }
            $prodOut[] = [
                'name'=>$p['name'] ?? '', 'unit_name'=>$p['unit_name'] ?? 'unit',
                'sale_price'=>$r2($price), 'unit_cost'=>$r2($cost),
                'margin'=>$r2($margin), 'margin_negative'=>$margin <= 0,
                'units'=>$units, 'contribution'=>$contrib,
            ];
        }

        $caseOut = [];
        foreach ($totals as $k => $t) {
            $profit = $t['revenue'] - $t['variable'] - $fixed;
            $caseOut[$k] = [
                'units_total'=>$t['units_total'],
                'revenue'=>$r2($t['revenue']),
                'variable'=>$r2($t['variable']),
                'fixed'=>$r2($fixed),
                'profit'=>$r2($profit),
                'break_even'=>$profit > 0 ? $r2($net / $profit) : null,
                'break_even_unfunded'=>$profit > 0 ? $r2($fullStartup / $profit) : null,
            ];
        }

        // allocation waterfall on the realistic (mid) profit
        $remaining = max($caseOut['mid']['profit'], 0.0);
        $allocOut = [];
        foreach ($allocations as $a) {
            $amt = (float)($a['monthly_amount'] ?? 0);
            $cov = $amt > 0 ? min($remaining / $amt, 1.0) : 0.0;
            $remaining = max($remaining - $amt, 0.0);
            $allocOut[] = ['name'=>$a['name'] ?? '', 'monthly_amount'=>$r2($amt), 'coverage_pct'=>(int)round($cov * 100)];
        }
        $leftover = $r2($remaining);
        if ($caseOut['mid']['profit'] <= 0) {
            $note = 'No profit to allocate in the realistic case.';
        } elseif ($allocOut && $leftover > 0) {
            $note = 'All covered — ' . number_format($leftover, 0) . ' TZS/month left for reserves.';
        } elseif ($allocOut) {
            $note = 'Profit does not fully cover the allocations at the realistic volume.';
        } else {
            $note = '';
        }

        return [
            'one_time_total'=>$r2($oneTime), 'net_startup'=>$r2($net), 'fixed_total'=>$r2($fixed),
            'first_batch_total'=>$r2($firstBatch), 'first_batch_included'=>$seedIncluded,
            'products'=>$prodOut, 'cases'=>$caseOut,
            'allocations'=>$allocOut, 'alloc_leftover'=>$leftover, 'alloc_note'=>$note,
        ];
    }
}
