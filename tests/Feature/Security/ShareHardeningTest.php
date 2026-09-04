<?php

namespace Tests\Feature\Security;

use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShareHardeningTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_unknown_share_email_does_not_reveal_missing_account(): void
    {
        $alice = User::factory()->create();
        $note = $this->noteFor($alice);

        $this->actingAs($alice)
            ->postJson(route('api.notes.shares.store', $note), [
                'email' => 'nobody@example.test',
                'permission' => SharePermission::Read->value,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonMissingPath('user')
            ->assertDontSee('exists', false);

        $this->assertDatabaseMissing('note_shares', ['note_id' => $note->id]);
    }

    #[Test]
    public function test_note_share_recipient_does_not_receive_workspace_member_emails(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
        ]);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Read);

        $this->actingAs($bob)
            ->get(route('workspaces.show', $workspace))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desktop/Show')
                ->missing('workspace.owner.email')
                ->missing('workspace.members'));
    }

    #[Test]
    public function test_edit_share_cannot_duplicate_into_owners_workspace(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->postJson(route('api.notes.duplicate', $note))
            ->assertForbidden();

        $this->assertSame(1, $note->workspace->notes()->count());
    }

    #[Test]
    public function test_edit_share_cannot_trash_owners_note(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->deleteJson(route('api.notes.destroy', $note))
            ->assertForbidden();

        $this->assertNull($note->fresh()->deleted_at);
    }

    #[Test]
    public function test_edit_share_cannot_lock_owners_note(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->patchJson(route('api.notes.update', $note), ['is_locked' => true])
            ->assertOk();

        $this->assertFalse($note->fresh()->is_locked);
    }

    #[Test]
    public function test_password_reset_does_not_enumerate_accounts(): void
    {
        $known = User::factory()->create();

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => $known->email])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status');

        $this->from('/forgot-password')
            ->post('/forgot-password', ['email' => 'missing@example.test'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function test_unverified_user_cannot_open_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    #[Test]
    public function test_workspace_editor_cannot_archive_someone_elses_bureau(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $workspace = Workspace::factory()->create(['user_id' => $alice->id, 'is_archived' => false]);
        $workspace->members()->syncWithoutDetaching([
            $bob->id => ['permission' => SharePermission::Edit->value],
        ]);

        $this->actingAs($bob)
            ->patch(route('workspaces.update', $workspace), ['is_archived' => true])
            ->assertRedirect();

        $this->assertFalse($workspace->fresh()->is_archived);
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
