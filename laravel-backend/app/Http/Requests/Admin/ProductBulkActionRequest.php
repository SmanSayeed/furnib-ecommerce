<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A bulk edit over many products at once. Selection is either an explicit list
 * of ids (the checkboxes ticked on screen) or "all rows matching the current
 * filters" — in which case the same injection-safe filter whitelist as the list
 * page resolves the target ids server-side (the client never sends the full id
 * set for a 1000-product store). Every action field is validated against the
 * same allow-lists as the single-product form, so a bulk edit can never write a
 * value the normal form would reject.
 */
class ProductBulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'all_matching' => $this->boolean('all_matching'),
            'is_advance_payment' => $this->boolean('is_advance_payment'),
        ]);

        // A "fixed amount" advance is entered in whole taka but stored as paisa,
        // matching the single-product form (and what the storefront consumes).
        if ($this->input('partial_amount_type') === 'amount' && $this->input('partial_amount') !== null) {
            $this->merge([
                'partial_amount' => (int) round((float) $this->input('partial_amount')) * 100,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['advance', 'status', 'category'])],

            // Selection: explicit ids OR every row matching the current filters.
            'all_matching' => ['boolean'],
            'ids' => ['array', 'required_if:all_matching,false'],
            'ids.*' => ['integer'],
            'filters' => ['array'],

            // action=advance. advance_payment_type only matters when turning
            // advance ON; the controller defaults a missing type to "full".
            'is_advance_payment' => ['boolean'],
            'advance_payment_type' => ['nullable', Rule::in(['full', 'partial'])],
            'partial_amount_type' => ['nullable', Rule::in(['percentage', 'amount', 'shipping'])],
            'partial_amount' => ['nullable', 'integer', 'min:0'],

            // action=status
            'product_status' => ['nullable', 'required_if:action,status', Rule::in(['draft', 'published', 'disabled'])],

            // action=category
            'category_id' => ['nullable', 'required_if:action,category', 'integer', 'exists:categories,id'],
        ];
    }
}
