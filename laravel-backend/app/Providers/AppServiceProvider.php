<?php

namespace App\Providers;

use App\Support\Mail\MailConfigurator;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;
use Throwable;

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
        $this->configureUrls();
        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->configureAuditTrail();
        $this->configureMail();
    }

    /**
     * Force URL generation to the configured public origin + HTTPS. Needed
     * because the storefront fetches the API over the internal Docker host
     * (furnib_backend); without this, signed/absolute URLs (e.g. the invoice
     * download link) would leak that internal host instead of admin.furnib.com.
     */
    protected function configureUrls(): void
    {
        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceScheme('https');
            URL::forceRootUrl($appUrl);
        }
    }

    /**
     * Apply the dynamic SMTP settings (from encrypted DB settings) to the mail
     * transport. Skipped in tests (which use the array mailer). Wrapped so a
     * missing settings table during install/migrate is non-fatal.
     */
    protected function configureMail(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        try {
            $this->app->make(MailConfigurator::class)->apply();
        } catch (Throwable) {
            // Settings table not ready yet (e.g. before migrations) — ignore.
        }
    }

    /**
     * Rate limiters for sensitive public endpoints.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('otp', fn (Request $request) => Limit::perMinute(5)->by((string) $request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by((string) $request->ip()));
        RateLimiter::for('orders', fn (Request $request) => Limit::perMinute(20)->by((string) $request->ip()));
        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));
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
