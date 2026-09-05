<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceStoreRequest;
use App\Http\Requests\WorkspaceUpdateRequest;
use App\Http\Resources\NoteResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Note;
use App\Models\Workspace;
use App\Services\Authorization\AccessService;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(
        private WorkspaceService $workspaces,
        private AccessService $access,
    ) {}

    public function show(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $workspace->load(['owner', 'members', 'shareLinks']);

        $user = $request->user();
        $canManage = $user?->can('manageMembers', $workspace) ?? false;
        $isAdminViewer = $user !== null && $this->access->isActiveAdmin($user);

        $notesQuery = $workspace->notes()->with(['tags', 'author']);
        if (! $isAdminViewer) {
            $notesQuery->where('is_archived', false);
        }

        $notes = $notesQuery->get()
            ->filter(fn ($note) => $user?->can('view', $note) ?? false)
            ->values();

        $focus = $request->query('note');
        if ($isAdminViewer && is_string($focus) && $focus !== '') {
            $alreadyPresent = $notes->contains(fn (Note $note): bool => $note->uuid === $focus);
            if (! $alreadyPresent) {
                $hidden = $workspace->notes()
                    ->withTrashed()
                    ->with(['tags', 'author'])
                    ->where('uuid', $focus)
                    ->first();
                if ($hidden && $user->can('view', $hidden)) {
                    $notes->push($hidden);
                }
            }
        }

        return Inertia::render('Desktop/Show', [
            'workspace' => WorkspaceResource::makeArray(
                $workspace,
                includeMembers: $user ? ($workspace->isOwnedBy($user) || $workspace->memberPermission($user) !== null) : false,
            ),
            'notes' => $notes->map(fn ($note) => NoteResource::makeArray($note))->values(),
            'canEdit' => $user?->can('update', $workspace) ?? false,
            'canManage' => $canManage,
            'isOwner' => $user ? $workspace->isOwnedBy($user) : false,
            'focusNote' => $request->query('note'),
            'shareLinks' => $canManage
                ? $workspace->shareLinks
                    ->filter(fn ($link) => $link->isUsable())
                    ->map(fn ($link) => [
                        'token' => $link->token,
                        'url' => route('shares.public', $link->token),
                        'expires_at' => $link->expires_at?->toIso8601String(),
                    ])
                    ->values()
                : [],
        ]);
    }

    public function store(WorkspaceStoreRequest $request): RedirectResponse|JsonResponse
    {
        $workspace = $this->workspaces->create($request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['workspace' => WorkspaceResource::makeArray($workspace)]);
        }

        return redirect()->route('workspaces.show', $workspace);
    }

    public function update(WorkspaceUpdateRequest $request, Workspace $workspace): RedirectResponse|JsonResponse
    {
        $wasArchived = $workspace->is_archived;
        $workspace = $this->workspaces->update($workspace, $request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['workspace' => WorkspaceResource::makeArray($workspace)]);
        }

        if ($workspace->is_archived && ! $wasArchived) {
            return redirect()->route('dashboard');
        }

        return back();
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);
        $this->workspaces->delete($workspace, $request->user());

        return redirect()->route('dashboard');
    }

    public function restore(Request $request, string $workspace): RedirectResponse|JsonResponse
    {
        $model = Workspace::onlyTrashed()->where('uuid', $workspace)->firstOrFail();
        $this->authorize('restore', $model);
        $this->workspaces->restore($model, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'workspace' => WorkspaceResource::makeArray($model->fresh())]);
        }

        return redirect()->route('workspaces.show', $model);
    }

    public function forceDestroy(Request $request, string $workspace): RedirectResponse|JsonResponse
    {
        $model = Workspace::withTrashed()->where('uuid', $workspace)->firstOrFail();
        $this->authorize('forceDelete', $model);
        $this->workspaces->forceDelete($model, $request->user(), $request->input('confirm_name'));

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('dashboard');
    }

    public function lock(Request $request, Workspace $workspace): RedirectResponse|JsonResponse
    {
        $this->authorize('lock', $workspace);
        $workspace = $this->workspaces->lock($workspace, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['workspace' => WorkspaceResource::makeArray($workspace)]);
        }

        return back();
    }

    public function unlock(Request $request, Workspace $workspace): RedirectResponse|JsonResponse
    {
        $this->authorize('unlock', $workspace);
        $workspace = $this->workspaces->unlock($workspace, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['workspace' => WorkspaceResource::makeArray($workspace)]);
        }

        return back();
    }

    public function duplicate(Request $request, Workspace $workspace): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $workspace);
        $copy = $this->workspaces->duplicate($workspace, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['workspace' => WorkspaceResource::makeArray($copy)]);
        }

        return redirect()->route('workspaces.show', $copy);
    }
}
