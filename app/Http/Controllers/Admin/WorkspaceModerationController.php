<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceModerationController extends Controller
{
    public function __construct(private WorkspaceService $workspaces) {}

    public function index(Request $request): Response
    {
        $status = $this->statusFilter($request);

        $workspaces = Workspace::query()
            ->withTrashed()
            ->with('owner')
            ->withCount('notes')
            ->when($request->string('q')->isNotEmpty(), fn (Builder $q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($status === 'active', fn (Builder $q) => $q->whereNull('deleted_at')->where('is_archived', false))
            ->when($status === 'archived', fn (Builder $q) => $q->whereNull('deleted_at')->where('is_archived', true))
            ->when($status === 'trashed', fn (Builder $q) => $q->onlyTrashed())
            ->when($status === 'locked', fn (Builder $q) => $q->whereNull('deleted_at')->where('is_locked', true))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Workspaces', [
            'workspaces' => $workspaces->through(fn (Workspace $workspace) => WorkspaceResource::makeArray($workspace) + [
                'owner_name' => $workspace->owner?->name,
                'owner_email' => $workspace->owner?->email,
                'is_trashed' => $workspace->trashed(),
            ]),
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $status,
            ],
        ]);
    }

    public function update(Request $request, string $workspace): RedirectResponse
    {
        $model = $this->resolveWorkspace($workspace);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'is_archived' => ['sometimes', 'boolean'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        if (isset($data['owner_id'])) {
            $owner = User::query()->findOrFail($data['owner_id']);
            $model->user_id = $owner->id;
            $model->save();
            unset($data['owner_id']);
        }

        if ($data !== []) {
            $this->workspaces->update($model, $request->user(), $data);
        }

        return back();
    }

    public function lock(Request $request, string $workspace): RedirectResponse
    {
        $this->workspaces->lock($this->resolveWorkspace($workspace), $request->user());

        return back();
    }

    public function unlock(Request $request, string $workspace): RedirectResponse
    {
        $this->workspaces->unlock($this->resolveWorkspace($workspace), $request->user());

        return back();
    }

    public function restore(Request $request, string $workspace): RedirectResponse
    {
        $model = $this->resolveWorkspace($workspace);
        if ($model->trashed()) {
            $this->workspaces->restore($model, $request->user());
        } else {
            $this->workspaces->restoreArchived($model, $request->user());
        }

        return back();
    }

    public function destroy(Request $request, string $workspace): RedirectResponse
    {
        $model = $this->resolveWorkspace($workspace);
        $this->workspaces->forceDelete($model, $request->user(), $request->input('confirm_name', $model->name));

        return back();
    }

    private function resolveWorkspace(string $workspace): Workspace
    {
        return Workspace::withTrashed()->where('uuid', $workspace)->firstOrFail();
    }

    private function statusFilter(Request $request): string
    {
        $status = $request->string('status')->toString();

        return in_array($status, ['active', 'archived', 'trashed', 'locked'], true) ? $status : '';
    }
}
