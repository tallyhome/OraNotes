<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private ActivityLogger $logger,
        private WorkspaceService $workspaces,
    ) {}

    public function index(Request $request): Response
    {
        $query = User::query()->withTrashed();

        if ($request->string('q')->isNotEmpty()) {
            $term = '%'.$request->string('q').'%';
            $query->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
        }

        $query->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')));
        $query->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')));

        $users = $query->latest('id')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Users', [
            'users' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'is_active' => $user->is_active,
                'deleted_at' => $user->deleted_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
            'filters' => $request->only(['q', 'role', 'active']),
        ]);
    }

    public function show(User $user): Response
    {
        $user->loadCount(['workspaces', 'notes']);

        return Inertia::render('Admin/UserShow', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
                'workspaces_count' => $user->workspaces_count,
                'notes_count' => $user->notes_count,
            ],
            'workspaces' => $user->workspaces()->withTrashed()->latest('id')->limit(20)->get(['id', 'uuid', 'name', 'is_archived', 'is_locked', 'deleted_at']),
            'notes' => $user->notes()->withTrashed()->latest('id')->limit(20)->get(['id', 'uuid', 'title', 'is_archived', 'deleted_at']),
            'activity' => $user->activityLogs()->latest('id')->limit(30)->get(['id', 'action', 'ip_address', 'created_at', 'properties']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $this->workspaces->createDefaultFor($user);
        $this->logger->log(ActivityAction::UserCreated, $request->user(), $user, [
            'email' => $user->email,
            'role' => $user->role?->value,
        ]);

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (($data['role'] ?? null) === UserRole::User->value && $user->isAdmin()) {
            $admins = User::query()->where('role', UserRole::Admin)->count();
            abort_if($admins <= 1, 422, 'Impossible de retirer le dernier administrateur.');
        }

        $wasActive = $user->is_active;
        $oldRole = $user->role?->value;
        $user->forceFill($data);
        $user->save();

        if (array_key_exists('is_active', $data) && $wasActive !== $user->is_active) {
            $this->logger->log($user->is_active ? ActivityAction::UserEnabled : ActivityAction::UserDisabled, $request->user(), $user);
            if (! $user->is_active) {
                $this->flushSessions($user);
            }
        }

        if (isset($data['role']) && $oldRole !== $user->role?->value) {
            $this->logger->log(ActivityAction::UserRoleChanged, $request->user(), $user, [
                'from' => $oldRole,
                'to' => $user->role?->value,
            ]);
        }

        $this->logger->log(ActivityAction::UserUpdated, $request->user(), $user);

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas supprimer votre propre compte ici.');
        abort_if($user->isAdmin() && User::query()->where('role', UserRole::Admin)->count() <= 1, 422, 'Impossible de supprimer le dernier administrateur.');

        $this->flushSessions($user);
        $user->update(['is_active' => false]);
        $user->delete();
        $this->logger->log(ActivityAction::UserDeleted, $request->user(), $user, [
            'email' => $user->email,
            'soft' => true,
        ]);

        return back();
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $model = User::onlyTrashed()->whereKey($id)->firstOrFail();
        $model->restore();
        $model->update(['is_active' => true]);
        $this->logger->log(ActivityAction::UserEnabled, $request->user(), $model, ['restored' => true]);

        return back();
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        $user->forceFill(['password' => $data['password']])->save();
        $this->flushSessions($user);
        $this->logger->log(ActivityAction::UserUpdated, $request->user(), $user, ['password_reset' => true]);

        return back();
    }

    public function logoutSessions(Request $request, User $user): RedirectResponse
    {
        $this->flushSessions($user);
        $this->logger->log(ActivityAction::UserUpdated, $request->user(), $user, ['sessions_revoked' => true]);

        return back();
    }

    private function flushSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
    }
}
