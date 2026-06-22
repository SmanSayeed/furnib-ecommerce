<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A browser→server tracking beacon for the storefront. Only the non-conversion
 * funnel events are accepted here; Purchase is fired server-side from the order
 * lifecycle so its value can never be forged by the client.
 */
class CollectEventRequest extends FormRequest
{
    public const ALLOWED = ['ViewContent', 'InitiateCheckout', 'Lead'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in(self::ALLOWED)],
            'event_id' => ['required', 'string', 'max:128'],
            'sku' => ['nullable', 'string', 'max:64'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
            'event_source_url' => ['nullable', 'string', 'url', 'max:2048'],
            // First-party Meta cookies (non-secret); raised match quality.
            'fbp' => ['nullable', 'string', 'max:255'],
            'fbc' => ['nullable', 'string', 'max:255'],
        ];
    }
}
