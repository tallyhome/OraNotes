<?php

namespace Tests\Feature\Security;

use App\Enums\SharePermission;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\SharingService;
use App\Support\OraDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrivateDataRetentionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_revoked_user_loses_note_api_search_export_and_attachment(): void
    {
        Storage::fake('local');

        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice, [
            'title' => 'Secret Alice',
            'text_content' => 'Secret Alice corps',
            'html_preview' => '<p>Secret Alice corps</p>',
            'document' => $this->doc('Secret Alice corps'),
        ]);

        $attachmentId = $this->actingAs($alice)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('secret.png', 10, 10),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('id');

        $sharing = app(SharingService::class);
        $sharing->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertOk()
            ->assertJsonPath('note.title', 'Secret Alice')
            ->assertJsonPath('note.document.content.0.content.0.text', 'Secret Alice corps');

        $this->actingAs($bob)
            ->get(route('attachments.show', $attachmentId))
            ->assertOk();

        $this->actingAs($bob)
            ->getJson(route('api.search', ['q' => 'Secret Alice']))
            ->assertOk()
            ->assertJsonCount(1, 'notes');

        $this->actingAs($bob)
            ->getJson(route('api.notes.export.json', $note))
            ->assertOk()
            ->assertJsonPath('document.content.0.content.0.text', 'Secret Alice corps');

        $this->actingAs($alice)
            ->deleteJson(route('api.notes.shares.destroy', [$note, $bob]))
            ->assertOk();

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();

        $this->actingAs($bob)
            ->get(route('attachments.show', $attachmentId))
            ->assertNotFound();

        $this->actingAs($bob)
            ->getJson(route('api.search', ['q' => 'Secret Alice']))
            ->assertOk()
            ->assertJsonCount(0, 'notes');

        $this->actingAs($bob)
            ->getJson(route('api.notes.export.json', $note))
            ->assertForbidden();

        $this->actingAs($bob)
            ->getJson(route('api.notes.export.html', $note))
            ->assertForbidden();

        $this->actingAs($bob)
            ->get(route('workspaces.show', $note->workspace))
            ->assertForbidden();

        $this->actingAs($bob)
            ->get(route('shared'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Shared')->has('notes', 0));
    }

    #[Test]
    public function test_revoked_workspace_member_loses_notes_and_attachments(): void
    {
        Storage::fake('local');

        [$alice, $bob] = $this->twoUsers();
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
            'title' => 'Bureau secret',
            'text_content' => 'Bureau secret',
        ]);

        $attachmentId = $this->actingAs($alice)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('ws.png', 10, 10),
            ], ['Accept' => 'application/json'])
            ->json('id');

        $this->actingAs($alice)
            ->postJson(route('api.workspaces.members.store', $workspace), [
                'email' => $bob->email,
                'permission' => SharePermission::Edit->value,
            ])
            ->assertOk();

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertOk();

        $this->actingAs($bob)
            ->get(route('attachments.show', $attachmentId))
            ->assertOk();

        $this->actingAs($alice)
            ->deleteJson(route('api.workspaces.members.destroy', [$workspace, $bob]))
            ->assertOk();

        $this->actingAs($bob)
            ->getJson(route('api.notes.show', $note))
            ->assertForbidden();

        $this->actingAs($bob)
            ->get(route('attachments.show', $attachmentId))
            ->assertNotFound();
    }

    #[Test]
    public function test_share_link_guest_can_open_attachment_until_revoke(): void
    {
        Storage::fake('local');

        [$alice] = $this->twoUsers();
        $note = $this->noteFor($alice);

        $attachmentId = $this->actingAs($alice)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('link.png', 10, 10),
            ], ['Accept' => 'application/json'])
            ->json('id');

        $create = $this->actingAs($alice)
            ->postJson(route('api.notes.links.store', $note))
            ->assertCreated();

        $token = $create->json('link.token');
        $this->assertIsString($token);
        $this->assertGreaterThanOrEqual(40, strlen($token));

        Auth::logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('attachments.show', $attachmentId))->assertNotFound();

        $this->get(route('shares.public', $token))->assertOk();
        $this->get(route('attachments.show', $attachmentId))->assertOk();

        $this->actingAs($alice)
            ->deleteJson(route('api.links.destroy', $token))
            ->assertOk();

        Auth::logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('shares.public', $token))->assertNotFound();
        $this->get(route('attachments.show', $attachmentId))->assertNotFound();
    }

    #[Test]
    public function test_workspace_share_link_grants_then_loses_attachment(): void
    {
        Storage::fake('local');

        [$alice] = $this->twoUsers();
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
        ]);

        $attachmentId = $this->actingAs($alice)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('desk.png', 10, 10),
            ], ['Accept' => 'application/json'])
            ->json('id');

        $token = $this->actingAs($alice)
            ->postJson(route('api.workspaces.links.store', $workspace))
            ->assertCreated()
            ->json('link.token');

        Auth::logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('shares.public', $token))->assertOk();
        $this->get(route('attachments.show', $attachmentId))->assertOk();

        $this->actingAs($alice)
            ->deleteJson(route('api.links.destroy', $token))
            ->assertOk();

        Auth::logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('shares.public', $token))->assertNotFound();
        $this->get(route('attachments.show', $attachmentId))->assertNotFound();
    }

    #[Test]
    public function test_expired_share_link_does_not_keep_attachment_access(): void
    {
        Storage::fake('local');

        [$alice] = $this->twoUsers();
        $note = $this->noteFor($alice);

        $attachmentId = $this->actingAs($alice)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('exp.png', 8, 8),
            ], ['Accept' => 'application/json'])
            ->json('id');

        $link = app(SharingService::class)->createLink(
            $note,
            $alice,
            SharePermission::Read,
            now()->addMinute(),
        );

        Auth::logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('shares.public', $link->token))->assertOk();
        $this->get(route('attachments.show', $attachmentId))->assertOk();

        $this->travel(2)->minutes();

        $this->get(route('shares.public', $link->token))->assertNotFound();
        $this->get(route('attachments.show', $attachmentId))->assertNotFound();
    }

    #[Test]
    public function test_trashed_and_force_deleted_note_removes_attachment_file(): void
    {
        Storage::fake('local');

        [$alice, $bob] = $this->twoUsers();
        $note = $this->noteFor($alice);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Read);

        $attachmentId = $this->actingAs($alice)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('gone.png', 10, 10),
            ], ['Accept' => 'application/json'])
            ->json('id');

        $attachment = Attachment::query()->where('uuid', $attachmentId)->firstOrFail();
        $path = $attachment->path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($alice)
            ->deleteJson(route('api.notes.destroy', $note))
            ->assertOk();

        $this->actingAs($bob)
            ->get(route('attachments.show', $attachmentId))
            ->assertNotFound();

        $this->actingAs($alice)
            ->get(route('attachments.show', $attachmentId))
            ->assertNotFound();

        $this->actingAs($alice)
            ->deleteJson(route('api.notes.force', $note->uuid))
            ->assertOk();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('attachments', ['uuid' => $attachmentId]);

        $this->actingAs($alice)
            ->get(route('attachments.show', $attachmentId))
            ->assertNotFound();
    }

    #[Test]
    public function test_unsafe_attachment_path_is_not_served(): void
    {
        Storage::fake('local');

        [$alice] = $this->twoUsers();
        $note = $this->noteFor($alice);

        $attachment = Attachment::query()->create([
            'user_id' => $alice->id,
            'note_id' => $note->id,
            'disk' => 'local',
            'path' => '../secret.txt',
            'original_name' => 'secret.txt',
            'mime' => 'text/plain',
            'size' => 4,
        ]);

        $this->actingAs($alice)
            ->get(route('attachments.show', $attachment))
            ->assertNotFound();
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

    /**
     * @return array{version: int, type: string, content: list<array<string, mixed>>}
     */
    private function doc(string $text): array
    {
        $empty = OraDocument::empty();
        $empty['content'][0]['content'][0]['text'] = $text;

        return $empty;
    }
}
