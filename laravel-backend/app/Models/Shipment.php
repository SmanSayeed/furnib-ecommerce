<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\Auditable;
use App\Support\Money;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;

/**
 * Courier consignment for an order. The raw courier response is encrypted at
 * rest; the audit log records only non-sensitive fields.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $courier_id
 * @property string $courier
 * @property string|null $consignment_id
 * @property string|null $tracking_code
 * @property string $status
 * @property string $recipient_name
 * @property string $recipient_phone
 * @property string $recipient_address
 * @property Money $cod_amount
 * @property string|null $note
 * @property array<string, mixed>|null $raw_payload
 * @property array<string, mixed>|null $meta
 */
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use Auditable, HasFactory;

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'order_id', 'courier_id', 'courier', 'consignment_id', 'tracking_code', 'status',
        'recipient_name', 'recipient_phone', 'recipient_address', 'cod_amount', 'note', 'raw_payload', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'cod_amount' => MoneyCast::class,
            'raw_payload' => 'encrypted:array',
            'meta' => 'encrypted:array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['order_id', 'courier', 'consignment_id', 'tracking_code', 'status', 'cod_amount'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Shipment');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Courier, $this> */
    public function courierModel(): BelongsTo
    {
        return $this->belongsTo(Courier::class, 'courier_id');
    }
}
