<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\ShareLink;
use App\Models\User;
use App\Notifications\AccessRevokedNotification;
use App\Notifications\NoteSharedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SharingService
{
    public const MAX_ACTIVE_LINKS = 10;

    public const MAX_NOTE_SHARES = 50;

    public function __construct(private ActivityLogger $logger) {}

    public function shareNoteWithUser(Note $note, User $actor, User $target, SharePermission $permission): void
    {
        $already = $note->sharedUsers()->where('users.id', $target->id)->exists();
        if (! $already && $note->sharedUsers()->count() >= self::MAX_NOTE_SHARES) {
            throw ValidationException::withMessages([
                'email' => 'Trop de partages utilisateurs sur cette note ('.self::MAX_NOTE_SHARES.' max).',
            ]);
        }

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
        $target->notify(new AccessRevokedNotification($note, $actor));
    }

    public function createLink(Model $shareable, User $actor, SharePermission $permission = SharePermission::Read, ?\DateTimeInterface $expiresAt = null): ShareLink
    {
        $active = $shareable->shareLinks()
            ->where('is_revoked', false)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($active >= self::MAX_ACTIVE_LINKS) {
            throw ValidationException::withMessages([
                'link' => 'Trop de liens de partage actifs ('.self::MAX_ACTIVE_LINKS.' max).',
            ]);
        }

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
