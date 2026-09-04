<?php

namespace App\Http\Middleware;

use App\Http\Resources\WorkspaceResource;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user?->toInertia(),
            ],
            'workspaces' => $user
                ? Workspace::query()
                    ->visibleTo($user)
                    ->where('is_archived', false)
                    ->orderByDesc('is_default')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Workspace $workspace) => WorkspaceResource::makeArray($workspace))
                    ->values()
                : [],
            'unreadNotifications' => $user ? $user->unreadNotifications()->count() : 0,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
