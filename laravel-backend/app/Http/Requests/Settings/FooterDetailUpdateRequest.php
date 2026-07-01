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

    protected function prepareForValidation(): void
    {
        // Checkbox toggles arrive as '1'/'0'/'on' (or absent) — normalise to
        // real booleans so the `boolean` rule and `$request->boolean()` agree.
        $this->merge([
            'member_of_enabled' => $this->boolean('member_of_enabled'),
            'delivery_partner_enabled' => $this->boolean('delivery_partner_enabled'),
        ]);
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

            // Footer "opening hours" line, e.g. "Every Day 9 AM To 2 AM".
            'contact_hours' => ['nullable', 'string', 'max:200'],

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

            // "Member's Of" trust badge — toggle, heading, image + optional link.
            // URL must be absolute http(s) or a site-relative path (never javascript:).
            'member_of_enabled' => ['boolean'],
            'member_of_heading' => ['nullable', 'string', 'max:60'],
            'member_of_url' => ['nullable', 'string', 'max:200', 'regex:#^(https?://|/)#i'],
            'member_of_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:20480'],

            // "Delivery Partner" trust badge — same shape.
            'delivery_partner_enabled' => ['boolean'],
            'delivery_partner_heading' => ['nullable', 'string', 'max:60'],
            'delivery_partner_url' => ['nullable', 'string', 'max:200', 'regex:#^(https?://|/)#i'],
            'delivery_partner_image' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:20480'],
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
            'member_of_url.regex' => 'Link must be an https:// URL or a path starting with /.',
            'member_of_image.mimes' => "Member's Of image must be PNG, JPG or WebP (SVG is not allowed).",
            'delivery_partner_url.regex' => 'Link must be an https:// URL or a path starting with /.',
            'delivery_partner_image.mimes' => 'Delivery Partner image must be PNG, JPG or WebP (SVG is not allowed).',
        ];
    }
}
