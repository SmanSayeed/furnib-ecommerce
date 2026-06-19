<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TrackVisitRequest;
use App\Models\Visitor;
use App\Support\Utm;
use Illuminate\Http\JsonResponse;

class TrackingController extends Controller
{
    /**
     * Record a storefront pageview. IP + user agent come from the request;
     * UTM falls back to parsing the supplied URL when explicit fields are absent.
     */
    public function store(TrackVisitRequest $request): JsonResponse
    {
        $data = $request->validated();
        $utm = Utm::parse($data['url'] ?? $data['path'] ?? null);

        Visitor::query()->create([
            'session_id' => $data['session_id'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'path' => $data['path'],
            'referrer' => $data['referrer'] ?? null,
            'utm_source' => $data['utm_source'] ?? $utm['utm_source'],
            'utm_medium' => $data['utm_medium'] ?? $utm['utm_medium'],
            'utm_campaign' => $data['utm_campaign'] ?? $utm['utm_campaign'],
        ]);

        return response()->json(['recorded' => true]);
    }
}
