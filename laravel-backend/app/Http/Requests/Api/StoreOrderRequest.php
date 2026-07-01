<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use App\Support\MobileNumber;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public storefront checkout. Validates the cart + customer + delivery. Money is
 * NEVER taken from the client — the server recomputes subtotal/shipping/total
 * from the catalog and selected zone in the PlaceOrder action.
 */
class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public checkout
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required', 'integer',
                Rule::exists('products', 'id')->where('product_status', 'published'),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],

            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.mobile' => [
                'required', 'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! MobileNumber::isValid($value)) {
                        $fail('Please enter a valid Bangladeshi mobile number.');
                    }
                },
            ],
            'customer.email' => ['nullable', 'email', 'max:255'],

            'shipping_zone_id' => [
                'nullable', 'integer',
                Rule::exists('shipping_zones', 'id')->where('status', true),
            ],
            'address' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:1000'],

            // Compliance #11 — the customer must agree to the Terms & Conditions,
            // Privacy Policy and Return & Refund Policy. Enforced server-side so a
            // client cannot skip it. Frontend sends `terms_accepted: true`.
            'terms_accepted' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'terms_accepted.accepted' => 'You must agree to the Terms & Conditions, Privacy Policy and Return & Refund Policy.',
        ];
    }
}
