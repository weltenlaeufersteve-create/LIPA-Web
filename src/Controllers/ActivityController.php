<?php
namespace App\Controllers;

use App\Auth;
use App\Models\Activity;

// Audit-log controller (/activity). Distinct from ActivitiesController (/activities).
final class ActivityController
{
    public function index(): string
    {
        Auth::requireRole('admin','viewer');
        return render('activity/index', ['rows'=>Activity::recent(100)], 'Activity log');
    }
}
