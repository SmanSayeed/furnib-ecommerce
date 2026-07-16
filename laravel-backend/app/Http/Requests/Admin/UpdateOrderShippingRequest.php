<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a manual delivery-charge override on an order. Entered in whole taka
 * and normalized to paisa. Gated by orders.manage.
 */
class UpdateOrderShippingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('shipping_cost') !== null && is_numeric($this->input('shipping_cost'))) {
            $this->merge([
                'shipping_minor' => (int) round((float) $this->input('shipping_cost')) * 100,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shipping_cost' => ['required', 'numeric', 'min:0'],
            'shipping_minor' => ['required', 'integer', 'min:0'],
        ];
    }
}
