<?php

use App\Http\Middleware\EnsureWritesAllowed;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdleTimeoutMiddleware;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UpdateLastActiveMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the ALB to set X-Forwarded-Proto / X-Forwarded-For /
        // X-Forwarded-Host / X-Forwarded-Port. Without this, Laravel
        // sees HTTP on the ECS task socket and generates http:// asset
        // URLs on HTTPS pages - browsers block them as mixed content.
        // Safe because the app is only reachable through the ALB (the
        // task SG only accepts traffic from the ALB SG).
        // Trust only in-VPC proxies (the ALB), not '*', so a client-supplied
        // X-Forwarded-For can't spoof the source IP that rate-limiting and audit
        // logging key on. Defaults to the RFC1918 private ranges any VPC sits in;
        // pin to the exact VPC/ALB CIDR via TRUSTED_PROXIES in production.
        $middleware->trustProxies(at: array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16')),
        ))));

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            IdleTimeoutMiddleware::class,
            UpdateLastActiveMiddleware::class,
            EnsureWritesAllowed::class,
            SecurityHeaders::class,
        ]);

        // External webhooks (server-to-server callbacks) can't carry a CSRF
        // token, so each exempt route is listed explicitly and is responsible
        // for its own verification (DocuSign Connect verifies a shared-secret
        // signature header). Listing routes explicitly - rather than a
        // `webhooks/*` wildcard - means a new webhook route can't silently
        // inherit the CSRF exemption without a deliberate addition here.
        $middleware->validateCsrfTokens(except: ['webhooks/docusign']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Fail-secure error handling (STIG APSC-DV-002310 / NIST SI-11): in
        // production (APP_DEBUG=false) Laravel renders generic error pages, logs
        // the failure server-side, and never bypasses auth/authorization
        // middleware on an exception. Additionally scrub sensitive fields from
        // any flashed input so a validation/exception round-trip can't echo
        // secrets back to the client.
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
            'token',
            '_token',
        ]);

        // Branded, self-contained error pages for production responses. The
        // Inertia component (pages/errors/error) carries its own copy and
        // styling so it renders even when the failure is the database - it
        // never surfaces exception detail to the user (that's logged only).
        // Debug mode keeps Laravel's detailed debugger; JSON + webhook clients
        // keep machine responses.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            $status = $response->getStatusCode();

            if (config('app.debug') || $request->expectsJson() || $request->is('webhooks/*')) {
                return $response;
            }

            if (in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return Inertia::render('errors/error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
