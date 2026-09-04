<?php

namespace Tests\Feature\Security;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AttachmentService;
use App\Services\SharingService;
use App\Support\OraDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InputLimitsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_oversized_document_json_is_rejected(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.notes.update', $note), [
                'document' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => str_repeat('A', OraDocument::MAX_JSON_BYTES)]],
                    ]],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
    }

    #[Test]
    public function test_deeply_nested_document_is_rejected(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        $node = ['type' => 'text', 'text' => 'x'];
        for ($i = 0; $i < OraDocument::MAX_DEPTH + 5; $i++) {
            $node = ['type' => 'blockquote', 'content' => [$node]];
        }

        $this->actingAs($user)
            ->patchJson(route('api.notes.update', $note), [
                'document' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [$node],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');
    }

    #[Test]
    public function test_too_many_tags_are_rejected(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        $tags = [];
        for ($i = 0; $i < 21; $i++) {
            $tags[] = 'tag'.$i;
        }

        $this->actingAs($user)
            ->patchJson(route('api.notes.update', $note), ['tags' => $tags])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tags');
    }

    #[Test]
    public function test_html_preview_over_limit_is_rejected(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.notes.update', $note), [
                'html_preview' => str_repeat('a', 100_001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('html_preview');
    }

    #[Test]
    public function test_position_batch_over_200_is_rejected(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        $positions = [];
        for ($i = 0; $i < 201; $i++) {
            $positions[] = ['id' => $note->uuid, 'x' => $i, 'y' => $i];
        }

        $this->actingAs($user)
            ->patchJson(route('api.notes.positions', $workspace), ['positions' => $positions])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('positions');
    }

    #[Test]
    public function test_search_query_over_limit_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.search', ['q' => str_repeat('p', 121)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');
    }

    #[Test]
    public function test_eleventh_active_share_link_is_rejected(): void
    {
        $user = User::factory()->create();
        $note = $this->noteFor($user);
        $sharing = app(SharingService::class);

        for ($i = 0; $i < SharingService::MAX_ACTIVE_LINKS; $i++) {
            $sharing->createLink($note, $user);
        }

        $this->actingAs($user)
            ->postJson(route('api.notes.links.store', $note))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('link');
    }

    #[Test]
    public function test_twenty_first_attachment_on_a_note_is_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $note = $this->noteFor($user);

        $this->actingAs($user);

        for ($i = 0; $i < AttachmentService::MAX_PER_NOTE; $i++) {
            $this->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('n'.$i.'.png', 6, 6),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->post(route('api.uploads.store'), [
            'note' => $note->uuid,
            'file' => UploadedFile::fake()->image('overflow.png', 6, 6),
        ], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    #[Test]
    public function test_reasonable_document_is_accepted(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.notes.update', $note), [
                'document' => OraDocument::empty(),
                'html_preview' => '<p>ok</p>',
                'tags' => ['urgent', 'design'],
            ])
            ->assertOk();
    }

    #[Test]
    public function test_unknown_share_link_is_not_found(): void
    {
        $this->get(route('shares.public', str_repeat('a', 48)))->assertNotFound();
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
