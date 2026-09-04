<?php

namespace App\Models;

use App\Enums\SharePermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['note_id', 'user_id', 'permission'])]
class NoteShare extends Model
{
    protected function casts(): array
    {
        return [
            'permission' => SharePermission::class,
        ];
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
