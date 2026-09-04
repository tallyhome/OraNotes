<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | Progressive strategy: Report-Only by default so Inertia, Vite, Ziggy,
    | OraEditor and uploads keep working while violations are observed.
    | Set CSP_REPORT_ONLY=false to enforce after reviewing reports.
    |
    | Exceptions (documented, minimized):
    | - style-src 'unsafe-inline' : Tailwind utilities, OraEditor, Inertia head
    | - script-src 'unsafe-inline' : Ziggy @routes, Inertia page bootstrap
    | - unsafe-eval is NOT included (Vite production + OraEditor IIFE do not need it)
    | - fonts.bunny.net : layout fonts
    | - frame-src YouTube/Vimeo : OraEditor embeds
    |
    */

    'csp' => [
        'enabled' => env('CSP_ENABLED', true),
        'report_only' => env('CSP_REPORT_ONLY', true),
        'report_uri' => env('CSP_REPORT_URI'),
    ],
];
