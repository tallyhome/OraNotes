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
        if ($workspace->isOwnedBy($user)) {
            return SharePermission::Edit;
        }

        return $workspace->memberPermission($user);
    }

    public function canAccessWorkspacePage(User $user, Workspace $workspace): bool
    {
        if ($this->canViewWorkspace($user, $workspace)) {
            return true;
        }

        return Note::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('shares', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
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
        $note->loadMissing('workspace');

        if ($note->workspace && $this->canEditWorkspace($user, $note->workspace)) {
            return SharePermission::Edit;
        }

        $direct = $note->sharePermissionFor($user);
        if ($direct !== null) {
            return $direct;
        }

        if ($note->workspace && $this->canViewWorkspace($user, $note->workspace)) {
            return SharePermission::Read;
        }

        return null;
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
