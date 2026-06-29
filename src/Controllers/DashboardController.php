<?php
namespace App\Controllers;

use App\Auth;

final class DashboardController
{
    public function index(): string
    {
        Auth::requireRole('admin', 'editor', 'viewer');
        return render('dashboard', [], 'Dashboard');
    }
}
