<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private AdminDashboardService $dashboard,
    ) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $period = (string) $request->query('period', 'today');
        $dateFrom = $request->query('date_from') !== null
            ? (string) $request->query('date_from')
            : null;
        $dateTo = $request->query('date_to') !== null
            ? (string) $request->query('date_to')
            : null;

        if (! in_array($period, ['today', '7d', '30d', 'month', 'custom'], true)) {
            $period = 'today';
        }

        $data = $this->dashboard->build($actor, $period, $dateFrom, $dateTo);

        return Inertia::render('admin/Dashboard', $data);
    }
}
