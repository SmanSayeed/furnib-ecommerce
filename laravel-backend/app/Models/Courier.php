<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\CourierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    /** A single credential value from the encrypted config. */
    public function credential(string $key): ?string
    {
        $value = $this->config[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
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
