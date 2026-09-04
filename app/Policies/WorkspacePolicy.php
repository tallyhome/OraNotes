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
        return $this->access->canViewWorkspace($user, $workspace);
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
        return $workspace->isOwnedBy($user) || $user->isAdmin();
    }

    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->isOwnedBy($user) || $user->isAdmin();
    }
}
