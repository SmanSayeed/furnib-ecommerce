<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class FooterDetailUpdateRequest extends FormRequest
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
        return [
            // White/transparent footer logo. SVG disallowed (stored-XSS risk).
            'logo_footer' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:20480'],

            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_address' => ['nullable', 'string', 'max:200'],

            // Footer quick links (label + url). URL must be absolute http(s) or a
            // site-relative path starting with "/" — never a javascript: href.
            'about_links' => ['nullable', 'array', 'max:12'],
            'about_links.*.label' => ['required', 'string', 'max:60'],
            'about_links.*.url' => ['required', 'string', 'max:200', 'regex:#^(https?://|/)#i'],

            // Payment-gateway compliance fields (owner fills the real values).
            'trade_license_no' => ['nullable', 'string', 'max:100'],
            'registered_address' => ['nullable', 'string', 'max:500'],
            'delivery_inside_dhaka' => ['nullable', 'string', 'max:200'],
            'delivery_outside_dhaka' => ['nullable', 'string', 'max:200'],

            // Gateway "payment methods" banner. SVG disallowed (stored-XSS risk).
            'payment_banner' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:20480'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo_footer.mimes' => 'Footer logo must be PNG, JPG or WebP (SVG is not allowed).',
            'about_links.*.url.regex' => 'Link must be an https:// URL or a path starting with /.',
            'payment_banner.mimes' => 'Payment banner must be PNG, JPG or WebP (SVG is not allowed).',
        ];
    }
}
