<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\Auditable;
use App\Support\Money;
use Database\Factories\ShippingZoneFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property Money $cost
 * @property bool $status
 * @property int $position_order
 */
class ShippingZone extends Model
{
    /** @use HasFactory<ShippingZoneFactory> */
    use Auditable, HasFactory;

    protected $fillable = ['name', 'cost', 'status', 'position_order'];

    protected function casts(): array
    {
        return [
            'cost' => MoneyCast::class,
            'status' => 'boolean',
            'position_order' => 'integer',
        ];
    }

    /** @param Builder<ShippingZone> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', true);
    }

    /** @param Builder<ShippingZone> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position_order')->orderBy('name');
    }
}
