<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Order;
use App\Support\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Correct the customer's details and the order's delivery address / zone.
 *
 * Note the split: the ADDRESS lives on the order (`orders.address`), so editing it
 * touches only this order. The name/mobile/email live on the shared `customers`
 * row, so editing those updates that person everywhere — which is right (it is the
 * same human), but the UI says so out loud.
 */
class UpdateOrderCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $order = $this->route('order');
        $customerId = $order instanceof Order ? $order->customer_id : null;

        return [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile' => [
                'required', 'string', 'max:20',
                // Compared against the NORMALIZED value (see prepareForValidation),
                // so 01712345678 and +8801712345678 cannot become two customers.
                Rule::unique('customers', 'mobile')->ignore($customerId),
            ],
            'address' => ['required', 'string', 'max:1000'],
            'shipping_zone_id' => [
                'nullable', 'integer',
                Rule::exists('shipping_zones', 'id')->where('status', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mobile.unique' => 'That mobile number already belongs to another customer.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $mobile = $this->input('mobile');

        if (! is_string($mobile) || $mobile === '') {
            return;
        }

        try {
            $this->merge(['mobile' => MobileNumber::normalize($mobile)]);
        } catch (Throwable) {
            // Not a valid BD number — leave it as typed so the rules below produce a
            // validation error instead of a 500.
        }
    }
}
