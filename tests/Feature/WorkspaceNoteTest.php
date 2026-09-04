<?php

namespace Tests\Feature;

use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkspaceNoteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_create_workspace_and_note(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('workspaces.store'), ['name' => 'Labo', 'icon' => '🧪'])
            ->assertRedirect();

        $workspace = Workspace::query()->where('name', 'Labo')->first();
        $this->assertNotNull($workspace);
        $this->assertNotEquals($workspace->id, $workspace->uuid);

        $this->actingAs($user)
            ->postJson(route('api.notes.store', $workspace), [
                'title' => 'Post-it',
                'x' => 40,
                'y' => 50,
            ])
            ->assertCreated()
            ->assertJsonPath('note.title', 'Post-it');

        $this->assertDatabaseHas('notes', ['title' => 'Post-it', 'workspace_id' => $workspace->id]);
    }

    #[Test]
    public function positions_update_is_batched(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'x' => 10, 'y' => 10]);

        $this->actingAs($user)
            ->patchJson(route('api.notes.positions', $workspace), [
                'positions' => [
                    ['id' => $note->uuid, 'x' => 200, 'y' => 180, 'width' => 280, 'height' => 240],
                ],
            ])
            ->assertOk();

        $note->refresh();
        $this->assertSame(200.0, $note->x);
        $this->assertSame(180.0, $note->y);
    }

    #[Test]
    public function soft_delete_then_restore(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        $this->actingAs($user)->deleteJson(route('api.notes.destroy', $note))->assertOk();
        $this->assertSoftDeleted($note);

        $this->actingAs($user)->postJson(route('api.notes.restore', $note->uuid))->assertOk();
        $this->assertDatabaseHas('notes', ['id' => $note->id, 'deleted_at' => null]);
    }

    #[Test]
    public function search_finds_own_note_only(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceWs = Workspace::factory()->create(['user_id' => $alice->id]);
        $bobWs = Workspace::factory()->create(['user_id' => $bob->id]);
        Note::factory()->create([
            'workspace_id' => $aliceWs->id,
            'user_id' => $alice->id,
            'title' => 'Secret papillon',
            'text_content' => 'Secret papillon',
        ]);
        Note::factory()->create([
            'workspace_id' => $bobWs->id,
            'user_id' => $bob->id,
            'title' => 'Secret papillon de Bob',
            'text_content' => 'Secret papillon de Bob',
        ]);

        $this->actingAs($alice)
            ->getJson(route('api.search', ['q' => 'papillon']))
            ->assertOk()
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Secret papillon');
    }

    #[Test]
    public function registration_creates_default_workspace(): void
    {
        $this->post('/register', [
            'name' => 'Nora',
            'email' => 'nora@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $this->assertDatabaseHas('workspaces', ['name' => 'Bureau']);
    }

    #[Test]
    public function negative_z_index_is_rejected(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson(route('api.notes.positions', $workspace), [
                'positions' => [
                    ['id' => $note->uuid, 'x' => 10, 'y' => 10, 'z_index' => -1],
                ],
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function search_finds_shared_note_for_recipient(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
            'title' => 'Papillon partagé',
            'text_content' => 'Papillon partagé',
        ]);
        app(SharingService::class)->shareNoteWithUser(
            $note,
            $alice,
            $bob,
            SharePermission::Read,
        );

        $this->actingAs($bob)
            ->getJson(route('api.search', ['q' => 'Papillon']))
            ->assertOk()
            ->assertJsonCount(1, 'notes')
            ->assertJsonPath('notes.0.title', 'Papillon partagé');
    }
}
