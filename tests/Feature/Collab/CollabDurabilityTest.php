<?php

namespace Tests\Feature\Collab;

use App\Enums\SharePermission;
use App\Models\CollabEvent;
use App\Models\Note;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Collab\CollabService;
use App\Services\SharingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CollabDurabilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_stale_snapshot_does_not_replace_newer_collab_state(): void
    {
        [$alice, $bob, $note] = $this->sharedNote();
        $collab = app(CollabService::class);

        $first = $collab->applyState($note, $alice, 'SNAP_NEW', 0);
        $second = $collab->applyState($note->fresh(), $bob, 'SNAP_STALE', 0);

        $this->assertTrue($first['accepted']);
        $this->assertFalse($second['accepted']);
        $this->assertSame(1, $first['seq']);
        $this->assertSame(1, $second['seq']);
        $this->assertSame('SNAP_NEW', $note->fresh()->collab_state);
    }

    #[Test]
    public function test_stale_snapshot_is_appended_to_the_event_log(): void
    {
        [$alice, $bob, $note] = $this->sharedNote();
        $collab = app(CollabService::class);
        $collab->applyState($note, $alice, 'SNAP_NEW', 0);
        $collab->applyState($note->fresh(), $bob, 'SNAP_STALE', 0);

        $updates = array_values(array_filter(
            $collab->pull($note->fresh(), 0),
            fn (array $event) => ($event['type'] ?? '') === 'update',
        ));

        $this->assertNotSame([], $updates);
        $this->assertSame('SNAP_STALE', $updates[0]['update'] ?? null);
    }

    #[Test]
    public function test_relayed_updates_survive_cache_flush(): void
    {
        [$alice, $bob, $note] = $this->sharedNote();
        $collab = app(CollabService::class);

        $collab->relayUpdate($note, $alice, 'UPD_A');
        $collab->relayUpdate($note, $bob, 'UPD_B');
        Cache::flush();

        $updates = array_values(array_filter(
            $collab->pull($note->fresh(), 0),
            fn (array $event) => ($event['type'] ?? '') === 'update',
        ));

        $this->assertSame(['UPD_A', 'UPD_B'], array_column($updates, 'update'));
    }

    #[Test]
    public function test_bootstrap_includes_updates_after_the_snapshot(): void
    {
        [$alice, $bob, $note] = $this->sharedNote();
        $collab = app(CollabService::class);
        $collab->applyState($note, $alice, 'SNAP_V1', 0);
        $collab->relayUpdate($note->fresh(), $bob, 'UPD_AFTER');

        $this->actingAs($alice)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertOk()
            ->assertJsonPath('state', 'SNAP_V1')
            ->assertJsonPath('events.0.update', 'UPD_AFTER');
    }

    #[Test]
    public function test_stream_uses_last_event_id_and_does_not_replay_older_events(): void
    {
        [$alice, $bob, $note] = $this->sharedNote();
        $collab = app(CollabService::class);
        $collab->relayUpdate($note, $alice, 'OLD_ONE');
        $collab->relayUpdate($note, $alice, 'NEW_ONE');
        $firstId = (int) CollabEvent::query()->where('note_id', $note->id)->orderBy('id')->value('id');

        $content = $this->actingAs($bob)
            ->withHeaders(['Last-Event-ID' => (string) $firstId])
            ->get(route('api.notes.collab.stream', $note))
            ->assertOk()
            ->streamedContent();

        $this->assertStringNotContainsString('OLD_ONE', $content);
        $this->assertStringContainsString('NEW_ONE', $content);
    }

    #[Test]
    public function test_stream_end_does_not_remove_presence(): void
    {
        [$alice, $bob, $note] = $this->sharedNote();

        $this->actingAs($bob)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertOk();

        $this->actingAs($bob)
            ->get(route('api.notes.collab.stream', $note))
            ->assertOk()
            ->streamedContent();

        $members = Cache::get('collab:presence:'.$note->uuid, []);
        $this->assertArrayHasKey($bob->id, $members);
    }

    #[Test]
    public function test_sequential_updates_are_all_retained_with_monotonic_ids(): void
    {
        [$alice, $bob, $note] = $this->sharedNote();
        $collab = app(CollabService::class);

        for ($i = 1; $i <= 20; $i++) {
            $collab->relayUpdate($note, $i % 2 === 0 ? $bob : $alice, 'SEQ-'.$i);
        }

        $events = array_values(array_filter(
            $collab->pull($note->fresh(), 0),
            fn (array $event) => ($event['type'] ?? '') === 'update',
        ));
        $ids = array_column($events, 'id');
        $sorted = $ids;
        sort($sorted);

        $this->assertSame(20, count($events));
        $this->assertSame($sorted, $ids);
        $this->assertSame('SEQ-1', $events[0]['update']);
        $this->assertSame('SEQ-20', $events[19]['update']);
    }

    #[Test]
    public function test_two_fresh_clients_receive_the_same_snapshot_and_tail(): void
    {
        [$alice, $bob, $note] = $this->sharedNote();
        $collab = app(CollabService::class);
        $collab->applyState($note, $alice, 'SHARED_SNAP', 0);
        $collab->relayUpdate($note->fresh(), $bob, 'TAIL');

        $aliceShow = $this->actingAs($alice)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertOk()
            ->json();
        $bobShow = $this->actingAs($bob)
            ->getJson(route('api.notes.collab.show', $note))
            ->assertOk()
            ->json();

        $this->assertSame('SHARED_SNAP', $aliceShow['state']);
        $this->assertSame($aliceShow['state'], $bobShow['state']);
        $this->assertSame($aliceShow['seq'], $bobShow['seq']);
        $this->assertContains('TAIL', array_column($aliceShow['events'], 'update'));
        $this->assertContains('TAIL', array_column($bobShow['events'], 'update'));
    }

    #[Test]
    public function test_note_update_cannot_mass_assign_snapshot_cursor(): void
    {
        [$alice, , $note] = $this->sharedNote();

        $this->actingAs($alice)
            ->patchJson(route('api.notes.update', $note), [
                'title' => 'ok',
                'collab_snapshot_event_id' => 99,
            ])
            ->assertOk();

        $this->assertNull($note->fresh()->collab_snapshot_event_id);
    }

    /**
     * @return array{0: User, 1: User, 2: Note}
     */
    private function sharedNote(): array
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $workspace = Workspace::factory()->create(['user_id' => $alice->id]);
        $note = Note::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $alice->id,
        ]);
        app(SharingService::class)->shareNoteWithUser($note, $alice, $bob, SharePermission::Edit);

        return [$alice, $bob, $note];
    }
}
