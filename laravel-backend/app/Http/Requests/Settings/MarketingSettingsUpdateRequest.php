<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin marketing/analytics settings. Public IDs (GTM/GA4/Pixel/Clarity) reach
 * the client; the Meta CAPI token is a write-only server-side secret.
 */
class MarketingSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('marketing.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gtm_id' => ['nullable', 'string', 'max:64'],
            'ga4_id' => ['nullable', 'string', 'max:64'],
            'fb_pixel_id' => ['nullable', 'string', 'max:64'],
            'clarity_id' => ['nullable', 'string', 'max:64'],
            // QA-only: Events Manager → Test Events code. Server-side only (never
            // exposed to the storefront), not a secret.
            'fb_test_event_code' => ['nullable', 'string', 'max:64'],
            'fb_capi_token' => ['nullable', 'string', 'max:512'],
        ];
    }
}
