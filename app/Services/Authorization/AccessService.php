<?php

namespace App\Services\Authorization;

use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;

class AccessService
{
    public function workspacePermission(User $user, Workspace $workspace): ?SharePermission
    {
        if ($user->isAdmin()) {
            return SharePermission::Edit;
        }

        if ($workspace->isOwnedBy($user)) {
            return SharePermission::Edit;
        }

        return $workspace->memberPermission($user);
    }

    public function canViewWorkspace(User $user, Workspace $workspace): bool
    {
        return $this->workspacePermission($user, $workspace) !== null;
    }

    public function canEditWorkspace(User $user, Workspace $workspace): bool
    {
        $permission = $this->workspacePermission($user, $workspace);

        return $permission === SharePermission::Edit;
    }

    public function notePermission(User $user, Note $note): ?SharePermission
    {
        if ($user->isAdmin()) {
            return SharePermission::Edit;
        }

        $note->loadMissing('workspace');

        if ($note->workspace && $this->canEditWorkspace($user, $note->workspace)) {
            return SharePermission::Edit;
        }

        if ($note->workspace && $this->canViewWorkspace($user, $note->workspace)) {
            $share = $note->sharePermissionFor($user);

            return $share ?? SharePermission::Read;
        }

        return $note->sharePermissionFor($user);
    }

    public function canViewNote(User $user, Note $note): bool
    {
        return $this->notePermission($user, $note) !== null;
    }

    public function canEditNote(User $user, Note $note): bool
    {
        return $this->notePermission($user, $note) === SharePermission::Edit;
    }
}
