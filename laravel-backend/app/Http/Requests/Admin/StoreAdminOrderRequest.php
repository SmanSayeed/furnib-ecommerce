<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use App\Support\MobileNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Validates an admin-created order. The customer is resolved find-or-create by
 * mobile (an existing buyer is reused, not rejected), so there is no uniqueness
 * rule here. Every money field is entered in whole taka and normalized to paisa;
 * the staff-only overrides (unit price, discount, shipping) are validated here but
 * only take effect because the controller marks the order `source = admin`. Gated
 * by orders.manage.
 */
class StoreAdminOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Normalize the mobile so 01712345678 and +8801712345678 resolve to the
        // same customer.
        $mobile = $this->input('customer.mobile');
        if (is_string($mobile) && $mobile !== '') {
            try {
                $customer = (array) $this->input('customer', []);
                $customer['mobile'] = MobileNumber::normalize($mobile);
                $this->merge(['customer' => $customer]);
            } catch (Throwable) {
                // leave as typed → the rule below produces a clean error, not a 500
            }
        }

        // Whole taka → paisa for every money field (floor fractions).
        $toMinor = static fn (mixed $v): ?int => is_numeric($v)
            ? (int) round((float) $v) * 100
            : null;

        $items = $this->input('items');
        if (is_array($items)) {
            $items = array_map(static function ($item) use ($toMinor) {
                if (is_array($item) && isset($item['unit_price']) && is_numeric($item['unit_price'])) {
                    $item['unit_price_minor'] = $toMinor($item['unit_price']);
                }

                return $item;
            }, $items);
            $this->merge(['items' => $items]);
        }

        $this->merge([
            'discount_minor' => $toMinor($this->input('discount')),
            'shipping_override_minor' => $toMinor($this->input('shipping_override')),
            'advance_paid_minor' => $toMinor($this->input('advance_paid')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.mobile' => ['required', 'string', 'max:20'],

            'address' => ['required', 'string', 'max:1000'],
            'shipping_zone_id' => [
                'nullable', 'integer',
                Rule::exists('shipping_zones', 'id')->where('status', true),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_price_minor' => ['nullable', 'integer', 'min:0'],

            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_minor' => ['nullable', 'integer', 'min:0'],
            'discount_note' => ['nullable', 'string', 'max:500'],

            'shipping_override' => ['nullable', 'numeric', 'min:0'],
            'shipping_override_minor' => ['nullable', 'integer', 'min:0'],

            'advance_paid' => ['nullable', 'numeric', 'min:0'],
            'advance_paid_minor' => ['nullable', 'integer', 'min:0'],
            // When an advance is collected up front, capture HOW (bKash/Nagad/…) and
            // its transaction id / reference in the note.
            'advance_method' => [
                Rule::requiredIf(fn (): bool => (int) $this->input('advance_paid_minor') > 0),
                'nullable', Rule::in(Payment::METHODS),
            ],
            'advance_note' => ['nullable', 'string', 'max:255'],

            'confirm' => ['boolean'],
            'send_sms' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer.mobile.required' => 'A customer mobile number is required.',
            'items.required' => 'Add at least one product to the order.',
        ];
    }
}
