<?php

namespace App\Http\Controllers;

use App\Http\Resources\NoteResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Note;
use App\Models\ShareLink;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicShareController extends Controller
{
    public const SESSION_TOKENS_KEY = 'public_share_tokens';

    public function __invoke(Request $request, string $token): Response
    {
        $link = ShareLink::query()->where('token', $token)->firstOrFail();
        abort_unless($link->isUsable(), 404);

        $shareable = $link->shareable;
        abort_unless($shareable, 404);

        if ($shareable instanceof Note) {
            abort_if($shareable->trashed() || $shareable->is_archived, 404);
            abort_if($shareable->workspace?->trashed() || $shareable->workspace?->is_archived, 404);
        }

        if ($shareable instanceof Workspace) {
            abort_if($shareable->trashed() || $shareable->is_archived, 404);
        }

        $this->rememberShareToken($request, $token);

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

    private function rememberShareToken(Request $request, string $token): void
    {
        $tokens = $request->session()->get(self::SESSION_TOKENS_KEY, []);
        if (! is_array($tokens)) {
            $tokens = [];
        }

        $tokens[] = $token;
        $tokens = array_values(array_unique(array_filter(
            $tokens,
            fn ($value) => is_string($value) && $value !== '',
        )));

        if (count($tokens) > 20) {
            $tokens = array_slice($tokens, -20);
        }

        $request->session()->put(self::SESSION_TOKENS_KEY, $tokens);
    }
}
