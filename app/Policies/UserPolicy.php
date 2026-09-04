<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isAdmin() || $actor->is($target);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isAdmin() || $actor->is($target);
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
