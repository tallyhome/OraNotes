<?php

namespace App\Http\Resources;

use App\Models\Workspace;

class WorkspaceResource
{
    /**
     * @return array<string, mixed>
     */
    public static function makeArray(Workspace $workspace, bool $includeMembers = false): array
    {
        $data = [
            'id' => $workspace->uuid,
            'name' => $workspace->name,
            'description' => $workspace->description,
            'icon' => $workspace->icon,
            'color' => $workspace->color,
            'is_default' => $workspace->is_default,
            'is_archived' => $workspace->is_archived,
            'is_template' => $workspace->is_template,
            'canvas_settings' => $workspace->canvas_settings ?? ['zoom' => 1, 'x' => 0, 'y' => 0, 'snap' => false],
            'notes_count' => $workspace->notes_count ?? $workspace->notes()->count(),
            'updated_at' => $workspace->updated_at?->toIso8601String(),
        ];

        if ($includeMembers) {
            $data['owner'] = [
                'id' => $workspace->owner?->id,
                'name' => $workspace->owner?->name,
                'email' => $workspace->owner?->email,
            ];
            $data['members'] = $workspace->members->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'permission' => $user->pivot->permission,
            ])->values();
        }

        return $data;
    }
}
