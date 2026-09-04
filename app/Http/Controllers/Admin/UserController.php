<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->when($request->string('q')->isNotEmpty(), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
            ->when($request->filled('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at?->toIso8601String(),
            ]),
            'filters' => $request->only(['q', 'role', 'active']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (($data['role'] ?? null) === UserRole::User->value && $user->isAdmin()) {
            $admins = User::query()->where('role', UserRole::Admin)->count();
            abort_if($admins <= 1, 422, 'Impossible de retirer le dernier administrateur.');
        }

        $user->fill($data);
        $user->save();

        return back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas supprimer votre propre compte ici.');
        $user->delete();

        return back();
    }
}
