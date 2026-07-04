<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Enums\OrderNotificationEvent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Customer SMS notification settings (Automas). API key is a write-only secret;
 * templates are BTRC-vettable Bangla text the owner edits. Per-event toggles let
 * the owner choose which status changes notify the customer.
 */
class SmsSettingsRequest extends FormRequest
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
        $rules = [
            'enabled' => ['required', 'boolean'],
            'sender_id' => ['nullable', 'string', 'max:32'],
            'api_key' => ['nullable', 'string', 'max:255'],
        ];

        foreach (OrderNotificationEvent::cases() as $event) {
            $rules[$event->toggleKey()] = ['required', 'boolean'];
            $rules[$event->templateKey()] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }
}
