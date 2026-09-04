<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminWorkspaceNoteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_admin_can_lock_unlock_and_restore_a_workspace(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $owner->id, 'is_archived' => true]);

        $this->actingAs($admin)
            ->post(route('admin.workspaces.lock', $workspace))
            ->assertRedirect();
        $this->assertTrue($workspace->fresh()->is_locked);

        $this->actingAs($admin)
            ->post(route('admin.workspaces.unlock', $workspace))
            ->assertRedirect();
        $this->assertFalse($workspace->fresh()->is_locked);

        $this->actingAs($admin)
            ->post(route('admin.workspaces.restore', $workspace->uuid))
            ->assertRedirect();
        $this->assertFalse($workspace->fresh()->is_archived);
    }

    #[Test]
    public function test_admin_note_purge_is_blocked_when_workspace_is_locked(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $owner->id, 'is_locked' => true]);
        $note = Note::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);

        $this->actingAs($admin)
            ->delete(route('admin.notes.destroy', $note))
            ->assertSessionHasErrors();

        $this->assertNotNull($note->fresh());
    }
}
