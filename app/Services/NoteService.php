<?php

namespace App\Services;

use App\Enums\ActivityAction;
use App\Enums\NoteColor;
use App\Enums\NotePriority;
use App\Enums\NoteStatus;
use App\Models\Note;
use App\Models\NoteVersion;
use App\Models\User;
use App\Models\Workspace;
use App\Support\HtmlSanitizer;
use App\Support\OraDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NoteService
{
    public function __construct(private ActivityLogger $logger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Workspace $workspace, User $user, array $data = []): Note
    {
        $maxZ = (int) $workspace->notes()->max('z_index');
        $document = OraDocument::isValid($data['document'] ?? null)
            ? $data['document']
            : OraDocument::empty();

        if ($error = OraDocument::limitError($document)) {
            throw ValidationException::withMessages(['document' => $error]);
        }

        $document = OraDocument::sanitize($document);

        $note = Note::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'title' => $data['title'] ?? 'Sans titre',
            'document' => $document,
            'html_preview' => HtmlSanitizer::clean($data['html_preview'] ?? ''),
            'text_content' => OraDocument::extractText($document),
            'color' => $data['color'] ?? NoteColor::Yellow->value,
            'icon' => $data['icon'] ?? null,
            'x' => $data['x'] ?? 120 + random_int(0, 80),
            'y' => $data['y'] ?? 100 + random_int(0, 80),
            'width' => $data['width'] ?? 260,
            'height' => $data['height'] ?? 220,
            'rotation' => $data['rotation'] ?? 0,
            'z_index' => $data['z_index'] ?? $maxZ + 1,
            'status' => $data['status'] ?? NoteStatus::Idea->value,
            'priority' => $data['priority'] ?? NotePriority::Normal->value,
        ]);

        if (! empty($data['tags']) && is_array($data['tags'])) {
            $this->syncTags($note, $user, $data['tags']);
        }

        $this->logger->log(ActivityAction::NoteCreated, $user, $note, [
            'workspace_id' => $workspace->uuid,
        ]);

        return $note->fresh(['tags', 'author']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Note $note, User $user, array $data): Note
    {
        unset($data['user_id'], $data['workspace_id'], $data['id'], $data['uuid']);

        if (array_key_exists('is_locked', $data)
            && (int) $note->user_id !== (int) $user->id
            && ! $note->workspace?->isOwnedBy($user)
        ) {
            unset($data['is_locked']);
        }

        $dirtyDocument = array_key_exists('document', $data) && OraDocument::isValid($data['document']);

        if ($dirtyDocument) {
            if ($error = OraDocument::limitError($data['document'])) {
                throw ValidationException::withMessages(['document' => $error]);
            }
            $this->snapshot($note, $user);
            $data['document'] = OraDocument::sanitize($data['document']);
            $data['text_content'] = OraDocument::extractText($data['document']);
        }

        if (array_key_exists('html_preview', $data)) {
            $data['html_preview'] = HtmlSanitizer::clean($data['html_preview']);
        }

        $note->fill($data);
        $note->save();

        if (array_key_exists('tags', $data) && is_array($data['tags'])) {
            $this->syncTags($note, $user, $data['tags']);
        }

        $this->logger->log(ActivityAction::NoteUpdated, $user, $note);

        return $note->fresh(['tags', 'author']);
    }

    /**
     * @param  list<array{id: int|string, x: float|int, y: float|int, width?: float|int, height?: float|int, z_index?: int, rotation?: float|int}>  $positions
     */
    public function updatePositions(Workspace $workspace, array $positions): void
    {
        DB::transaction(function () use ($workspace, $positions) {
            foreach ($positions as $item) {
                $uuid = (string) ($item['id'] ?? $item['uuid'] ?? '');
                if ($uuid === '') {
                    continue;
                }

                $payload = array_filter([
                    'x' => $item['x'] ?? null,
                    'y' => $item['y'] ?? null,
                    'width' => $item['width'] ?? null,
                    'height' => $item['height'] ?? null,
                    'z_index' => $item['z_index'] ?? null,
                    'rotation' => $item['rotation'] ?? null,
                ], fn ($value) => $value !== null);

                if ($payload === []) {
                    continue;
                }

                Note::query()
                    ->where('workspace_id', $workspace->id)
                    ->where('uuid', $uuid)
                    ->where('is_locked', false)
                    ->update($payload);
            }
        });
    }

    public function duplicate(Note $note, User $user, ?Workspace $workspace = null, float $offsetX = 32, float $offsetY = 32): Note
    {
        $target = $workspace ?? $note->workspace;

        return $this->create($target, $user, [
            'title' => $note->title === '' ? 'Sans titre' : $note->title.' (copie)',
            'document' => $note->document,
            'html_preview' => $note->html_preview,
            'color' => $note->color?->value ?? NoteColor::Yellow->value,
            'icon' => $note->icon,
            'x' => $note->x + $offsetX,
            'y' => $note->y + $offsetY,
            'width' => $note->width,
            'height' => $note->height,
            'status' => $note->status?->value ?? NoteStatus::Idea->value,
            'priority' => $note->priority?->value ?? NotePriority::Normal->value,
            'tags' => $note->tags->pluck('name')->all(),
        ]);
    }

    public function trash(Note $note, User $user): void
    {
        $note->delete();
        $this->logger->log(ActivityAction::NoteDeleted, $user, $note);
    }

    public function restore(Note $note, User $user): Note
    {
        $note->restore();
        $this->logger->log(ActivityAction::NoteRestored, $user, $note);

        return $note->refresh();
    }

    public function forceDelete(Note $note, User $user): void
    {
        $this->logger->log(ActivityAction::NoteForceDeleted, $user, $note, [
            'title' => $note->title,
        ]);
        $note->forceDelete();
    }

    /**
     * @param  list<string>  $names
     */
    public function syncTags(Note $note, User $user, array $names): void
    {
        $ids = [];
        $names = array_slice($names, 0, 20);
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $tag = $user->tags()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'color' => 'gray'],
            );
            $ids[] = $tag->id;
        }
        $note->tags()->sync($ids);
    }

    private function snapshot(Note $note, User $user): void
    {
        $count = $note->versions()->count();
        if ($count >= 30) {
            $note->versions()->oldest()->first()?->delete();
        }

        NoteVersion::query()->create([
            'note_id' => $note->id,
            'created_by' => $user->id,
            'document' => $note->document,
            'title' => $note->title,
        ]);
    }
}
