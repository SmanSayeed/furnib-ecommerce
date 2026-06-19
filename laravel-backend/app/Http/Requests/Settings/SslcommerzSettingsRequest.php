<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SSLCommerz store credentials. The store password is a write-only secret.
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
            'store_id' => ['required', 'string', 'max:255'],
            'store_passwd' => ['nullable', 'string', 'max:255'],
            'sandbox' => ['required', 'boolean'],
        ];
    }
}
