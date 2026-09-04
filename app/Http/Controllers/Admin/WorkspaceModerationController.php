<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceModerationController extends Controller
{
    public function __construct(private WorkspaceService $workspaces) {}

    public function index(Request $request): Response
    {
        $workspaces = Workspace::query()
            ->withTrashed()
            ->with('owner')
            ->withCount('notes')
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Workspaces', [
            'workspaces' => $workspaces->through(fn (Workspace $w) => WorkspaceResource::makeArray($w) + [
                'owner_email' => $w->owner?->email,
                'is_trashed' => $w->trashed(),
            ]),
            'filters' => $request->only(['q']),
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'is_archived' => ['sometimes', 'boolean'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        if (isset($data['owner_id'])) {
            $owner = User::query()->findOrFail($data['owner_id']);
            $workspace->user_id = $owner->id;
            $workspace->save();
            unset($data['owner_id']);
        }

        if ($data !== []) {
            $this->workspaces->update($workspace, $request->user(), $data);
        }

        return back();
    }

    public function lock(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->workspaces->lock($workspace, $request->user());

        return back();
    }

    public function unlock(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->workspaces->unlock($workspace, $request->user());

        return back();
    }

    public function restore(Request $request, string $workspace): RedirectResponse
    {
        $model = Workspace::withTrashed()->where('uuid', $workspace)->firstOrFail();
        if ($model->trashed()) {
            $this->workspaces->restore($model, $request->user());
        } else {
            $this->workspaces->restoreArchived($model, $request->user());
        }

        return back();
    }

    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->workspaces->forceDelete($workspace, $request->user(), $request->input('confirm_name', $workspace->name));

        return back();
    }
}
