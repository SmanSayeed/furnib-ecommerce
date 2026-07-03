<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrderBulkStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('orders.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['all_matching' => $this->boolean('all_matching')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Order::STATUSES)],
            'all_matching' => ['boolean'],
            'ids' => ['array', 'required_if:all_matching,false'],
            'ids.*' => ['integer'],
            // List filters for "all matching" (kept separate from the target
            // status so the two never collide on the `status` key).
            'filters' => ['array'],
        ];
    }
}
