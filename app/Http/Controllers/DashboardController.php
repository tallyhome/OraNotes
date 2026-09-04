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
            ->withCount('notes')
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $recentNotes = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->whereHas('workspace', fn ($q) => $q->visibleTo($user))
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $favorites = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->where('user_id', $user->id)
            ->where('is_favorite', true)
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $shared = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->whereHas('shares', fn ($q) => $q->where('user_id', $user->id))
            ->latest('updated_at')
            ->limit(8)
            ->get();

        return Inertia::render('Dashboard', [
            'workspaces' => $workspaces->map(fn (Workspace $w) => WorkspaceResource::makeArray($w)),
            'recentNotes' => $recentNotes->map(fn (Note $n) => NoteResource::makeArray($n)),
            'favorites' => $favorites->map(fn (Note $n) => NoteResource::makeArray($n)),
            'shared' => $shared->map(fn (Note $n) => NoteResource::makeArray($n)),
        ]);
    }
}
