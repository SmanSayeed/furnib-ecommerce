<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureAccountSecured;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Dev\ErrorLogger;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind EasyPanel's Traefik reverse proxy AND Cloudflare. Trust the
        // private Docker networks (Traefik) so X-Forwarded-Proto/Host yield the
        // real https://admin.furnib.com scheme + host (signed-URL validation),
        // and trust Cloudflare's published edge ranges so $request->ip() resolves
        // to the real visitor instead of a Cloudflare edge IP (e.g. 172.69.x).
        // Specific ranges only — never '*' — so X-Forwarded-For can't be spoofed
        // by a client reaching the origin directly.
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            // Cloudflare IPv4 — https://www.cloudflare.com/ips-v4
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            // Cloudflare IPv6 — https://www.cloudflare.com/ips-v6
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ]);

        // _fbp/_fbc (Meta) and _ttp/ttclid (TikTok) are first-party, non-secret
        // attribution cookies set by the pixels — they are not Laravel-issued, so
        // they must bypass cookie encryption or decryption fails and the value is
        // read as null.
        $middleware->encryptCookies(except: [
            'appearance', 'sidebar_state', '_fbp', '_fbc', '_ttp', 'ttclid',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
            EnsureUserActive::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'account.secured' => EnsureAccountSecured::class,
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Persist exceptions to the DB so the owner can review them from the
        // developer console even in production (logs go to stderr there).
        $exceptions->report(function (Throwable $e): void {
            app(ErrorLogger::class)->record($e, request());
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return ApiExceptionRenderer::render($e);
            }

            return null;
        });
    })->create();
