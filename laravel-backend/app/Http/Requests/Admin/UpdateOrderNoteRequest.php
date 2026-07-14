<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderNoteRequest extends FormRequest
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
            // Free text, any status. Unlike pending_note this is never cleared by a
            // status change, so it can hold the running story of the order.
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
