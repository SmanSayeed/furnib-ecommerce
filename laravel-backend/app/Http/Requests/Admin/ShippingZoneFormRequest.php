<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Inertia admin form for shipping zones. `cost` is a display amount (taka);
 * the Money cast stores it as integer minor units (paisa).
 */
class ShippingZoneFormRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'cost' => ['required', 'numeric', 'min:0'],
            'status' => ['boolean'],
            'position_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->boolean('status'),
            'position_order' => $this->input('position_order', 0),
        ]);
    }
}
