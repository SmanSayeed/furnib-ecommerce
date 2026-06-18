<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly StorageRepository $storage,
    ) {}

    /**
     * Public, non-secret branding settings for the storefront.
     */
    public function index(): JsonResponse
    {
        $b = $this->settings->toArray('branding');

        return response()->json([
            'data' => [
                'site_name' => $b['site_name'] ?? null,
                'tagline' => $b['tagline'] ?? null,
                'whatsapp' => $b['whatsapp'] ?? null,
                'contact' => [
                    'phone' => $b['contact_phone'] ?? null,
                    'email' => $b['contact_email'] ?? null,
                    'address' => $b['contact_address'] ?? null,
                ],
                'logo_light' => $this->url($b['logo_light'] ?? null),
                'logo_dark' => $this->url($b['logo_dark'] ?? null),
                'favicon' => $this->url($b['favicon'] ?? null),
                'banners' => array_values(array_filter([
                    $this->url($b['banner_1'] ?? null),
                    $this->url($b['banner_2'] ?? null),
                ])),
            ],
        ]);
    }

    private function url(mixed $path): ?string
    {
        if (! is_string($path) || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->storage->url($path);
    }
}
