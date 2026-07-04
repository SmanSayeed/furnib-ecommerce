<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sms;

use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Services\Settings\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Receives Automas SMS delivery reports (DLR). Automas is configured with two
 * push URLs — a success and a fail URL — each carrying our secret token, so a
 * request that doesn't know the token cannot spoof delivery state. We only ever
 * advance an EXISTING log matched by the provider message id; we never create
 * rows and never leak whether a message was found.
 */
class DlrController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Request $request, string $token, string $outcome): JsonResponse
    {
        $expected = (string) ($this->settings->get('sms', 'dlr_token') ?? '');

        // Constant-time compare; a missing/unmatched token is a generic 404 so
        // the endpoint's existence + token are not probeable.
        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        $messageId = $this->messageId($request);

        if ($messageId !== '') {
            $log = NotificationLog::query()
                ->where('channel', 'sms')
                ->where('provider_message_id', $messageId)
                ->first();

            if ($log !== null) {
                $delivered = $outcome === 'success';

                $log->update([
                    'status' => $delivered
                        ? NotificationLog::STATUS_DELIVERED
                        : NotificationLog::STATUS_UNDELIVERED,
                    'delivered_at' => $delivered ? Date::now() : null,
                    'error' => $delivered ? null : $this->reason($request),
                ]);
            }
        }

        // Always 200 — never reveal whether the id matched.
        return response()->json(['ok' => true]);
    }

    /** Read the provider message id under any of Automas' likely param names. */
    private function messageId(Request $request): string
    {
        foreach (['id', 'sid', 'messageid', 'message_id', 'smsid', 'msgid'] as $key) {
            $value = $request->input($key);

            if (is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    private function reason(Request $request): string
    {
        $reason = $request->input('error') ?? $request->input('reason') ?? $request->input('status');

        return is_scalar($reason) ? Str::limit((string) $reason, 180) : 'Reported undelivered by the operator.';
    }
}
