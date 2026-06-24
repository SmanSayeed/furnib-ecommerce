<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Media storage settings. Driver toggle + Cloudflare R2 connection. The R2
 * access key + secret are write-only server-side secrets.
 */
class StorageSettingsUpdateRequest extends FormRequest
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
            'driver' => ['required', Rule::in(['server', 'r2'])],
            'r2_endpoint' => ['nullable', 'string', 'url', 'max:255'],
            'r2_bucket' => ['nullable', 'string', 'max:255'],
            'r2_url' => ['nullable', 'string', 'url', 'max:255'],
            'r2_region' => ['nullable', 'string', 'max:64'],
            'r2_access_key' => ['nullable', 'string', 'max:255'],
            'r2_secret_key' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * Block enabling R2 without complete credentials so the storefront can't
     * break. An "effective" value may come from this request, the stored
     * settings, or the env-backed disk config.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->input('driver') !== 'r2') {
                return;
            }

            $settings = app(SettingsService::class);

            $missing = function (string $field, string $configKey) use ($settings): bool {
                return blank($this->input($field))
                    && blank($settings->get('storage', $field))
                    && blank(config("filesystems.disks.r2.$configKey"));
            };

            if ($missing('r2_bucket', 'bucket')) {
                $v->errors()->add('r2_bucket', 'Bucket is required to enable R2.');
            }
            if ($missing('r2_endpoint', 'endpoint')) {
                $v->errors()->add('r2_endpoint', 'Endpoint is required to enable R2.');
            }
            if ($missing('r2_access_key', 'key')) {
                $v->errors()->add('r2_access_key', 'Access key is required to enable R2.');
            }
            if ($missing('r2_secret_key', 'secret')) {
                $v->errors()->add('r2_secret_key', 'Secret key is required to enable R2.');
            }
        });
    }
}
