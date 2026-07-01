<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Inertia admin form for categories. Image fields are uploaded files here
 * (the controller stores them and passes the resulting paths to the service).
 * SVG is disallowed (stored-XSS risk) — raster only.
 */
class CategoryFormRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('categories', 'slug')->ignore($this->route('category')),
            ],
            'details' => ['nullable', 'string', 'max:2000'],
            'status' => ['boolean'],
            'position_order' => ['nullable', 'integer', 'min:0'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'header_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,avif', 'max:20480'],
            'header_image_mobile' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,avif', 'max:20480'],
            'thumbnail_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,avif', 'max:20480'],
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
