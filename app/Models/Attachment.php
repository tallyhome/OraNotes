<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'note_id', 'disk', 'path', 'original_name', 'mime', 'size'])]
class Attachment extends Model
{
    use HasPublicUuid;

    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment): void {
            if (! self::pathIsSafe($attachment->path)) {
                return;
            }

            Storage::disk($attachment->disk ?: 'local')->delete($attachment->path);
        });
    }

    public static function pathIsSafe(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        if (str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }

        return str_starts_with($path, 'attachments/') && ! str_starts_with($path, '/');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function publicUrl(): string
    {
        return route('attachments.show', $this);
    }
}
