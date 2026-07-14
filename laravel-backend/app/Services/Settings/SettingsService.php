<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Grouped key-value settings with typed casting and at-rest encryption for
 * secret values. Secret values are never returned by toArray() unless
 * explicitly requested server-side.
 */
final class SettingsService
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('group', $group)->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return $this->cast($this->decrypt($setting), $setting->type);
    }

    public function set(string $group, string $key, mixed $value, bool $isSecret = false): Setting
    {
        [$serialized, $type] = $this->serialize($value);
        $stored = ($isSecret && $serialized !== null) ? Crypt::encryptString($serialized) : $serialized;

        return Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $stored, 'type' => $type, 'is_secret' => $isSecret],
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(string $group, bool $includeSecrets = false): array
    {
        return Setting::query()->where('group', $group)->get()
            ->mapWithKeys(function (Setting $setting) use ($includeSecrets): array {
                if ($setting->is_secret && ! $includeSecrets) {
                    return [$setting->key => null];
                }

                return [$setting->key => $this->cast($this->decrypt($setting), $setting->type)];
            })->all();
    }

    /**
     * A secret encrypted under a DIFFERENT APP_KEY (DB moved between environments,
     * key rotated, `config:cache` baked a build-time key) throws here. Nothing used
     * to catch it, so the Integrations page 500'd on load with APP_DEBUG=false —
     * i.e. a blank error and no clue. Degrade to null (= "not set, re-enter it")
     * and report, so the cause is visible in /admin/dev/errors.
     */
    private function decrypt(Setting $setting): ?string
    {
        if ($setting->value === null) {
            return null;
        }

        if (! $setting->is_secret) {
            return $setting->value;
        }

        try {
            return Crypt::decryptString($setting->value);
        } catch (DecryptException $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array{0:?string,1:string}
     */
    private function serialize(mixed $value): array
    {
        return match (true) {
            $value === null => [null, 'string'],
            is_bool($value) => [$value ? '1' : '0', 'boolean'],
            is_int($value) => [(string) $value, 'integer'],
            is_float($value) => [(string) $value, 'double'],
            is_array($value) => [(string) json_encode($value), 'array'],
            default => [(string) $value, 'string'],
        };
    }

    private function cast(?string $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $raw === '1',
            'integer' => (int) $raw,
            'double' => (float) $raw,
            'array' => json_decode($raw, true),
            default => $raw,
        };
    }
}
