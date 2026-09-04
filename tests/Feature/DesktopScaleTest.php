<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DesktopScaleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function workspace_desktop_loads_with_one_hundred_notes(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        Note::factory()->count(100)->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('workspaces.show', $workspace))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desktop/Show')
                ->has('notes', 100));
    }

    #[Test]
    public function workspace_desktop_and_search_scale_to_two_hundred_fifty_notes(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id, 'name' => 'Scale']);
        Note::factory()->count(249)->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => 'NeedleScale250',
            'text_content' => 'NeedleScale250 body',
        ]);

        $this->actingAs($user)
            ->get(route('workspaces.show', $workspace))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Desktop/Show')
                ->has('notes', 250));

        $this->actingAs($user)
            ->getJson(route('api.search', ['q' => 'NeedleScale250']))
            ->assertOk()
            ->assertJsonFragment(['title' => 'NeedleScale250']);
    }
}
