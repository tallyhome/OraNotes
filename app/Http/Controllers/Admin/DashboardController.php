<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'active_users' => User::query()->where('is_active', true)->count(),
                'workspaces' => Workspace::query()->count(),
                'notes' => Note::query()->count(),
                'trashed_notes' => Note::onlyTrashed()->count(),
            ],
            'recentActivity' => ActivityLog::query()
                ->with('user')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (ActivityLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action?->value,
                    'user' => $log->user?->name,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'properties' => $log->properties,
                ]),
        ]);
    }
}
