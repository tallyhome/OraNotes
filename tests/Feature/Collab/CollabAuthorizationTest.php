<?php

namespace Tests\Feature\Collab;

use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\AccessRevokedNotification;
use App\Notifications\CollaboratorJoinedNotification;
use App\Notifications\InviteAcceptedNotification;
use App\Services\SharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CollabAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_reader_can_open_collab_state_but_cannot_push_updates(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Read);

        $this->actingAs($bob)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertOk()
            ->assertJsonPath('canEdit', false);

        $this->actingAs($bob)
            ->postJson(route('api.notes.collab.update', $note), ['update' => 'AAAA'])
            ->assertForbidden();
    }

    #[Test]
    public function test_editor_can_push_a_yjs_state_snapshot(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->postJson(route('api.notes.collab.update', $note), ['state' => base64_encode('yjs-state')])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotNull($note->fresh()->collab_state);
    }

    #[Test]
    public function test_stranger_and_disabled_user_cannot_subscribe(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $disabled = User::factory()->create(['is_active' => false]);
        $note = $this->noteFor($alice);

        $this->actingAs($bob)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertForbidden();

        $this->actingAs($disabled)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function test_guest_cannot_open_collab(): void
    {
        $alice = User::factory()->create();
        $note = $this->noteFor($alice);

        $this->getJson(route('api.notes.collab.show', $note))
            ->assertUnauthorized();
    }

    #[Test]
    public function test_note_update_cannot_mass_assign_collab_state(): void
    {
        $alice = User::factory()->create();
        $note = $this->noteFor($alice);

        $this->actingAs($alice)
            ->patchJson(route('api.notes.update', $note), [
                'title' => 'ok',
                'collab_state' => 'injected',
                'collab_seq' => 99,
            ])
            ->assertOk();

        $fresh = $note->fresh();
        $this->assertNull($fresh->collab_state);
        $this->assertSame(0, (int) $fresh->collab_seq);
        $this->assertSame('ok', $fresh->title);
    }

    #[Test]
    public function test_joining_collab_notifies_owner_once(): void
    {
        Notification::fake();
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertOk()
            ->assertJsonPath('canEdit', true);

        Notification::assertSentTo($alice, CollaboratorJoinedNotification::class);
        Notification::assertSentTo($alice, InviteAcceptedNotification::class);

        $this->actingAs($bob)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertOk();

        Notification::assertSentToTimes($alice, CollaboratorJoinedNotification::class, 1);
    }

    #[Test]
    public function test_revoking_share_notifies_the_target(): void
    {
        Notification::fake();
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteFor($alice);
        $sharing = app(SharingService::class);
        $sharing->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);
        $sharing->revokeNoteShare($note, $alice, $bob);

        Notification::assertSentTo($bob, AccessRevokedNotification::class);
    }

    #[Test]
    public function test_revoked_editor_loses_collab_write(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteFor($alice);
        $sharing = app(SharingService::class);
        $sharing->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);
        $sharing->revokeNoteShare($note, $alice, $bob);

        $this->actingAs($bob)
            ->postJson(route('api.notes.collab.update', $note), ['update' => 'xx'])
            ->assertForbidden();
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
