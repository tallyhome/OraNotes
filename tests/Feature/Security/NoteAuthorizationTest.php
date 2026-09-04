<?php

namespace Tests\Feature\Security;

use App\Enums\SharePermission;
use App\Enums\UserRole;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NoteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_cannot_view_another_users_note_by_uuid(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();
    }

    #[Test]
    public function user_cannot_update_another_users_note(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);

        $this->actingAs($bob)
            ->patchJson(route('api.notes.update', $note), ['title' => 'hack'])
            ->assertForbidden();

        $this->assertSame($note->title, $note->fresh()->title);
    }

    #[Test]
    public function user_cannot_open_another_users_workspace(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);

        $this->actingAs($bob)
            ->get(route('workspaces.show', $workspace))
            ->assertForbidden();
    }

    #[Test]
    public function numeric_id_is_not_a_public_route_key(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);

        $this->actingAs($bob)
            ->getJson('/api/notes/'.$note->id)
            ->assertNotFound();
    }

    #[Test]
    public function read_share_cannot_edit(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Read);

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertOk();

        $this->actingAs($bob)
            ->patchJson(route('api.notes.update', $note), ['title' => 'nope'])
            ->assertForbidden();
    }

    #[Test]
    public function edit_share_can_update_but_owner_stays_owner(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->patchJson(route('api.notes.update', $note), ['title' => 'ok'])
            ->assertOk();

        $this->assertSame('ok', $note->fresh()->title);
        $this->assertSame($alice->id, $note->fresh()->user_id);
    }

    #[Test]
    public function revoked_share_blocks_access(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);
        $sharing = app(SharingService::class);
        $sharing->shareNoteWithUser($note, $alice, $bob, SharePermission::Read);
        $sharing->revokeNoteShare($note, $alice, $bob);

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();
    }

    #[Test]
    public function expired_link_is_not_found(): void
    {
        [$alice] = $this->twoUsers();
        $note = $this->noteFor($alice);
        $link = app(SharingService::class)->createLink($note, $alice, SharePermission::Read, now()->subMinute());

        $this->get(route('shares.public', $link->token))->assertNotFound();
    }

    #[Test]
    public function inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function non_admin_cannot_open_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    #[Test]
    public function admin_can_open_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    #[Test]
    public function admin_cannot_silently_open_another_users_note(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$alice] = $this->twoUsers();
        $note = $this->noteFor($alice);

        $this->actingAs($admin)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();
    }

    #[Test]
    public function note_share_recipient_opens_workspace_and_only_sees_shared_notes(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $shared = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
            'title' => 'Shared with Bob',
        ]);
        Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
            'title' => 'Secret Alice',
        ]);
        app(SharingService::class)->shareNoteWithUser($shared, $alice, $bob, SharePermission::Read);

        $this->actingAs($bob)
            ->get(route('workspaces.show', $workspace))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desktop/Show')
                ->has('notes', 1)
                ->where('notes.0.title', 'Shared with Bob')
                ->where('canEdit', false));
    }

    #[Test]
    public function share_recipient_cannot_duplicate_workspace(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->post(route('workspaces.duplicate', $note->workspace))
            ->assertForbidden();
    }

    #[Test]
    public function readers_do_not_receive_share_lists(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Read);

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertOk()
            ->assertJsonPath('canShare', false)
            ->assertJsonCount(0, 'shares')
            ->assertJsonCount(0, 'links');
    }

    #[Test]
    public function revoked_share_link_returns_404(): void
    {
        [$alice] = $this->twoUsers();
        $note = $this->noteFor($alice);
        $link = app(SharingService::class)->createLink($note, $alice, SharePermission::Read);

        $this->actingAs($alice)
            ->deleteJson(route('api.links.destroy', $link))
            ->assertOk();

        $this->get(route('shares.public', $link->token))->assertNotFound();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function twoUsers(): array
    {
        return [User::factory()->create(), User::factory()->create()];
    }

    private function noteFor(User $user): Note
    {
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);

        return Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
    }
}
