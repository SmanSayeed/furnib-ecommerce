<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')],
            'details' => ['nullable', 'string'],
            'product_video' => ['nullable', 'string', 'max:255'],
            'main_image' => ['nullable', 'string'],
            'social_thumbnail_image' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'is_advance_payment' => ['boolean'],
            'advance_payment_type' => ['nullable', Rule::in(['full', 'partial'])],
            'partial_amount_type' => ['nullable', Rule::in(['percentage', 'amount'])],
            'partial_amount' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
            'position_order' => ['integer', 'min:0'],
            'product_status' => ['required', Rule::in(['draft', 'published', 'disabled'])],
            'stock_amount' => ['integer', 'min:0'],
            'stock_status' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string'],
        ];
    }
}
