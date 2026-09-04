<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\System\HealthService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SystemController extends Controller
{
    public function __invoke(HealthService $health): Response
    {
        return Inertia::render('Admin/System', [
            'health' => $health->summary(),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
            'env' => app()->environment(),
            'debug' => (bool) config('app.debug'),
            'url' => config('app.url'),
            'db' => DB::getDriverName(),
            'cache' => config('cache.default'),
            'queue' => config('queue.default'),
            'session' => config('session.driver'),
            'version' => config('oranotes.version'),
            'oraeditor' => config('oranotes.ora_editor_version'),
            'node_runtime_required' => false,
        ]);
    }
}
