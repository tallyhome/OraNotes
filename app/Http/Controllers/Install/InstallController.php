<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\Install\EnvironmentDetector;
use App\Services\Install\Installer;
use App\Services\Install\InstallState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InstallController extends Controller
{
    public function __construct(
        private InstallState $state,
        private EnvironmentDetector $detector,
        private Installer $installer,
    ) {}

    public function welcome(): Response|RedirectResponse
    {
        if (! $this->state->canRunWizard()) {
            return redirect('/');
        }

        return Inertia::render('Install/Welcome', [
            'environment' => $this->detector->detect(),
        ]);
    }

    public function checks(): Response|RedirectResponse
    {
        if (! $this->state->canRunWizard()) {
            return redirect('/');
        }

        $requirements = $this->detector->requirements();

        return Inertia::render('Install/Checks', [
            'environment' => $this->detector->detect(),
            'requirements' => $requirements,
            'ready' => collect($requirements)->every(fn ($c) => $c['ok']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->state->canRunWizard()) {
            abort(403, 'Installation déjà verrouillée.');
        }

        $data = $request->validate([
            'app.name' => ['required', 'string', 'max:80'],
            'app.url' => ['required', 'url', 'max:255'],
            'app.env' => ['required', 'in:production,local'],
            'app.locale' => ['required', 'in:fr,en'],
            'database.driver' => ['required', 'in:sqlite,mysql'],
            'database.host' => ['nullable', 'string', 'max:255'],
            'database.port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database.database' => ['required', 'string', 'max:255'],
            'database.username' => ['nullable', 'string', 'max:255'],
            'database.password' => ['nullable', 'string', 'max:255'],
            'admin.name' => ['required', 'string', 'max:255'],
            'admin.email' => ['required', 'email', 'max:255'],
            'admin.password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
        ]);

        $this->installer->install($data);

        return redirect()->route('install.done');
    }

    public function done(): Response
    {
        return Inertia::render('Install/Done', [
            'url' => config('app.url'),
            'version' => config('oranotes.version'),
        ]);
    }
}
