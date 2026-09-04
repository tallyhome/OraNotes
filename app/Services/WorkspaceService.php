<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\SharePermission;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\WorkspaceSharedNotification;
use Illuminate\Support\Facades\DB;

class WorkspaceService
{
    public function __construct(
        private ActivityLogger $logger,
        private NoteService $notes,
    ) {}

    public function createDefaultFor(User $user): Workspace
    {
        return $this->create($user, [
            'name' => 'Bureau',
            'icon' => '🖥️',
            'color' => 'yellow',
            'is_default' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Workspace
    {
        return DB::transaction(function () use ($user, $data) {
            if (! empty($data['is_default'])) {
                Workspace::query()->where('user_id', $user->id)->update(['is_default' => false]);
            }

            $workspace = Workspace::query()->create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? '🗂️',
                'color' => $data['color'] ?? 'yellow',
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_template' => (bool) ($data['is_template'] ?? false),
                'canvas_settings' => $data['canvas_settings'] ?? ['zoom' => 1, 'x' => 0, 'y' => 0, 'snap' => false],
            ]);

            $this->logger->log(ActivityAction::WorkspaceCreated, $user, $workspace, [
                'name' => $workspace->name,
            ]);

            return $workspace;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Workspace $workspace, User $user, array $data): Workspace
    {
        if (! $workspace->isOwnedBy($user)) {
            unset($data['is_default'], $data['is_template'], $data['is_archived']);
        }

        if (! empty($data['is_default'])) {
            Workspace::query()->where('user_id', $workspace->user_id)->update(['is_default' => false]);
        }

        $workspace->fill($data);
        $workspace->save();

        $this->logger->log(ActivityAction::WorkspaceUpdated, $user, $workspace);

        return $workspace->refresh();
    }

    public function archive(Workspace $workspace, User $user): void
    {
        $workspace->update(['is_archived' => true, 'is_default' => false]);
        $this->logger->log(ActivityAction::WorkspaceUpdated, $user, $workspace, ['archived' => true]);
    }

    public function delete(Workspace $workspace, User $user): void
    {
        $workspace->delete();
        $this->logger->log(ActivityAction::WorkspaceDeleted, $user, $workspace, [
            'name' => $workspace->name,
        ]);
    }

    public function duplicate(Workspace $workspace, User $user): Workspace
    {
        return DB::transaction(function () use ($workspace, $user) {
            $copy = $this->create($user, [
                'name' => $workspace->name.' (copie)',
                'description' => $workspace->description,
                'icon' => $workspace->icon,
                'color' => $workspace->color,
                'canvas_settings' => $workspace->canvas_settings,
            ]);

            foreach ($workspace->notes()->with('tags')->get() as $note) {
                $this->notes->duplicate($note, $user, $copy, 24, 24);
            }

            return $copy;
        });
    }

    public function addMember(Workspace $workspace, User $actor, User $member, SharePermission $permission): void
    {
        $workspace->members()->syncWithoutDetaching([
            $member->id => ['permission' => $permission->value],
        ]);

        $this->logger->log(ActivityAction::ShareAdded, $actor, $workspace, [
            'member_id' => $member->id,
            'permission' => $permission->value,
        ]);

        $member->notify(new WorkspaceSharedNotification($workspace, $actor, $permission));
    }

    public function removeMember(Workspace $workspace, User $actor, User $member): void
    {
        $workspace->members()->detach($member->id);
        $this->logger->log(ActivityAction::ShareRemoved, $actor, $workspace, [
            'member_id' => $member->id,
        ]);
    }
}
