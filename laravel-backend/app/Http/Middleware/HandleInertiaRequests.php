<?php

namespace App\Http\Middleware;

use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'branding' => $this->branding(),
            'auth' => [
                'user' => $request->user(),
                'permissions' => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Public branding (site name, tagline, resolved logo URLs) shared with every
     * Inertia page so unauthenticated screens like login can render the brand.
     * Falls back to config + null logos if settings are unavailable (e.g. before
     * migrations run), so a missing settings table never breaks page rendering.
     *
     * @return array{site_name:string,tagline:?string,logo_light:?string,logo_dark:?string}
     */
    private function branding(): array
    {
        $fallback = [
            'site_name' => (string) config('app.name'),
            'tagline' => null,
            'logo_light' => null,
            'logo_dark' => null,
        ];

        try {
            $settings = app(SettingsService::class)->toArray('branding');
            $storage = app(StorageRepository::class);

            $url = static function (mixed $path) use ($storage): ?string {
                if (! is_string($path) || $path === '') {
                    return null;
                }
                if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                    return $path;
                }

                return $storage->url($path);
            };

            return [
                'site_name' => ($settings['site_name'] ?? null) ?: $fallback['site_name'],
                'tagline' => $settings['tagline'] ?? null,
                'logo_light' => $url($settings['logo_light'] ?? null),
                'logo_dark' => $url($settings['logo_dark'] ?? null),
            ];
        } catch (Throwable) {
            return $fallback;
        }
    }
}
