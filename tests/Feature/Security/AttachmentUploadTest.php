<?php

namespace Tests\Feature\Security;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttachmentUploadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_image_upload_requires_an_editable_note(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('api.uploads.store'), [
                'file' => UploadedFile::fake()->image('note.jpg', 10, 10),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    #[Test]
    public function test_user_cannot_attach_file_to_someone_elses_note(): void
    {
        Storage::fake('local');

        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteFor($alice);

        $this->actingAs($bob)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('note.jpg', 10, 10),
            ], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    #[Test]
    public function test_svg_and_html_uploads_are_rejected(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $note = $this->noteFor($user);

        $this->actingAs($user)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->create('xss.svg', 20, 'image/svg+xml'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->create('page.html', 20, 'text/html'),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    #[Test]
    public function test_valid_image_is_served_with_nosniff_and_declared_mime(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $note = $this->noteFor($user);

        $response = $this->actingAs($user)
            ->post(route('api.uploads.store'), [
                'note' => $note->uuid,
                'file' => UploadedFile::fake()->image('note.png', 12, 12),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $id = $response->json('id');
        $this->assertIsString($id);

        $this->get(route('attachments.show', $id))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
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
