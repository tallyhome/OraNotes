<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Authorization\AccessService;

class NotePolicy
{
    public function __construct(private AccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Note $note): bool
    {
        return $this->access->canViewNote($user, $note);
    }

    public function create(User $user, ?Workspace $workspace = null): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($workspace) {
            return $this->access->canEditWorkspace($user, $workspace);
        }

        return true;
    }

    public function update(User $user, Note $note): bool
    {
        return $this->access->canEditNote($user, $note);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->ownsOrManagesWorkspace($user, $note);
    }

    public function restore(User $user, Note $note): bool
    {
        return $this->ownsOrManagesWorkspace($user, $note);
    }

    public function forceDelete(User $user, Note $note): bool
    {
        $note->loadMissing('workspace');

        return $note->workspace?->isOwnedBy($user)
            || (int) $note->user_id === (int) $user->id
            || $user->isAdmin();
    }

    public function share(User $user, Note $note): bool
    {
        $note->loadMissing('workspace');

        return $note->workspace?->isOwnedBy($user)
            || (int) $note->user_id === (int) $user->id
            || $user->isAdmin();
    }

    private function ownsOrManagesWorkspace(User $user, Note $note): bool
    {
        $note->loadMissing('workspace');

        if ((int) $note->user_id === (int) $user->id) {
            return true;
        }

        return $note->workspace
            ? $this->access->canEditWorkspace($user, $note->workspace)
            : false;
    }
}
