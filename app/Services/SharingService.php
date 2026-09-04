<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\ShareLink;
use App\Models\User;
use App\Notifications\NoteSharedNotification;
use Illuminate\Database\Eloquent\Model;

class SharingService
{
    public function __construct(private ActivityLogger $logger) {}

    public function shareNoteWithUser(Note $note, User $actor, User $target, SharePermission $permission): void
    {
        $note->sharedUsers()->syncWithoutDetaching([
            $target->id => ['permission' => $permission->value],
        ]);

        $this->logger->log(ActivityAction::ShareAdded, $actor, $note, [
            'target_id' => $target->id,
            'permission' => $permission->value,
        ]);

        $target->notify(new NoteSharedNotification($note, $actor, $permission));
    }

    public function revokeNoteShare(Note $note, User $actor, User $target): void
    {
        $note->sharedUsers()->detach($target->id);
        $this->logger->log(ActivityAction::ShareRemoved, $actor, $note, [
            'target_id' => $target->id,
        ]);
    }

    public function createLink(Model $shareable, User $actor, SharePermission $permission = SharePermission::Read, ?\DateTimeInterface $expiresAt = null): ShareLink
    {
        $link = $shareable->shareLinks()->create([
            'permission' => $permission,
            'expires_at' => $expiresAt,
            'created_by' => $actor->id,
        ]);

        $this->logger->log(ActivityAction::ShareAdded, $actor, $shareable, [
            'link' => true,
            'token_hint' => substr($link->token, 0, 6),
        ]);

        return $link;
    }

    public function revokeLink(ShareLink $link, User $actor): void
    {
        $link->update(['is_revoked' => true]);
        $this->logger->log(ActivityAction::ShareRemoved, $actor, $link->shareable, [
            'link' => true,
        ]);
    }
}
