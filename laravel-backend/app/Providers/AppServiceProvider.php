<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->configureAuditTrail();
    }

    /**
     * Rate limiters for sensitive public endpoints.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(20)->by((string) $request->ip()));
    }

    /**
     * Attach the request IP to every audit entry when available.
     */
    protected function configureAuditTrail(): void
    {
        Activity::saving(function (Activity $activity): void {
            if (! $activity->properties->has('ip')) {
                $ip = request()->ip();

                if ($ip !== null) {
                    $activity->properties = $activity->properties->put('ip', $ip);
                }
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
