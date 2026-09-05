<?php

namespace App\Services\Authorization;

use App\Enums\SharePermission;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\ShareLink;
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

    public function isActiveAdmin(User $user): bool
    {
        return $user->isAdmin() && $user->is_active;
    }

    public function canAccessWorkspacePage(User $user, Workspace $workspace): bool
    {
        if ($this->isActiveAdmin($user)) {
            return true;
        }

        if ($workspace->trashed()) {
            return $workspace->isOwnedBy($user);
        }

        if ($this->canViewWorkspace($user, $workspace)) {
            return true;
        }

        if ($workspace->is_archived) {
            return false;
        }

        return Note::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_archived', false)
            ->whereHas('shares', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    public function canViewWorkspace(User $user, Workspace $workspace): bool
    {
        if ($this->isActiveAdmin($user)) {
            return true;
        }

        if ($workspace->trashed()) {
            return $workspace->isOwnedBy($user);
        }

        if ($workspace->is_archived) {
            return $workspace->isOwnedBy($user);
        }

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
        if ($this->isActiveAdmin($user)) {
            return true;
        }

        if ($note->trashed()) {
            return false;
        }

        $note->loadMissing('workspace');

        if ($note->workspace?->trashed() || $note->workspace?->is_archived) {
            return $this->canManageHiddenNote($user, $note);
        }

        if ($note->is_archived) {
            return $this->canManageHiddenNote($user, $note);
        }

        return $this->notePermission($user, $note) !== null;
    }

    public function canEditNote(User $user, Note $note): bool
    {
        if ($note->trashed()) {
            return false;
        }

        $note->loadMissing('workspace');

        if ($note->workspace?->trashed()) {
            return false;
        }

        if ($note->is_archived || $note->workspace?->is_archived) {
            return $this->canManageHiddenNote($user, $note);
        }

        return $this->notePermission($user, $note) === SharePermission::Edit;
    }

    /**
     * Archived / hidden notes: author, workspace owner, workspace editor or active admin.
     * Knowing a UUID or holding a former share is not enough.
     */
    public function canManageHiddenNote(User $user, Note $note): bool
    {
        if ($this->isActiveAdmin($user)) {
            return true;
        }

        $note->loadMissing('workspace');

        if ((int) $note->user_id === (int) $user->id) {
            return true;
        }

        if ($note->workspace?->isOwnedBy($user)) {
            return true;
        }

        return $note->workspace !== null && $this->canEditWorkspace($user, $note->workspace);
    }

    /**
     * @param  list<string>  $shareTokens
     */
    public function canDownloadAttachment(?User $user, Attachment $attachment, array $shareTokens = []): bool
    {
        if ($attachment->note_id === null || ! Attachment::pathIsSafe($attachment->path)) {
            return false;
        }

        $note = $attachment->note;
        if ($note === null) {
            return false;
        }

        if ($user && $this->canViewNote($user, $note)) {
            return true;
        }

        return $this->shareTokensGrantNote($shareTokens, $note);
    }

    /**
     * @param  list<mixed>  $tokens
     */
    public function shareTokensGrantNote(array $tokens, Note $note): bool
    {
        $tokens = array_values(array_unique(array_filter(
            $tokens,
            fn ($token) => is_string($token) && $token !== '',
        )));

        if ($tokens === []) {
            return false;
        }

        $links = ShareLink::query()->whereIn('token', $tokens)->get();

        $note->loadMissing('workspace');

        foreach ($links as $link) {
            if (! $link->isUsable()) {
                continue;
            }

            $shareable = $link->shareable;
            if ($note->trashed() || $note->is_archived || $note->workspace?->trashed() || $note->workspace?->is_archived) {
                continue;
            }

            if ($shareable instanceof Note && (int) $shareable->id === (int) $note->id) {
                return true;
            }

            if (
                $shareable instanceof Workspace
                && (int) $shareable->id === (int) $note->workspace_id
            ) {
                return true;
            }
        }

        return false;
    }
}
