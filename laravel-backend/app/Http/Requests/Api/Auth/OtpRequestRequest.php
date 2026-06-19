<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Support\MobileNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public OTP request. Only a valid BD mobile is needed; the code is generated
 * server-side and delivered by SMS — never returned in the response.
 */
class OtpRequestRequest extends FormRequest
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
        ];
    }
}
