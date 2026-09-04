<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->latest()
            ->paginate(40)
            ->withQueryString();

        return Inertia::render('Admin/Activity', [
            'logs' => $logs->through(fn (ActivityLog $log) => [
                'id' => $log->id,
                'action' => $log->action?->value,
                'user' => $log->user?->only(['id', 'name', 'email']),
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'properties' => $log->properties,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'filters' => $request->only(['action']),
        ]);
    }
}
