<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Support\MobileNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public OTP verification. On success the controller auto-registers the
 * customer (by mobile) and issues a Sanctum token scoped to the 'customer'
 * ability. An optional name is captured on first login.
 */
class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mobile' => [
                'required', 'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! MobileNumber::isValid($value)) {
                        $fail('Please enter a valid Bangladeshi mobile number.');
                    }
                },
            ],
            'code' => ['required', 'string', 'digits:6'],
            'name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
