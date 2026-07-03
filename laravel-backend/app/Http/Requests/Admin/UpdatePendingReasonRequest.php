<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePendingReasonRequest extends FormRequest
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
            'pending_reason' => ['required', Rule::in(Order::PENDING_REASONS)],
            // Only used with the "other" reason; capped so the note stays a short
            // operational memo, not free-form storage.
            'pending_note' => ['nullable', 'string', 'max:500', 'required_if:pending_reason,other'],
        ];
    }
}
