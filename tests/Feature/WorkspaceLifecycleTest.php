<?php

namespace Tests\Feature;

use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_owner_can_archive_and_restore_a_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patch(route('workspaces.update', $workspace), ['is_archived' => true])
            ->assertRedirect(route('dashboard'));

        $this->assertTrue($workspace->fresh()->is_archived);

        $this->actingAs($user)
            ->patch(route('workspaces.update', $workspace), ['is_archived' => false])
            ->assertRedirect();

        $this->assertFalse($workspace->fresh()->is_archived);
    }

    #[Test]
    public function test_owner_can_soft_delete_then_restore_a_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('workspaces.destroy', $workspace))
            ->assertRedirect(route('dashboard'));

        $this->assertSoftDeleted($workspace);

        $this->actingAs($user)
            ->postJson(route('workspaces.restore', $workspace->uuid))
            ->assertOk();

        $this->assertDatabaseHas('workspaces', [
            'id' => $workspace->id,
            'deleted_at' => null,
        ]);
    }

    #[Test]
    public function test_force_delete_requires_name_confirmation_when_notes_exist(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id, 'name' => 'Labo']);
        Note::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('workspaces.force', $workspace->uuid), ['confirm_name' => 'mauvais'])
            ->assertUnprocessable();

        $this->assertNotNull($workspace->fresh());

        $this->actingAs($user)
            ->deleteJson(route('workspaces.force', $workspace->uuid), ['confirm_name' => 'Labo'])
            ->assertOk();

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
    }

    #[Test]
    public function test_locked_workspace_blocks_delete_and_note_purge(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->postJson(route('workspaces.lock', $workspace))
            ->assertOk()
            ->assertJsonPath('workspace.is_locked', true);

        $this->actingAs($user)
            ->delete(route('workspaces.destroy', $workspace))
            ->assertForbidden();

        $note->delete();

        $this->actingAs($user)
            ->deleteJson(route('api.notes.force', $note->uuid))
            ->assertForbidden();

        $this->assertSoftDeleted($note);
        $this->assertNotSoftDeleted($workspace);
    }

    #[Test]
    public function test_member_cannot_lock_unlock_or_force_delete(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
        $workspace->members()->syncWithoutDetaching([
            $member->id => ['permission' => SharePermission::Edit->value],
        ]);

        $this->actingAs($member)
            ->postJson(route('workspaces.lock', $workspace))
            ->assertForbidden();

        $this->actingAs($owner)->postJson(route('workspaces.lock', $workspace))->assertOk();

        $this->actingAs($member)
            ->postJson(route('workspaces.unlock', $workspace))
            ->assertForbidden();

        $this->actingAs($member)
            ->deleteJson(route('workspaces.force', $workspace->uuid), ['confirm_name' => $workspace->name])
            ->assertForbidden();

        $this->assertTrue($workspace->fresh()->is_locked);
    }

    #[Test]
    public function test_member_cannot_unlock_via_generic_update(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $owner->id,
            'is_locked' => true,
        ]);
        $workspace->members()->syncWithoutDetaching([
            $member->id => ['permission' => SharePermission::Edit->value],
        ]);

        $this->actingAs($member)
            ->patchJson(route('workspaces.update', $workspace), ['is_locked' => false])
            ->assertOk();

        $this->assertTrue($workspace->fresh()->is_locked);
    }

    #[Test]
    public function test_admin_can_unlock_a_locked_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $workspace = Workspace::factory()->create(['user_id' => $owner->id, 'is_locked' => true]);

        $this->actingAs($admin)
            ->postJson(route('workspaces.unlock', $workspace))
            ->assertOk()
            ->assertJsonPath('workspace.is_locked', false);
    }
}
