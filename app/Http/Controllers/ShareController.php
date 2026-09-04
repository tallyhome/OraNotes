<?php

namespace App\Http\Controllers;

use App\Enums\SharePermission;
use App\Http\Requests\ShareUserRequest;
use App\Http\Resources\NoteResource;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SharingService;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShareController extends Controller
{
    public function __construct(
        private SharingService $sharing,
        private WorkspaceService $workspaces,
    ) {}

    public function index(Request $request): Response
    {
        $notes = Note::query()
            ->with(['workspace', 'tags', 'author'])
            ->whereHas('shares', fn ($q) => $q->where('user_id', $request->user()->id))
            ->latest('updated_at')
            ->get();

        return Inertia::render('Shared', [
            'notes' => $notes->map(fn (Note $n) => NoteResource::makeArray($n)),
        ]);
    }

    public function storeNoteShare(ShareUserRequest $request, Note $note): JsonResponse
    {
        $this->authorize('share', $note);
        $target = User::query()->where('email', $request->validated('email'))->firstOrFail();
        abort_if($target->is($request->user()), 422, 'Impossible de se partager une note à soi-même.');

        $this->sharing->shareNoteWithUser(
            $note,
            $request->user(),
            $target,
            SharePermission::from($request->validated('permission')),
        );

        return response()->json(['ok' => true]);
    }

    public function destroyNoteShare(Request $request, Note $note, User $user): JsonResponse
    {
        $this->authorize('share', $note);
        $this->sharing->revokeNoteShare($note, $request->user(), $user);

        return response()->json(['ok' => true]);
    }

    public function storeWorkspaceMember(ShareUserRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);
        $target = User::query()->where('email', $request->validated('email'))->firstOrFail();
        $this->workspaces->addMember(
            $workspace,
            $request->user(),
            $target,
            SharePermission::from($request->validated('permission')),
        );

        return response()->json(['ok' => true]);
    }

    public function destroyWorkspaceMember(Request $request, Workspace $workspace, User $user): JsonResponse
    {
        $this->authorize('manageMembers', $workspace);
        $this->workspaces->removeMember($workspace, $request->user(), $user);

        return response()->json(['ok' => true]);
    }
}
