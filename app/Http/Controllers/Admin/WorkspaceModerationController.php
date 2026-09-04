<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceModerationController extends Controller
{
    public function index(Request $request): Response
    {
        $workspaces = Workspace::query()
            ->with('owner')
            ->withCount('notes')
            ->when($request->string('q')->isNotEmpty(), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Workspaces', [
            'workspaces' => $workspaces->through(fn (Workspace $w) => WorkspaceResource::makeArray($w) + [
                'owner_email' => $w->owner?->email,
            ]),
            'filters' => $request->only(['q']),
        ]);
    }

    public function destroy(Workspace $workspace): RedirectResponse
    {
        $workspace->forceDelete();

        return back();
    }
}
