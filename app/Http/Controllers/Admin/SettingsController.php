<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Settings', [
            'app' => [
                'name' => config('app.name'),
                'url' => config('app.url'),
                'env' => app()->environment(),
                'debug' => (bool) config('app.debug'),
                'locale' => config('app.locale'),
                'timezone' => config('app.timezone'),
            ],
            'security' => [
                'csp_enabled' => (bool) config('security.csp.enabled'),
                'csp_report_only' => (bool) config('security.csp.report_only'),
            ],
            'update_repo' => config('oranotes.update.repository'),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
        ]);

        if (isset($data['name'])) {
            config(['app.name' => $data['name']]);
        }

        $logger->log(ActivityAction::SettingChanged, $request->user(), null, [
            'keys' => array_keys($data),
        ]);

        return back()->with('status', 'Paramètres enregistrés pour le processus courant. Pour la production, modifier le .env.');
    }
}
