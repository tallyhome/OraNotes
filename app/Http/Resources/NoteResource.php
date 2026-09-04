<?php

namespace App\Http\Resources;

use App\Models\Note;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Note */
class NoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::makeArray($this->resource, includeDocument: (bool) ($this->additional['include_document'] ?? false));
    }

    /**
     * @return array<string, mixed>
     */
    public static function makeArray(Note $note, bool $includeDocument = false): array
    {
        $payload = [
            'id' => $note->uuid,
            'workspace_id' => $note->workspace?->uuid,
            'title' => $note->title,
            'html_preview' => HtmlSanitizer::clean($note->html_preview),
            'color' => $note->color?->value ?? 'yellow',
            'icon' => $note->icon,
            'x' => $note->x,
            'y' => $note->y,
            'width' => $note->width,
            'height' => $note->height,
            'rotation' => $note->rotation,
            'z_index' => $note->z_index,
            'status' => $note->status?->value ?? 'idea',
            'priority' => $note->priority?->value ?? 'normal',
            'is_locked' => $note->is_locked,
            'is_favorite' => $note->is_favorite,
            'is_archived' => $note->is_archived,
            'author' => $note->author ? [
                'id' => $note->author->id,
                'name' => $note->author->name,
            ] : null,
            'tags' => $note->relationLoaded('tags')
                ? $note->tags->map(fn ($tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])->values()
                : [],
            'created_at' => $note->created_at?->toIso8601String(),
            'updated_at' => $note->updated_at?->toIso8601String(),
            'deleted_at' => $note->deleted_at?->toIso8601String(),
        ];

        if ($includeDocument) {
            $payload['document'] = $note->document;
        }

        return $payload;
    }
}
