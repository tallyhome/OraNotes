<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Admin\AdminStatsService;
use App\Services\Update\UpdateService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(AdminStatsService $stats, UpdateService $updates): Response
    {
        $update = $updates->status();

        return Inertia::render('Admin/Dashboard', [
            'stats' => $stats->dashboard(),
            'update' => $update,
            'recentActivity' => ActivityLog::query()
                ->with('user')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action?->value,
                    'user' => $log->user?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'properties' => $log->properties,
                    'ip_address' => $log->ip_address,
                ]),
        ]);
    }
}
