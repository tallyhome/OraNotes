<?php

namespace App\Notifications;

use App\Enums\SharePermission;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkspaceSharedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Workspace $workspace,
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
            'type' => 'workspace_shared',
            'message' => $this->actor->name.' vous a ajouté au bureau « '.$this->workspace->name.' » ('.$this->permission->value.')',
            'workspace_uuid' => $this->workspace->uuid,
            'actor' => $this->actor->name,
        ];
    }
}
