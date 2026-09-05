<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminWorkspaceIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_admin_workspaces_index_returns_paginator_rows_the_vue_page_expects(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['name' => 'Alice Martin', 'email' => 'alice@example.test']);
        $workspace = Workspace::factory()->create([
            'user_id' => $owner->id,
            'name' => 'Idées produit',
            'icon' => '💡',
        ]);
        Note::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workspaces.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Workspaces')
                ->has('workspaces.data', 1)
                ->where('workspaces.data.0.id', $workspace->uuid)
                ->where('workspaces.data.0.name', 'Idées produit')
                ->where('workspaces.data.0.icon', '💡')
                ->where('workspaces.data.0.owner_name', 'Alice Martin')
                ->where('workspaces.data.0.owner_email', 'alice@example.test')
                ->where('workspaces.data.0.notes_count', 2)
                ->where('workspaces.data.0.is_locked', false)
                ->where('workspaces.data.0.is_archived', false)
                ->where('workspaces.data.0.is_trashed', false)
                ->has('workspaces.data.0.updated_at')
                ->has('navWorkspaces', 0)
                ->where('filters.status', ''));
    }

    #[Test]
    public function test_admin_workspaces_index_can_filter_locked_rows(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $locked = Workspace::factory()->create(['is_locked' => true, 'name' => 'Coffre']);
        Workspace::factory()->create(['name' => 'Ouvert']);

        $this->actingAs($admin)
            ->get(route('admin.workspaces.index', ['status' => 'locked']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Workspaces')
                ->has('workspaces.data', 1)
                ->where('workspaces.data.0.id', $locked->uuid)
                ->where('filters.status', 'locked'));
    }

    #[Test]
    public function test_admin_notes_index_returns_paginator_rows(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.test']);
        $workspace = Workspace::factory()->create(['user_id' => $owner->id, 'name' => 'Sprint']);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'title' => 'Stand-up',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.notes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Notes')
                ->has('notes.data', 1)
                ->where('notes.data.0.id', $note->uuid)
                ->where('notes.data.0.title', 'Stand-up')
                ->where('notes.data.0.author_email', 'bob@example.test')
                ->where('notes.data.0.workspace_name', 'Sprint')
                ->where('notes.data.0.workspace_id', $workspace->uuid)
                ->where('notes.data.0.is_trashed', false));
    }

    #[Test]
    public function test_non_admin_cannot_open_admin_workspaces(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.workspaces.index'))
            ->assertForbidden();
    }
}
