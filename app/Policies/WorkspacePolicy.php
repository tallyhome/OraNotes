<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Services\Authorization\AccessService;

class WorkspacePolicy
{
    public function __construct(private AccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Workspace $workspace): bool
    {
        return $this->access->canAccessWorkspacePage($user, $workspace);
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, Workspace $workspace): bool
    {
        return $this->access->canEditWorkspace($user, $workspace);
    }

    public function delete(User $user, Workspace $workspace): bool
    {
        if ($workspace->is_locked) {
            return false;
        }

        return $workspace->isOwnedBy($user) || $user->isAdmin();
    }

    public function restore(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->isAdmin();
    }

    public function forceDelete(User $user, Workspace $workspace): bool
    {
        if ($workspace->is_locked) {
            return false;
        }

        return $workspace->isOwnedBy($user) || $user->isAdmin();
    }

    public function lock(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->isAdmin();
    }

    public function unlock(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->isAdmin();
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->isAdmin();
    }
}
