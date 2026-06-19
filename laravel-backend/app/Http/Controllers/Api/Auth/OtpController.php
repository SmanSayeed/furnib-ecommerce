<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\OtpRequestRequest;
use App\Http\Requests\Api\Auth\OtpVerifyRequest;
use App\Services\Auth\OtpService;
use App\Services\Orders\CustomerService;
use App\Support\MobileNumber;
use App\Support\Sms\SmsGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Customer mobile OTP auth. `request` issues a code (delivered by SMS, never
 * returned); `verify` exchanges a correct code for a Sanctum token scoped to
 * the 'customer' ability and auto-registers the customer on first login.
 */
class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly SmsGateway $sms,
        private readonly CustomerService $customers,
    ) {}

    public function request(OtpRequestRequest $request): JsonResponse
    {
        $mobile = (string) $request->validated()['mobile'];

        // Throws 429 (TooManyRequestsHttpException) if within the resend cooldown.
        $code = $this->otp->issue($mobile);

        $sent = $this->sms->send(
            MobileNumber::normalize($mobile),
            "Your Furnib verification code is {$code}. It expires in ".OtpService::EXPIRY_MINUTES.' minutes.',
        );

        if (! $sent) {
            return response()->json([
                'error' => [
                    'code' => 'sms_failed',
                    'message' => 'Could not send the verification code. Please try again.',
                ],
            ], 502);
        }

        return response()->json(['sent' => true]);
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! $this->otp->verify((string) $data['mobile'], (string) $data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['The code is invalid or has expired.'],
            ]);
        }

        $customer = $this->customers->findOrCreateByMobile(
            (string) $data['mobile'],
            $data['name'] ?? null,
        );

        $token = $customer->createToken('storefront', ['customer'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'mobile' => $customer->mobile,
                'email' => $customer->email,
            ],
        ]);
    }
}
