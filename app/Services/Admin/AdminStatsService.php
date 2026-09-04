<?php

namespace App\Services\Admin;

use App\Models\ActivityLog;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\ShareLink;
use App\Models\User;
use App\Models\Workspace;
use App\Services\System\HealthService;
use Illuminate\Support\Facades\DB;

class AdminStatsService
{
    public function __construct(private HealthService $health) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $health = $this->health->summary();

        return [
            'users' => User::query()->count(),
            'active_users' => User::query()->where('is_active', true)->count(),
            'disabled_users' => User::query()->where('is_active', false)->count(),
            'new_users_7d' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'workspaces' => Workspace::query()->count(),
            'archived_workspaces' => Workspace::query()->where('is_archived', true)->count(),
            'locked_workspaces' => Workspace::query()->where('is_locked', true)->count(),
            'notes' => Note::query()->count(),
            'archived_notes' => Note::query()->where('is_archived', true)->count(),
            'trashed_notes' => Note::onlyTrashed()->count(),
            'recent_notes_24h' => Note::query()->where('created_at', '>=', now()->subDay())->count(),
            'attachments' => Attachment::query()->count(),
            'versions' => NoteVersion::query()->count(),
            'share_links' => ShareLink::query()->where('is_revoked', false)->count(),
            'activity_24h' => ActivityLog::query()->where('created_at', '>=', now()->subDay())->count(),
            'storage_bytes' => (int) Attachment::query()->sum('size'),
            'failed_jobs' => $this->failedJobs(),
            'cache' => config('cache.default'),
            'queue' => config('queue.default'),
            'oranotes_version' => config('oranotes.version'),
            'oraeditor_version' => config('oranotes.ora_editor_version'),
            'health' => $health,
        ];
    }

    private function failedJobs(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
