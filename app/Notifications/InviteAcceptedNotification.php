<?php

namespace App\Notifications;

use App\Models\Note;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InviteAcceptedNotification extends Notification
{
    use Queueable;

    public function __construct(public Note $note, public User $invitee) {}

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
            'type' => 'invite_accepted',
            'message' => $this->invitee->name.' a ouvert la note partagée « '.$this->note->title.' »',
            'note_uuid' => $this->note->uuid,
            'workspace_uuid' => $this->note->workspace?->uuid,
        ];
    }
}
