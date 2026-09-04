<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CanvasAlignmentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_grid_preference_is_persisted_on_the_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->patchJson(route('workspaces.update', $workspace), [
                'canvas_settings' => ['zoom' => 1.2, 'x' => 40, 'y' => -10, 'snap' => true, 'grid' => true],
            ])
            ->assertOk();

        $settings = $workspace->fresh()->canvas_settings;
        $this->assertTrue($settings['grid']);
        $this->assertTrue($settings['snap']);
        $this->assertEqualsWithDelta(1.2, $settings['zoom'], 0.001);
    }

    #[Test]
    public function test_notes_can_be_positioned_beyond_the_old_4000_by_3000_box(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $user->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'x' => 10,
            'y' => 10,
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.notes.positions', $workspace), [
                'positions' => [
                    ['id' => $note->uuid, 'x' => 8200, 'y' => -1400, 'width' => 260, 'height' => 220],
                ],
            ])
            ->assertOk();

        $note->refresh();
        $this->assertSame(8200.0, $note->x);
        $this->assertSame(-1400.0, $note->y);
    }

    #[Test]
    public function test_reader_cannot_persist_grid_or_positions(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $owner->id]);
        $note = Note::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'x' => 8, 'y' => 8]);
        $workspace->members()->syncWithoutDetaching([
            $reader->id => ['permission' => 'read'],
        ]);

        $this->actingAs($reader)
            ->patchJson(route('workspaces.update', $workspace), [
                'canvas_settings' => ['grid' => true, 'snap' => true, 'zoom' => 2, 'x' => 0, 'y' => 0],
            ])
            ->assertForbidden();

        $this->actingAs($reader)
            ->patchJson(route('api.notes.positions', $workspace), [
                'positions' => [['id' => $note->uuid, 'x' => 99, 'y' => 99]],
            ])
            ->assertForbidden();

        $this->assertSame(8.0, $note->fresh()->x);
    }
}
