<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class WhatsAppSettingsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already gated by `permission:settings.manage`.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Checkboxes arrive as '1'/'0'/'on' (or absent) — normalise to booleans.
        $this->merge([
            'floating_enabled' => $this->boolean('floating_enabled'),
            'inquiry_enabled' => $this->boolean('inquiry_enabled'),
            'footer_enabled' => $this->boolean('footer_enabled'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The single WhatsApp number used everywhere. Digits + country code,
            // no leading + (wa.me wants bare digits).
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]+$/'],

            // Per-button show/hide toggles — the same number, shown or hidden.
            'floating_enabled' => ['boolean'],
            'inquiry_enabled' => ['boolean'],
            'footer_enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'whatsapp.regex' => 'WhatsApp number must contain digits only (with country code, no +).',
        ];
    }
}
