<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

trait HasPublicUuid
{
    protected static function bootHasPublicUuid(): void
    {
        static::creating(function ($model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $field ??= $this->getRouteKeyName();
        $query = $this->newQuery()->where($field, $value);

        if (
            in_array(SoftDeletes::class, class_uses_recursive(static::class), true)
            && $this->activeAdminMayResolveTrashed()
        ) {
            $query->withTrashed();
        }

        return $query->first();
    }

    private function activeAdminMayResolveTrashed(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isAdmin() && $user->is_active;
    }
}
