<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalog;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $product = $this->route('product');
        $productId = $product instanceof Product ? $product->id : null;

        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($productId)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($productId)],
            'details' => ['nullable', 'string'],
            'product_video' => ['nullable', 'string', 'max:255'],
            'main_image' => ['nullable', 'string'],
            'social_thumbnail_image' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            // Must stay strictly below the price, exactly as StoreProductRequest
            // and Admin\ProductFormRequest already require. Without this, the API
            // could persist a "discount" that is higher than the price.
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'is_advance_payment' => ['boolean'],
            'advance_payment_type' => ['nullable', Rule::in(['full', 'partial'])],
            'partial_amount_type' => ['nullable', Rule::in(['percentage', 'amount'])],
            'partial_amount' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
            'position_order' => ['integer', 'min:0'],
            'product_status' => ['sometimes', Rule::in(['draft', 'published', 'disabled'])],
            'stock_amount' => ['integer', 'min:0'],
            'stock_status' => ['boolean'],
            'shipping_charge_allowed' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image' => ['nullable', 'string'],
        ];
    }

    /**
     * This is a partial update, so `price` may be absent while `discount_price`
     * is being changed. `lt:price` needs something to compare against, so fall
     * back to the product's stored price (as a display amount, matching the
     * incoming payload's units).
     */
    protected function prepareForValidation(): void
    {
        $product = $this->route('product');

        if ($product instanceof Product && ! $this->has('price')) {
            $this->merge(['price' => $product->price->toDisplay()]);
        }
    }
}
