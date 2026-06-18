<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit log. Gated by the `audit.view` permission at the route.
 * There is intentionally no create/update/delete path.
 */
final class AuditLogController
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Activity::query()->latest('id')->limit(100)->get(),
        ]);
    }
}
