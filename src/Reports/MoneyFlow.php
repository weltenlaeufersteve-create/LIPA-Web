<?php
namespace App\Reports;

use App\Database;

/**
 * Aggregates a period's cashbook into a Sankey payload:
 * income sources (col 0) -> accounts (col 1, with transfers) -> expense categories (col 2).
 * Money is fungible; the account is the hub, not a 1:1 income-to-expense mapping.
 */
final class MoneyFlow
{
    public static function build(string $from, string $to): array
    {
        $pdo = Database::pdo();
        $p = [':from'=>$from, ':to'=>$to];

        $income = self::rows($pdo, p:$p, sql:
            "SELECT CASE WHEN c.type='donor' THEN c.name ELSE 'Other income' END AS source,
                    COALESCE(a.name,'Unassigned') AS acct, SUM(i.amount_tzs) AS total
             FROM income i
             LEFT JOIN contacts c ON c.id = i.contact_id
             LEFT JOIN accounts a ON a.id = i.account_id
             WHERE i.date BETWEEN :from AND :to
             GROUP BY source, acct HAVING total > 0");

        $transfers = self::rows($pdo, p:$p, sql:
            "SELECT COALESCE(af.name,'Unassigned') AS f, COALESCE(at2.name,'Unassigned') AS t,
                    SUM(tr.amount_tzs) AS total
             FROM transfers tr
             LEFT JOIN accounts af  ON af.id  = tr.from_account_id
             LEFT JOIN accounts at2 ON at2.id = tr.to_account_id
             WHERE tr.date BETWEEN :from AND :to
             GROUP BY f, t HAVING total > 0");

        $expenses = self::rows($pdo, p:$p, sql:
            "SELECT COALESCE(a.name,'Unassigned') AS acct,
                    COALESCE(cat.name,'(uncategorised)') AS category, SUM(e.amount_tzs) AS total
             FROM expenses e
             LEFT JOIN accounts a   ON a.id  = e.account_id
             LEFT JOIN categories cat ON cat.id = e.category_id
             WHERE e.date BETWEEN :from AND :to
             GROUP BY acct, category HAVING total > 0");

        $nodes = [];   // id => ['id'=>,'label'=>,'col'=>]
        $add = static function (string $id, string $label, int $col) use (&$nodes): void {
            if (!isset($nodes[$id])) { $nodes[$id] = ['id'=>$id, 'label'=>$label, 'col'=>$col]; }
        };
        $links = [];
        $totals = ['in'=>0.0, 'transfer'=>0.0, 'out'=>0.0];

        foreach ($income as $r) {
            $s = 'src:' . $r['source']; $t = 'acc:' . $r['acct'];
            $add($s, $r['source'], 0); $add($t, $r['acct'], 1);
            $links[] = ['s'=>$s, 't'=>$t, 'v'=>(float)$r['total'], 'kind'=>'in'];
            $totals['in'] += (float)$r['total'];
        }
        foreach ($transfers as $r) {
            $s = 'acc:' . $r['f']; $t = 'acc:' . $r['t'];
            $add($s, $r['f'], 1); $add($t, $r['t'], 1);
            $links[] = ['s'=>$s, 't'=>$t, 'v'=>(float)$r['total'], 'kind'=>'transfer'];
            $totals['transfer'] += (float)$r['total'];
        }
        foreach ($expenses as $r) {
            $s = 'acc:' . $r['acct']; $t = 'exp:' . $r['category'];
            $add($s, $r['acct'], 1); $add($t, $r['category'], 2);
            $links[] = ['s'=>$s, 't'=>$t, 'v'=>(float)$r['total'], 'kind'=>'out'];
            $totals['out'] += (float)$r['total'];
        }

        return [
            'from'   => $from,
            'to'     => $to,
            'nodes'  => array_values($nodes),
            'links'  => $links,
            'totals' => $totals,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function rows(\PDO $pdo, string $sql, array $p): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        return $stmt->fetchAll();
    }
}
