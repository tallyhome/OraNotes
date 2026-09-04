<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SecurityController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Security', [
            'csp' => config('security.csp'),
            'debug' => (bool) config('app.debug'),
            'session' => [
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
                'secure' => config('session.secure'),
                'http_only' => config('session.http_only'),
            ],
            'recommendations' => [
                'APP_DEBUG=false en production',
                'HTTPS (APP_URL=https://…)',
                'CSP_REPORT_ONLY=false après revue des rapports',
                'Ne jamais exposer .env ni storage/logs',
                'Les mises à jour n’acceptent que GitHub Releases officiel',
            ],
        ]);
    }
}
