<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inertia admin form for products. Image fields are uploaded files here; the
 * controller optimizes + stores them and persists the resulting paths. SVG is
 * disallowed (stored-XSS risk) — raster only. Gallery ordering/deletion is
 * driven by `gallery_layout` (JSON) referencing kept image ids and indexes
 * into the freshly uploaded `gallery_new[]` files.
 */
class ProductFormRequest extends FormRequest
{
    private const IMAGE_MIMES = 'png,jpg,jpeg,webp,avif';

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
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('products', 'slug')->ignore($this->route('product')),
            ],
            'sku' => [
                'nullable', 'string', 'max:100',
                Rule::unique('products', 'sku')->ignore($this->route('product')),
            ],
            'details' => ['nullable', 'string'],
            'product_video' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'is_advance_payment' => ['boolean'],
            'advance_payment_type' => ['nullable', Rule::in(['full', 'partial'])],
            'partial_amount_type' => ['nullable', Rule::in(['percentage', 'amount', 'shipping'])],
            'partial_amount' => ['nullable', 'integer', 'min:0'],
            'is_featured' => ['boolean'],
            'is_new' => ['boolean'],
            'position_order' => ['nullable', 'integer', 'min:0'],
            'product_status' => ['required', Rule::in(['draft', 'published', 'disabled'])],
            'stock_amount' => ['nullable', 'integer', 'min:0'],
            'stock_status' => ['boolean'],
            'shipping_charge_allowed' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],

            'main_image' => ['nullable', 'file', 'mimes:'.self::IMAGE_MIMES, 'max:20480'],
            'social_thumbnail_image' => ['nullable', 'file', 'mimes:'.self::IMAGE_MIMES, 'max:20480'],
            'gallery_new' => ['nullable', 'array', 'max:6'],
            'gallery_new.*' => ['file', 'mimes:'.self::IMAGE_MIMES, 'max:20480'],
            'gallery_layout' => ['nullable', 'string'],

            // Optional per-zone extra delivery charge (display amount, ৳). Only
            // active zones are accepted; one entry per zone (no duplicates).
            'shipping_charges' => ['nullable', 'array'],
            'shipping_charges.*.shipping_zone_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('shipping_zones', 'id')->where('status', true),
            ],
            'shipping_charges.*.extra_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'discount_price.lt' => 'The discounted price must be lower than the price. Clear or lower the discount before saving.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $isAdvance = $this->boolean('is_advance_payment');
        $advanceType = $this->input('advance_payment_type');

        $this->merge([
            'is_advance_payment' => $isAdvance,
            'is_featured' => $this->boolean('is_featured'),
            'is_new' => $this->boolean('is_new'),
            'stock_status' => $this->boolean('stock_status'),
            // Defaults to true when the field is absent (e.g. legacy clients),
            // matching the column default and "charge shipping" being the norm.
            'shipping_charge_allowed' => $this->has('shipping_charge_allowed')
                ? $this->boolean('shipping_charge_allowed')
                : true,
            'position_order' => $this->input('position_order', 0),
            'stock_amount' => $this->input('stock_amount', 0),
        ]);

        // Partial fields are only meaningful for a "partial" advance. When advance
        // payment is off, or the advance type is "full", null them out so stale
        // partial values are never persisted.
        if (! $isAdvance || $advanceType === 'full') {
            $this->merge([
                'partial_amount_type' => null,
                'partial_amount' => null,
            ]);

            return;
        }

        // A "fixed amount" advance is entered in whole taka by the admin but is
        // stored (and consumed by the storefront) as paisa — convert × 100 so the
        // units match Price/discount. "percentage" keeps its raw percent value;
        // "shipping" carries no amount.
        if ($this->input('partial_amount_type') === 'amount' && $this->input('partial_amount') !== null) {
            $this->merge([
                'partial_amount' => (int) round((float) $this->input('partial_amount')) * 100,
            ]);
        }
    }
}
