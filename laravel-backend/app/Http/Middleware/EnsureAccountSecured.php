<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces an authenticated user to (1) change a bootstrap password and
 * (2) enrol in 2FA when required, before reaching any protected route.
 */
class EnsureAccountSecured
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $passwordUrl = (string) config('rbac.password_change_url');
            $twoFactorUrl = (string) config('rbac.two_factor_setup_url');

            if ($user->must_change_password && ! $request->is(ltrim($passwordUrl, '/'))) {
                return redirect($passwordUrl);
            }

            if ($user->two_factor_required
                && $user->two_factor_confirmed_at === null
                && ! $request->is(ltrim($twoFactorUrl, '/'))) {
                return redirect($twoFactorUrl);
            }
        }

        return $next($request);
    }
}
