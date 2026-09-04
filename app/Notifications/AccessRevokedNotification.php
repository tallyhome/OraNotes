<?php

namespace App\Notifications;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AccessRevokedNotification extends Notification
{
    use Queueable;

    public function __construct(public Note|Workspace $target, public User $actor) {}

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
        if ($this->target instanceof Workspace) {
            return [
                'type' => 'access_revoked',
                'message' => $this->actor->name.' a retiré votre accès au bureau « '.$this->target->name.' »',
                'workspace_uuid' => $this->target->uuid,
            ];
        }

        return [
            'type' => 'access_revoked',
            'message' => $this->actor->name.' a retiré votre accès à « '.$this->target->title.' »',
            'note_uuid' => $this->target->uuid,
        ];
    }
}
