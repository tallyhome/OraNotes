<?php

namespace App\Notifications;

use App\Models\Note;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CollaboratorJoinedNotification extends Notification
{
    use Queueable;

    public function __construct(public Note $note, public User $actor) {}

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
            'type' => 'collaborator_joined',
            'message' => $this->actor->name.' a rejoint « '.$this->note->title.' »',
            'note_uuid' => $this->note->uuid,
            'workspace_uuid' => $this->note->workspace?->uuid,
        ];
    }
}
