<?php

namespace App\Models;

use App\Enums\SharePermission;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\WorkspaceFactory;
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
    'user_id',
    'name',
    'description',
    'icon',
    'color',
    'is_default',
    'is_archived',
    'is_template',
    'canvas_settings',
])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_archived' => 'boolean',
            'is_template' => 'boolean',
            'canvas_settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('permission')
            ->withTimestamps();
    }

    public function shareLinks(): MorphMany
    {
        return $this->morphMany(ShareLink::class, 'shareable');
    }

    protected static function booted(): void
    {
        static::forceDeleting(function (Workspace $workspace): void {
            $workspace->notes()->withTrashed()->get()->each->forceDelete();
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    public function memberPermission(User $user): ?SharePermission
    {
        if ($this->relationLoaded('members')) {
            $member = $this->members->firstWhere('id', $user->id);
        } else {
            $member = $this->members()->where('users.id', $user->id)->first();
        }

        if (! $member) {
            return null;
        }

        return SharePermission::tryFrom((string) $member->pivot->permission);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $inner) use ($user) {
            $inner->where('user_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->where('users.id', $user->id));
        });
    }
}
