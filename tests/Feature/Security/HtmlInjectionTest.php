<?php

namespace Tests\Feature\Security;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HtmlInjectionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_html_preview_is_sanitized_even_without_document_update(): void
    {
        $user = User::factory()->create();
        $note = $this->noteFor($user);

        $this->actingAs($user)
            ->patchJson(route('api.notes.update', $note), [
                'html_preview' => '<p>ok</p><img src=x onerror=alert(1)><script>alert(1)</script>',
            ])
            ->assertOk();

        $preview = $note->fresh()->html_preview;

        $this->assertStringContainsString('ok', $preview);
        $this->assertStringNotContainsString('onerror', $preview);
        $this->assertStringNotContainsString('<script', $preview);
        $this->assertStringNotContainsString('alert(1)', $preview);
    }

    #[Test]
    public function test_note_resource_does_not_echo_raw_event_handlers(): void
    {
        $user = User::factory()->create();
        $note = $this->noteFor($user);
        $note->forceFill([
            'html_preview' => '<p>ok</p><img src="x"onerror="alert(1)">',
        ])->save();

        $this->actingAs($user)
            ->getJson(route('api.notes.show', $note))
            ->assertOk();

        $preview = $this->actingAs($user)
            ->getJson(route('api.notes.show', $note))
            ->json('note.html_preview');

        $this->assertStringNotContainsString('onerror', $preview);
        $this->assertStringContainsString('ok', $preview);
    }

    #[Test]
    public function test_html_export_sanitizes_preview(): void
    {
        $user = User::factory()->create();
        $note = $this->noteFor($user);
        $note->forceFill([
            'html_preview' => '<p>ok</p><img src=x onerror=alert(1)>',
        ])->save();

        $html = $this->actingAs($user)
            ->get(route('api.notes.export.html', $note))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ok', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    #[Test]
    public function test_document_javascript_urls_are_stripped_on_save(): void
    {
        $user = User::factory()->create();
        $note = $this->noteFor($user);

        $this->actingAs($user)
            ->patchJson(route('api.notes.update', $note), [
                'document' => [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [[
                        'type' => 'paragraph',
                        'content' => [[
                            'type' => 'text',
                            'text' => 'lien',
                            'marks' => [[
                                'type' => 'link',
                                'attrs' => ['href' => 'javascript:alert(1)'],
                            ]],
                        ]],
                    ]],
                ],
            ])
            ->assertOk();

        $href = data_get($note->fresh()->document, 'content.0.content.0.marks.0.attrs.href');

        $this->assertNull($href);
    }

    #[Test]
    public function test_profile_cannot_mass_assign_admin_role(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'admin',
                'is_active' => 0,
            ])
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertFalse($user->isAdmin());
        $this->assertTrue($user->is_active);
    }

    #[Test]
    public function test_security_headers_are_present(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
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
