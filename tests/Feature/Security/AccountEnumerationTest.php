<?php

namespace Tests\Feature\Security;

use App\Enums\SharePermission;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountEnumerationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_login_uses_the_same_error_for_unknown_wrong_and_disabled_accounts(): void
    {
        $active = User::factory()->create();
        $disabled = User::factory()->create(['is_active' => false]);

        $unknown = $this->from('/login')->post('/login', [
            'email' => 'missing@example.test',
            'password' => 'password',
        ]);
        $wrong = $this->from('/login')->post('/login', [
            'email' => $active->email,
            'password' => 'wrong-password',
        ]);
        $inactive = $this->from('/login')->post('/login', [
            'email' => $disabled->email,
            'password' => 'password',
        ]);

        foreach ([$unknown, $wrong, $inactive] as $response) {
            $response->assertSessionHasErrors([
                'email' => __('auth.failed'),
            ]);
        }

        $this->assertGuest();
    }

    #[Test]
    public function test_share_invite_returns_the_same_payload_whether_or_not_the_email_exists(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $note = $this->noteFor($alice);

        $missing = $this->actingAs($alice)
            ->postJson(route('api.notes.shares.store', $note), [
                'email' => 'nobody@example.test',
                'permission' => SharePermission::Read->value,
            ]);

        $existing = $this->actingAs($alice)
            ->postJson(route('api.notes.shares.store', $note), [
                'email' => $bob->email,
                'permission' => SharePermission::Read->value,
            ]);

        $missing->assertOk()->assertJsonPath('ok', true);
        $existing->assertOk()->assertJsonPath('ok', true);
        $this->assertSame($missing->json('message'), $existing->json('message'));
        $this->assertArrayNotHasKey('exists', $missing->json());
        $this->assertArrayNotHasKey('exists', $existing->json());
    }

    #[Test]
    public function test_workspace_invite_does_not_reveal_missing_accounts(): void
    {
        $alice = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);

        $this->actingAs($alice)
            ->postJson(route('api.workspaces.members.store', $workspace), [
                'email' => 'ghost@example.test',
                'permission' => SharePermission::Edit->value,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(0, $workspace->members()->count());
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
