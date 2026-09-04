<?php

namespace App\Notifications;

use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NoteSharedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Note $note,
        public User $actor,
        public SharePermission $permission,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'note_shared',
            'message' => $this->actor->name.' a partagé la note « '.$this->note->title.' » ('.$this->permission->value.')',
            'note_uuid' => $this->note->uuid,
            'workspace_uuid' => $this->note->workspace?->uuid,
            'actor' => $this->actor->name,
        ];
    }
}
