<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class SiteSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already gated by `permission:settings.manage`.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // SVG is intentionally disallowed for uploads: it can embed scripts and
        // would be served same-origin, enabling stored XSS. Raster only.
        return [
            'site_name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],

            'logo_light' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:20480'],
            'logo_dark' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:20480'],
            'logo_invoice' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:20480'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'banner_1' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,avif', 'max:20480'],
            'banner_2' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,avif', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'WhatsApp number must contain digits only (with country code, no +).',
            'logo_light.mimes' => 'Logo must be PNG, JPG or WebP (SVG is not allowed).',
            'logo_dark.mimes' => 'Logo must be PNG, JPG or WebP (SVG is not allowed).',
            'logo_invoice.mimes' => 'Logo must be PNG, JPG or WebP (SVG is not allowed).',
            'favicon.mimes' => 'Favicon must be a PNG or ICO file.',
            'banner_1.mimes' => 'Banner must be PNG, JPG, WebP or AVIF (SVG is not allowed).',
            'banner_2.mimes' => 'Banner must be PNG, JPG, WebP or AVIF (SVG is not allowed).',
        ];
    }
}
