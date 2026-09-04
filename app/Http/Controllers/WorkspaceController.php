<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkspaceStoreRequest;
use App\Http\Requests\WorkspaceUpdateRequest;
use App\Http\Resources\NoteResource;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(private WorkspaceService $workspaces) {}

    public function show(Request $request, Workspace $workspace): Response
    {
        $this->authorize('view', $workspace);

        $workspace->load(['owner', 'members']);

        $notes = $workspace->notes()
            ->with(['tags', 'author'])
            ->where('is_archived', false)
            ->get();

        return Inertia::render('Desktop/Show', [
            'workspace' => WorkspaceResource::makeArray($workspace, includeMembers: true),
            'notes' => $notes->map(fn ($note) => NoteResource::makeArray($note))->values(),
            'canEdit' => $request->user()->can('update', $workspace),
            'focusNote' => $request->query('note'),
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
        $workspace = $this->workspaces->update($workspace, $request->user(), $request->validated());

        if ($request->wantsJson()) {
            return response()->json(['workspace' => WorkspaceResource::makeArray($workspace)]);
        }

        return back();
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('delete', $workspace);
        $this->workspaces->delete($workspace, $request->user());

        return redirect()->route('dashboard');
    }

    public function duplicate(Request $request, Workspace $workspace): RedirectResponse|JsonResponse
    {
        $this->authorize('view', $workspace);
        $copy = $this->workspaces->duplicate($workspace, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['workspace' => WorkspaceResource::makeArray($copy)]);
        }

        return redirect()->route('workspaces.show', $copy);
    }
}
