<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        $request->attributes->set('cspNonce', $nonce);
        view()->share('cspNonce', $nonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (! config('security.csp.enabled', true)) {
            return $response;
        }

        $policy = $this->policy($request, $nonce);
        $header = config('security.csp.report_only', true)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $policy);

        return $response;
    }

    private function policy(Request $request, string $nonce): string
    {
        $script = ["'self'", "'unsafe-inline'", "'nonce-{$nonce}'"];
        $connect = ["'self'"];
        $style = ["'self'", "'unsafe-inline'", 'https://fonts.bunny.net'];

        if (app()->environment('local')) {
            $script[] = 'http://localhost:5173';
            $script[] = 'http://127.0.0.1:5173';
            $connect[] = 'ws://localhost:5173';
            $connect[] = 'ws://127.0.0.1:5173';
            $connect[] = 'http://localhost:5173';
            $connect[] = 'http://127.0.0.1:5173';
        }

        $connect[] = 'ws:';
        $connect[] = 'wss:';

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: blob: https:",
            'connect-src '.implode(' ', $connect),
            "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com",
            "media-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        $reportUri = config('security.csp.report_uri');
        if (is_string($reportUri) && $reportUri !== '') {
            $directives[] = 'report-uri '.$reportUri;
        }

        return implode('; ', $directives);
    }
}
