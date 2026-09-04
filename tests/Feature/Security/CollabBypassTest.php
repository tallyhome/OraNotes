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

class CollabBypassTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function archived_shared_note_cannot_be_opened_via_collab_api(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
            'is_archived' => true,
        ]);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertForbidden();

        $this->actingAs($bob)
            ->postJson(route('api.notes.collab.update', $note), ['update' => 'AAAA'])
            ->assertForbidden();
    }

    #[Test]
    public function reader_cannot_use_workspace_member_edit_to_bypass_note_share_read(): void
    {
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
        ]);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Read);

        $this->actingAs($bob)
            ->postJson(route('api.notes.collab.update', $note), ['state' => base64_encode('nope')])
            ->assertForbidden();
    }
}
