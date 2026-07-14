<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use App\Services\Settings\SettingsService;
use Database\Factories\CourierFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use Spatie\Activitylog\LogOptions;

/**
 * A courier the shop can ship with. Either API-driven (Steadfast/RedX/Pathao)
 * or 'manual' (a named courier with no API — booked by hand, its name still
 * printed on the shipping label). Per-courier credentials live in the encrypted
 * `config` and never reach the browser.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $driver
 * @property bool $is_active
 * @property bool $is_default
 * @property int $position_order
 * @property array<string, mixed>|null $config
 */
class Courier extends Model
{
    /** @use HasFactory<CourierFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const DRIVER_MANUAL = 'manual';

    public const DRIVER_STEADFAST = 'steadfast';

    public const DRIVER_REDX = 'redx';

    public const DRIVER_PATHAO = 'pathao';

    /** All selectable drivers. */
    public const DRIVERS = [
        self::DRIVER_MANUAL,
        self::DRIVER_STEADFAST,
        self::DRIVER_REDX,
        self::DRIVER_PATHAO,
    ];

    /** Drivers that talk to a provider API (manual is excluded). */
    public const API_DRIVERS = [
        self::DRIVER_STEADFAST,
        self::DRIVER_REDX,
        self::DRIVER_PATHAO,
    ];

    /** Required credential keys per API driver — used to gauge "configured". */
    public const REQUIRED_CREDENTIALS = [
        self::DRIVER_STEADFAST => ['api_key', 'secret_key'],
        self::DRIVER_REDX => ['access_token', 'pickup_store_id'],
        self::DRIVER_PATHAO => ['client_id', 'client_secret', 'username', 'password', 'store_id'],
    ];

    protected $fillable = [
        'name', 'slug', 'driver', 'is_active', 'is_default', 'position_order', 'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'position_order' => 'integer',
            'config' => 'encrypted:array',
        ];
    }

    /** Audit everything except the encrypted credentials. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'driver', 'is_active', 'is_default', 'position_order'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Courier');
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @param Builder<Courier> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** The active default courier auto-booked on confirm, if any. */
    public static function default(): ?self
    {
        return self::query()->active()->where('is_default', true)->first();
    }

    public function isApi(): bool
    {
        return in_array($this->driver, self::API_DRIVERS, true);
    }

    /**
     * The decrypted config, or an empty array if it cannot be decrypted.
     *
     * `config` is an `encrypted:array` cast, so merely READING it throws when the
     * row was encrypted under a different APP_KEY (DB moved between environments,
     * key rotated, or `config:cache` baked a build-time key). Nothing used to catch
     * that, which turned the couriers list and the integrations page into hard
     * 500s. Degrading to "not configured" is honest and actionable; a 500 is not.
     *
     * @return array<string, mixed>
     */
    public function safeConfig(): array
    {
        // Decrypt the raw attribute ourselves rather than going through the cast:
        // the cast throws from deep inside attribute access, which is exactly the
        // unguardable path that produced the 500s.
        $raw = $this->attributes['config'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($raw), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $e) {
            report($e); // surfaces in /admin/dev/errors

            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * A single resolved credential.
     *
     * Credentials have historically lived in TWO places: this model's encrypted
     * `config` (Shipping → Couriers) and the legacy `steadfast` settings group
     * (Settings → Integrations). The driver factory always read both, but this
     * method — and therefore isConfigured(), the Book button and the auto-push
     * observer — read only the first. An owner who entered their keys under
     * Settings → Integrations saw "Configured" there and "needs credentials" on the
     * order page, with booking dead in between.
     *
     * Resolving the fallback HERE means every caller sees one value, so the two
     * pages can no longer disagree.
     */
    public function credential(string $key): ?string
    {
        $value = $this->safeConfig()[$key] ?? null;
        $value = is_scalar($value) ? (string) $value : null;

        return filled($value) ? $value : $this->legacyCredential($key);
    }

    /**
     * Where a Pathao courier's OAuth token is cached. Named here (not inlined at
     * the two call sites) so the admin form can forget it when the credentials
     * change — otherwise a corrected password keeps 401-ing behind a token that
     * lives for days.
     */
    public static function pathaoTokenCacheKey(int $courierId): string
    {
        return 'courier:pathao:token:'.$courierId;
    }

    /** Steadfast is the only driver that ever had a settings-group store. */
    private function legacyCredential(string $key): ?string
    {
        if ($this->driver !== self::DRIVER_STEADFAST) {
            return null;
        }

        $value = app(SettingsService::class)->get('steadfast', $key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Whether this courier can actually be used: a manual courier always can; an
     * API courier needs all of its required credentials present.
     */
    public function isConfigured(): bool
    {
        if (! $this->isApi()) {
            return true;
        }

        foreach (self::REQUIRED_CREDENTIALS[$this->driver] ?? [] as $key) {
            if (blank($this->credential($key))) {
                return false;
            }
        }

        return true;
    }
}
