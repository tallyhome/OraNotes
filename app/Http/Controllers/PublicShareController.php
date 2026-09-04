<?php

namespace App\Http\Controllers;

use App\Http\Resources\NoteResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Note;
use App\Models\ShareLink;
use App\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class PublicShareController extends Controller
{
    public function __invoke(string $token): Response
    {
        $link = ShareLink::query()->where('token', $token)->firstOrFail();
        abort_unless($link->isUsable(), 404);

        $shareable = $link->shareable;
        abort_unless($shareable, 404);

        if ($shareable instanceof Note) {
            $shareable->load(['tags', 'author', 'workspace']);

            return Inertia::render('Public/SharedNote', [
                'note' => NoteResource::makeArray($shareable, includeDocument: true),
                'readOnly' => true,
            ]);
        }

        if ($shareable instanceof Workspace) {
            $notes = $shareable->notes()->with(['tags', 'author'])->where('is_archived', false)->get();

            return Inertia::render('Desktop/Show', [
                'workspace' => WorkspaceResource::makeArray($shareable),
                'notes' => $notes->map(fn (Note $n) => NoteResource::makeArray($n, includeDocument: true))->values(),
                'canEdit' => false,
                'canManage' => false,
                'isOwner' => false,
                'publicShare' => true,
                'shareLinks' => [],
            ]);
        }

        abort(404);
    }
}
