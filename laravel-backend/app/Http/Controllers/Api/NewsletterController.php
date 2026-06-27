<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreNewsletterRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;

class NewsletterController extends Controller
{
    /**
     * Capture a storefront newsletter subscription. Idempotent: an already
     * subscribed email is accepted without creating a duplicate row.
     */
    public function store(StoreNewsletterRequest $request): JsonResponse
    {
        $email = (string) $request->validated('email');

        $subscriber = NewsletterSubscriber::query()->firstOrCreate(
            ['email' => $email],
            ['source' => 'storefront'],
        );

        return response()->json(
            ['data' => ['subscribed' => true]],
            $subscriber->wasRecentlyCreated ? 201 : 200,
        );
    }
}
