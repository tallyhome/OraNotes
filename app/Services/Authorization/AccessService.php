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

        foreach ($links as $link) {
            if (! $link->isUsable()) {
                continue;
            }

            $shareable = $link->shareable;
            if ($shareable instanceof Note && (int) $shareable->id === (int) $note->id) {
                return true;
            }

            if (
                $shareable instanceof Workspace
                && (int) $shareable->id === (int) $note->workspace_id
                && ! $note->is_archived
            ) {
                return true;
            }
        }

        return false;
    }
}
