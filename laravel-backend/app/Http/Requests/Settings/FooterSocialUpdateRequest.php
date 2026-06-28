<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class FooterSocialUpdateRequest extends FormRequest
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
        // Social links must be absolute http(s) URLs — blocks javascript:/data:
        // hrefs that would enable stored XSS in the storefront footer.
        return [
            'social_facebook' => ['nullable', 'string', 'max:200', 'regex:#^https?://#i'],
            'social_instagram' => ['nullable', 'string', 'max:200', 'regex:#^https?://#i'],
            'social_youtube' => ['nullable', 'string', 'max:200', 'regex:#^https?://#i'],
            'social_linkedin' => ['nullable', 'string', 'max:200', 'regex:#^https?://#i'],
            'social_x' => ['nullable', 'string', 'max:200', 'regex:#^https?://#i'],
            'social_pinterest' => ['nullable', 'string', 'max:200', 'regex:#^https?://#i'],
            'social_tiktok' => ['nullable', 'string', 'max:200', 'regex:#^https?://#i'],
            'social_facebook_enabled' => ['nullable', 'boolean'],
            'social_instagram_enabled' => ['nullable', 'boolean'],
            'social_youtube_enabled' => ['nullable', 'boolean'],
            'social_linkedin_enabled' => ['nullable', 'boolean'],
            'social_x_enabled' => ['nullable', 'boolean'],
            'social_pinterest_enabled' => ['nullable', 'boolean'],
            'social_tiktok_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'social_facebook.regex' => 'Link must be a full https:// URL.',
            'social_instagram.regex' => 'Link must be a full https:// URL.',
            'social_youtube.regex' => 'Link must be a full https:// URL.',
            'social_linkedin.regex' => 'Link must be a full https:// URL.',
            'social_x.regex' => 'Link must be a full https:// URL.',
            'social_pinterest.regex' => 'Link must be a full https:// URL.',
            'social_tiktok.regex' => 'Link must be a full https:// URL.',
        ];
    }
}
