<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminNoteAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_admin_can_get_another_users_workspace(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $owner->id, 'name' => 'Bureau Alice']);
        Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'title' => 'Note d’Alice',
        ]);

        $this->actingAs($admin)
            ->get(route('workspaces.show', $workspace))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desktop/Show')
                ->where('workspace.id', $workspace->uuid)
                ->where('workspace.name', 'Bureau Alice')
                ->where('canEdit', false)
                ->has('notes', 1)
                ->where('notes.0.title', 'Note d’Alice'));
    }

    #[Test]
    public function test_admin_can_get_another_users_note_via_api(): void
    {
        [$admin, $note] = $this->adminAndForeignNote();

        $this->actingAs($admin)
            ->getJson(route('api.notes.show', $note))
            ->assertOk()
            ->assertJsonPath('note.id', $note->uuid)
            ->assertJsonPath('note.title', $note->title)
            ->assertJsonPath('canEdit', false);
    }

    #[Test]
    public function test_regular_user_is_forbidden_on_another_users_note(): void
    {
        $stranger = User::factory()->create();
        [, $note] = $this->adminAndForeignNote();

        $this->actingAs($stranger)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();
    }

    #[Test]
    public function test_admin_can_view_another_users_archived_note(): void
    {
        [$admin, $note] = $this->adminAndForeignNote(['is_archived' => true]);

        $this->actingAs($admin)
            ->getJson(route('api.notes.show', $note))
            ->assertOk()
            ->assertJsonPath('note.is_archived', true);

        $this->actingAs($admin)
            ->get(route('workspaces.show', ['workspace' => $note->workspace, 'note' => $note->uuid]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desktop/Show')
                ->has('notes', 1)
                ->where('notes.0.id', $note->uuid)
                ->where('focusNote', $note->uuid));
    }

    #[Test]
    public function test_admin_can_open_another_users_archived_workspace(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create([
            'user_id' => $owner->id,
            'is_archived' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('workspaces.show', $workspace))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desktop/Show')
                ->where('workspace.id', $workspace->uuid)
                ->where('workspace.is_archived', true));
    }

    #[Test]
    public function test_admin_can_open_another_users_trashed_workspace(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
        $workspace->delete();

        $this->actingAs($admin)
            ->get(route('workspaces.show', $workspace->uuid))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desktop/Show')
                ->where('workspace.id', $workspace->uuid));
    }

    #[Test]
    public function test_inactive_admin_is_logged_out_instead_of_viewing_a_note(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => false,
        ]);
        [, $note] = $this->adminAndForeignNote();

        $this->actingAs($admin)
            ->getJson(route('api.notes.show', $note))
            ->assertRedirect(route('login'));
    }

    /**
     * @param  array<string, mixed>  $noteAttrs
     * @return array{0: User, 1: Note}
     */
    private function adminAndForeignNote(array $noteAttrs = []): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
        $note = Note::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ], $noteAttrs));

        return [$admin, $note];
    }
}
