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
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_address' => ['nullable', 'string', 'max:200'],

            // Footer quick links (label + url). URL must be absolute http(s) or a
            // site-relative path starting with "/" — never a javascript: href.
            'about_links' => ['nullable', 'array', 'max:12'],
            'about_links.*.label' => ['required', 'string', 'max:60'],
            'about_links.*.url' => ['required', 'string', 'max:200', 'regex:#^(https?://|/)#i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'about_links.*.url.regex' => 'Link must be an https:// URL or a path starting with /.',
        ];
    }
}
