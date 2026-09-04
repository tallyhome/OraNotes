<?php

namespace App\Services\Collab;

use App\Models\Note;
use App\Models\User;
use App\Notifications\CollaboratorJoinedNotification;
use App\Notifications\InviteAcceptedNotification;
use Illuminate\Support\Facades\Cache;

class CollabService
{
    public function snapshot(Note $note): array
    {
        return [
            'state' => $note->collab_state,
            'seq' => (int) $note->collab_seq,
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Persist a Yjs update produced by a client. The CRDT merge happens in Yjs
     * on peers; the server stores the latest encoded state (not last-write JSON).
     *
     * @return array{seq: int}
     */
    public function applyState(Note $note, User $user, string $state, int $clientSeq = 0): array
    {
        $note->forceFill([
            'collab_state' => $state,
            'collab_seq' => max((int) $note->collab_seq, $clientSeq) + 1,
        ])->save();

        $payload = [
            'type' => 'state',
            'state' => $state,
            'seq' => (int) $note->collab_seq,
            'user' => ['id' => $user->id, 'name' => $user->name],
        ];
        $this->push($note, $payload);

        return ['seq' => (int) $note->collab_seq];
    }

    /**
     * Relay a binary Yjs update without replacing the snapshot yet.
     */
    public function relayUpdate(Note $note, User $user, string $update): void
    {
        $this->push($note, [
            'type' => 'update',
            'update' => $update,
            'user' => ['id' => $user->id, 'name' => $user->name],
        ]);
    }

    /**
     * @return list<array{id: int, name: string, avatar: string|null}>
     */
    public function join(Note $note, User $user): array
    {
        $key = $this->presenceKey($note);
        $members = Cache::get($key, []);
        $isNew = ! isset($members[$user->id]);
        $members[$user->id] = [
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatarUrl(),
            'at' => now()->timestamp,
        ];
        Cache::put($key, $members, now()->addMinutes(10));
        $this->push($note, ['type' => 'presence', 'members' => array_values($members)]);

        if ($isNew && (int) $note->user_id !== (int) $user->id) {
            $this->notifyPresence($note, $user);
        }

        return array_values($members);
    }

    /**
     * @return list<array{id: int, name: string, avatar: string|null}>
     */
    public function leave(Note $note, User $user): array
    {
        $key = $this->presenceKey($note);
        $members = Cache::get($key, []);
        unset($members[$user->id]);
        Cache::put($key, $members, now()->addMinutes(10));
        $this->push($note, ['type' => 'presence', 'members' => array_values($members)]);

        return array_values($members);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pull(Note $note, int $after): array
    {
        $events = Cache::get($this->eventsKey($note), []);

        return array_values(array_filter(
            $events,
            fn ($event) => ($event['id'] ?? 0) > $after,
        ));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function push(Note $note, array $event): void
    {
        $key = $this->eventsKey($note);
        $events = Cache::get($key, []);
        $event['id'] = (int) (end($events)['id'] ?? 0) + 1;
        $events[] = $event;
        if (count($events) > 80) {
            $events = array_slice($events, -40);
        }
        Cache::put($key, $events, now()->addMinutes(10));
    }

    private function notifyPresence(Note $note, User $user): void
    {
        $note->loadMissing('author');
        $joinedKey = 'notify:collab-joined:'.$note->id.':'.$user->id;
        if (! Cache::has($joinedKey) && $note->author) {
            Cache::put($joinedKey, true, now()->addMinutes(30));
            $note->author->notify(new CollaboratorJoinedNotification($note, $user));
        }

        $acceptedKey = 'notify:invite-accepted:'.$note->id.':'.$user->id;
        if (! Cache::has($acceptedKey) && $note->author) {
            Cache::put($acceptedKey, true, now()->addDay());
            $note->author->notify(new InviteAcceptedNotification($note, $user));
        }
    }

    private function presenceKey(Note $note): string
    {
        return 'collab:presence:'.$note->uuid;
    }

    private function eventsKey(Note $note): string
    {
        return 'collab:events:'.$note->uuid;
    }
}
