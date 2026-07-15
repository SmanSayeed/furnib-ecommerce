<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an admin order-level discount. Amount is entered in whole taka (no
 * poysha) and normalized to paisa; a note is mandatory whenever the discount is
 * non-zero so every reduction is explained. Zero clears an existing discount and
 * needs no note. Gated by orders.manage.
 */
class ApplyOrderDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('discount') !== null && is_numeric($this->input('discount'))) {
            // Whole taka in → paisa. Floor fractions; the admin never deals in poysha.
            $this->merge([
                'discount_minor' => (int) round((float) $this->input('discount')) * 100,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'discount' => ['required', 'numeric', 'min:0'],
            'discount_minor' => ['required', 'integer', 'min:0'],
            'note' => [
                'nullable', 'string', 'max:500',
                Rule::requiredIf(fn (): bool => (int) $this->input('discount_minor') > 0),
            ],
        ];
    }
}
