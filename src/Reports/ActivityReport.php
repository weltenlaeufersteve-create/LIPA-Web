<?php
namespace App\Reports;

use App\Models\ActivityItem;

final class ActivityReport
{
    public static function build(string $from, string $to, ?int $projectId = null): array
    {
        $filters = ['date_from' => $from, 'date_to' => $to];
        if ($projectId) { $filters['project_id'] = $projectId; }

        $activities = [];
        $grand = 0.0;
        foreach (ActivityItem::all($filters) as $a) {
            $id = (int)$a['id'];
            $cost = ActivityItem::cost($id);
            $grand += $cost;
            $activities[] = [
                'activity' => $a,
                'photos'   => ActivityItem::photos($id),
                'expenses' => ActivityItem::expenses($id),
                'cost'     => $cost,
            ];
        }
        return ['from' => $from, 'to' => $to, 'activities' => $activities, 'grand_total' => round($grand, 2)];
    }
}
