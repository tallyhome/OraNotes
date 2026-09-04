<?php

namespace App\Http\Controllers;

use App\Http\Resources\NoteResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Note;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $workspaces = Workspace::query()
            ->visibleTo($user)
            ->where('is_archived', false)
            ->withCount('notes')
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $recentNotes = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->where('is_archived', false)
            ->whereHas('workspace', fn ($q) => $q->visibleTo($user)->where('is_archived', false))
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $favorites = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->where('user_id', $user->id)
            ->where('is_favorite', true)
            ->where('is_archived', false)
            ->whereHas('workspace', fn ($q) => $q->where('is_archived', false))
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $shared = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->where('is_archived', false)
            ->whereHas('shares', fn ($q) => $q->where('user_id', $user->id))
            ->whereHas('workspace', fn ($q) => $q->where('is_archived', false))
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $archivedWorkspaces = Workspace::query()
            ->where('user_id', $user->id)
            ->where('is_archived', true)
            ->withCount('notes')
            ->latest('updated_at')
            ->get();

        return Inertia::render('Dashboard', [
            'workspaces' => $workspaces->map(fn (Workspace $w) => WorkspaceResource::makeArray($w)),
            'archivedWorkspaces' => $archivedWorkspaces->map(fn (Workspace $w) => WorkspaceResource::makeArray($w)),
            'recentNotes' => $recentNotes->map(fn (Note $n) => NoteResource::makeArray($n)),
            'favorites' => $favorites->map(fn (Note $n) => NoteResource::makeArray($n)),
            'shared' => $shared->map(fn (Note $n) => NoteResource::makeArray($n)),
        ]);
    }
}
