<?php

namespace App\Services\Collab;

use App\Models\CollabEvent;
use App\Models\Note;
use App\Models\User;
use App\Notifications\CollaboratorJoinedNotification;
use App\Notifications\InviteAcceptedNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CollabService
{
    public const EVENT_RETENTION = 400;

    public const PRESENCE_TTL_SECONDS = 120;

    /**
     * @return array{state: ?string, seq: int, updated_at: ?string, snapshot_event_id: int}
     */
    public function snapshot(Note $note): array
    {
        return [
            'state' => $note->collab_state,
            'seq' => (int) $note->collab_seq,
            'updated_at' => $note->updated_at?->toIso8601String(),
            'snapshot_event_id' => (int) ($note->collab_snapshot_event_id ?? 0),
        ];
    }

    /**
     * Persist a Yjs snapshot. Stale checkpoints never overwrite a newer row;
     * their bytes are still appended so peers can merge them as updates.
     *
     * @return array{seq: int, accepted: bool}
     */
    public function applyState(Note $note, User $user, string $state, int $clientSeq = 0): array
    {
        return $this->retryOnLock(fn () => DB::transaction(function () use ($note, $user, $state, $clientSeq): array {
            /** @var Note $locked */
            $locked = Note::query()->whereKey($note->id)->lockForUpdate()->firstOrFail();
            $current = (int) $locked->collab_seq;
            $stale = $clientSeq < $current && filled($locked->collab_state);

            if ($stale) {
                $this->push($locked, [
                    'type' => 'update',
                    'update' => $state,
                    'user' => ['id' => $user->id, 'name' => $user->name],
                ]);

                return ['seq' => $current, 'accepted' => false];
            }

            $locked->forceFill([
                'collab_state' => $state,
                'collab_seq' => $current + 1,
            ])->save();

            $eventId = $this->push($locked, [
                'type' => 'state',
                'state' => $state,
                'seq' => (int) $locked->collab_seq,
                'user' => ['id' => $user->id, 'name' => $user->name],
            ]);

            $locked->forceFill(['collab_snapshot_event_id' => $eventId])->save();
            $this->prune($locked);

            return ['seq' => (int) $locked->collab_seq, 'accepted' => true];
        }));
    }

    /**
     * Persist and relay a binary Yjs update.
     */
    public function relayUpdate(Note $note, User $user, string $update): void
    {
        $this->retryOnLock(function () use ($note, $user, $update): void {
            $this->push($note, [
                'type' => 'update',
                'update' => $update,
                'user' => ['id' => $user->id, 'name' => $user->name],
            ]);
        });
    }

    /**
     * @return list<array{id: int, name: string, avatar: string|null}>
     */
    public function join(Note $note, User $user): array
    {
        $isNew = false;
        $members = $this->withPresenceLock($note, function (array $members) use ($user, &$isNew): array {
            $isNew = ! isset($members[$user->id]);
            $members[$user->id] = [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatarUrl(),
                'at' => now()->timestamp,
            ];

            return $members;
        });

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
        $members = $this->withPresenceLock($note, function (array $members) use ($user): array {
            unset($members[$user->id]);

            return $members;
        });

        $this->push($note, ['type' => 'presence', 'members' => array_values($members)]);

        return array_values($members);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pull(Note $note, int $after): array
    {
        return CollabEvent::query()
            ->where('note_id', $note->id)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->limit(200)
            ->get()
            ->map(fn (CollabEvent $event) => $event->toClientEvent())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function retryOnLock(callable $callback): mixed
    {
        $attempts = 0;

        while (true) {
            $attempts++;
            try {
                return $callback();
            } catch (QueryException $exception) {
                if ($attempts >= 4 || ! $this->isLockError($exception)) {
                    throw $exception;
                }
                usleep(25_000 * $attempts);
            }
        }
    }

    private function isLockError(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'database is locked')
            || str_contains($message, 'Deadlock found')
            || str_contains($message, '1213');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function push(Note $note, array $event): int
    {
        $row = CollabEvent::query()->create([
            'note_id' => $note->id,
            'type' => (string) ($event['type'] ?? 'update'),
            'payload' => $event,
            'user_id' => $event['user']['id'] ?? null,
            'created_at' => now(),
        ]);

        return (int) $row->id;
    }

    private function prune(Note $note): void
    {
        $keepFrom = CollabEvent::query()
            ->where('note_id', $note->id)
            ->orderByDesc('id')
            ->skip(self::EVENT_RETENTION - 1)
            ->value('id');

        if ($keepFrom) {
            CollabEvent::query()
                ->where('note_id', $note->id)
                ->where('id', '<', $keepFrom)
                ->delete();
        }
    }

    /**
     * @param  callable(array<int, array<string, mixed>>): array<int, array<string, mixed>>  $callback
     * @return array<int, array<string, mixed>>
     */
    private function withPresenceLock(Note $note, callable $callback): array
    {
        $key = $this->presenceKey($note);

        return Cache::lock($key.':lock', 5)->block(3, function () use ($key, $callback): array {
            /** @var array<int, array<string, mixed>> $members */
            $members = Cache::get($key, []);
            $cutoff = now()->timestamp - self::PRESENCE_TTL_SECONDS;
            $members = array_filter(
                $members,
                fn ($member) => (int) ($member['at'] ?? 0) >= $cutoff,
            );
            $members = $callback($members);
            Cache::put($key, $members, now()->addMinutes(5));

            return $members;
        });
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
}
