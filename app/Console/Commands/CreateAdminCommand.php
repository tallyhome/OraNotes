<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\WorkspaceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'oranotes:create-admin {email} {name=Admin} {--password=password}';

    protected $description = 'Créer un compte administrateur OraNotes';

    public function handle(WorkspaceService $workspaces): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->fill([
            'name' => (string) $this->argument('name'),
            'password' => Hash::make((string) $this->option('password')),
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->save();

        if ($user->workspaces()->count() === 0) {
            $workspaces->createDefaultFor($user);
        }

        $this->info('Administrateur prêt : '.$user->email);

        return self::SUCCESS;
    }
}
