<?php

namespace App\Models;

use App\Enums\NoteColor;
use App\Enums\NotePriority;
use App\Enums\NoteStatus;
use App\Enums\SharePermission;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'workspace_id',
    'user_id',
    'title',
    'document',
    'html_preview',
    'text_content',
    'color',
    'icon',
    'x',
    'y',
    'width',
    'height',
    'rotation',
    'z_index',
    'status',
    'priority',
    'is_locked',
    'is_favorite',
    'is_archived',
])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'x' => 'float',
            'y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'rotation' => 'float',
            'z_index' => 'integer',
            'color' => NoteColor::class,
            'status' => NoteStatus::class,
            'priority' => NotePriority::class,
            'is_locked' => 'boolean',
            'is_favorite' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(NoteShare::class);
    }

    public function sharedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'note_shares')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function versions(): HasMany
    {
        return $this->hasMany(NoteVersion::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function shareLinks(): MorphMany
    {
        return $this->morphMany(ShareLink::class, 'shareable');
    }

    public function sharePermissionFor(User $user): ?SharePermission
    {
        $share = $this->relationLoaded('shares')
            ? $this->shares->firstWhere('user_id', $user->id)
            : $this->shares()->where('user_id', $user->id)->first();

        return $share ? SharePermission::tryFrom($share->permission->value ?? $share->permission) : null;
    }

    public function scopeNotTrashedActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at')->where('is_archived', false);
    }
}
