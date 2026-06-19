<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;

class MaintenanceController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Public maintenance flag for the storefront to render a maintenance page.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'enabled' => (bool) $this->settings->get('maintenance', 'enabled', false),
                'message' => $this->settings->get('maintenance', 'message'),
            ],
        ]);
    }
}
