<?php

namespace App\Models;

use App\Enums\SharePermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[Fillable([
    'token',
    'shareable_type',
    'shareable_id',
    'permission',
    'expires_at',
    'created_by',
    'is_revoked',
])]
class ShareLink extends Model
{
    protected function casts(): array
    {
        return [
            'permission' => SharePermission::class,
            'expires_at' => 'datetime',
            'is_revoked' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ShareLink $link): void {
            if (empty($link->token)) {
                $link->token = Str::random(48);
            }
        });
    }

    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function isUsable(): bool
    {
        if ($this->is_revoked) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
