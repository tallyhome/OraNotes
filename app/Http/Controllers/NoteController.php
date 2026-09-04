<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotePositionsRequest;
use App\Http\Requests\NoteStoreRequest;
use App\Http\Requests\NoteUpdateRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Models\Workspace;
use App\Services\NoteService;
use App\Support\OraDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    public function __construct(private NoteService $notes) {}

    public function show(Request $request, Note $note): JsonResponse
    {
        $this->authorize('view', $note);
        $note->load(['tags', 'author', 'workspace', 'shares.user', 'shareLinks']);
        $canShare = $request->user()->can('share', $note);

        return response()->json([
            'note' => NoteResource::makeArray($note, includeDocument: true),
            'canEdit' => $request->user()->can('update', $note),
            'canShare' => $canShare,
            'shares' => $canShare ? $note->shares->map(fn ($share) => [
                'id' => $share->id,
                'user' => ['id' => $share->user->id, 'name' => $share->user->name, 'email' => $share->user->email],
                'permission' => $share->permission->value,
            ])->values() : [],
            'links' => $canShare ? $note->shareLinks
                ->filter(fn ($link) => $link->isUsable())
                ->map(fn ($link) => [
                    'token' => $link->token,
                    'url' => route('shares.public', $link->token),
                    'expires_at' => $link->expires_at?->toIso8601String(),
                ])
                ->values() : [],
        ]);
    }

    public function store(NoteStoreRequest $request, Workspace $workspace): JsonResponse
    {
        $data = $request->validated();
        if (($data['template'] ?? null) === 'todo') {
            $data['document'] = $this->todoTemplate();
            $data['title'] ??= 'À faire';
            $data['html_preview'] = '<ul><li>Tâche</li></ul>';
        } elseif (($data['template'] ?? null) === 'meeting') {
            $data['title'] ??= 'Réunion';
            $data['document'] = OraDocument::empty();
        }

        $note = $this->notes->create($workspace, $request->user(), $data);

        return response()->json(['note' => NoteResource::makeArray($note, includeDocument: true)], 201);
    }

    public function update(NoteUpdateRequest $request, Note $note): JsonResponse
    {
        $note = $this->notes->update($note, $request->user(), $request->validated());

        return response()->json(['note' => NoteResource::makeArray($note, includeDocument: isset($request->validated()['document']))]);
    }

    public function destroy(Request $request, Note $note): JsonResponse
    {
        $this->authorize('delete', $note);
        $this->notes->trash($note, $request->user());

        return response()->json(['ok' => true]);
    }

    public function duplicate(Request $request, Note $note): JsonResponse
    {
        $this->authorize('update', $note);
        $note->loadMissing('workspace');
        abort_unless($note->workspace, 404);
        $this->authorize('create', [Note::class, $note->workspace]);
        $copy = $this->notes->duplicate($note, $request->user());

        return response()->json(['note' => NoteResource::makeArray($copy, includeDocument: true)], 201);
    }

    public function positions(NotePositionsRequest $request, Workspace $workspace): JsonResponse
    {
        $this->notes->updatePositions($workspace, $request->validated('positions'));

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{version: int, type: string, content: list<array<string, mixed>>}
     */
    private function todoTemplate(): array
    {
        return [
            'version' => 1,
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'listItem',
                    'attrs' => ['level' => 0],
                    'content' => [['type' => 'text', 'text' => 'Première tâche']],
                ],
                [
                    'type' => 'listItem',
                    'attrs' => ['level' => 0],
                    'content' => [['type' => 'text', 'text' => 'Deuxième tâche']],
                ],
            ],
        ];
    }
}
