<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SSLCommerz store credentials. Sandbox and live keys are stored separately so
 * switching mode never wipes the other environment. All credential fields are
 * blank-keeps (only overwritten when a new value is provided); the passwords are
 * write-only secrets. Only the mode toggle is required.
 */
class SslcommerzSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sandbox' => ['required', 'boolean'],
            'sandbox_store_id' => ['nullable', 'string', 'max:255'],
            'sandbox_store_passwd' => ['nullable', 'string', 'max:255'],
            'live_store_id' => ['nullable', 'string', 'max:255'],
            'live_store_passwd' => ['nullable', 'string', 'max:255'],
        ];
    }
}
