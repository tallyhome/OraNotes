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

class ArchivedNoteAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_share_recipient_cannot_view_archived_note_by_uuid(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice, ['is_archived' => true]);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();

        $this->actingAs($bob)
            ->patchJson(route('api.notes.update', $note), ['title' => 'nope'])
            ->assertForbidden();
    }

    #[Test]
    public function test_workspace_reader_cannot_open_archived_note_or_archived_workspace(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $workspace->members()->syncWithoutDetaching([
            $bob->id => ['permission' => SharePermission::Read->value],
        ]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
            'is_archived' => true,
        ]);

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();

        $workspace->update(['is_archived' => true]);

        $this->actingAs($bob)
            ->get(route('workspaces.show', $workspace))
            ->assertForbidden();
    }

    #[Test]
    public function test_owner_can_view_and_restore_archived_note(): void
    {
        [$alice] = $this->twoUsers();
        $note = $this->noteFor($alice, ['is_archived' => true]);

        $this->actingAs($alice)
            ->getJson(route('api.notes.show', $note))
            ->assertOk()
            ->assertJsonPath('note.is_archived', true);

        $this->actingAs($alice)
            ->patchJson(route('api.notes.update', $note), ['is_archived' => false])
            ->assertOk();

        $this->assertFalse($note->fresh()->is_archived);
    }

    #[Test]
    public function test_archived_note_is_hidden_from_shared_index_and_search(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice, ['title' => 'Secret archived', 'is_archived' => true]);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Read);

        $this->actingAs($bob)
            ->get(route('shared'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Shared')->has('notes', 0));

        $this->actingAs($bob)
            ->getJson(route('api.search', ['q' => 'Secret archived']))
            ->assertOk()
            ->assertJsonCount(0, 'notes');
    }

    #[Test]
    public function test_public_link_to_archived_note_returns_404(): void
    {
        [$alice] = $this->twoUsers();
        $note = $this->noteFor($alice);
        $link = app(SharingService::class)->createLink($note, $alice, SharePermission::Read);
        $note->update(['is_archived' => true]);

        $this->get(route('shares.public', $link->token))->assertNotFound();
    }

    #[Test]
    public function test_trashed_note_uuid_is_not_found_on_regular_api(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);
        $note->delete();

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note->uuid))
            ->assertNotFound();

        $this->actingAs($alice)
            ->getJson(route('api.notes.show', $note->uuid))
            ->assertNotFound();
    }

    #[Test]
    public function test_stranger_cannot_open_archived_note_by_guessing_uuid(): void
    {
        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice, ['is_archived' => true]);

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function twoUsers(): array
    {
        return [User::factory()->create(), User::factory()->create()];
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function noteFor(User $user, array $attrs = []): Note
    {
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);

        return Note::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ], $attrs));
    }
}
