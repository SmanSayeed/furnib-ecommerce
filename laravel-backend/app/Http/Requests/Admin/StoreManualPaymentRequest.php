<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an admin manual payment adjustment. Amount is entered in whole taka
 * (no poysha) and normalized to paisa; a note is mandatory so every money-in /
 * money-out entry is explained. Gated by orders.manage.
 */
class StoreManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Whole taka in → paisa. Reject fractions up front by flooring the taka
        // first (the storefront/admin never deal in poysha).
        if ($this->input('amount') !== null && is_numeric($this->input('amount'))) {
            $this->merge([
                'amount_minor' => (int) round((float) $this->input('amount')) * 100,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'direction' => ['required', Rule::in(Payment::DIRECTIONS)],
            'note' => ['required', 'string', 'max:255'],
        ];
    }
}
