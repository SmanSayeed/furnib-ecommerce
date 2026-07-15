<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Facebook Commerce feed settings. Gated by marketing.manage. The
 * feed password itself is never submitted here — it is generated server-side and
 * shown once; catalog/business IDs and the feed toggle are the editable fields.
 */
class FacebookCommerceUpdateRequest extends FormRequest
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
            'feed_enabled' => ['boolean'],
            'catalog_id' => ['nullable', 'string', 'max:64'],
            'business_id' => ['nullable', 'string', 'max:64'],
            'feed_username' => ['nullable', 'string', 'max:64'],
        ];
    }
}
