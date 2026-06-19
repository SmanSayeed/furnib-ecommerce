<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\Auditable;
use App\Support\Money;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $order_no
 * @property int $customer_id
 * @property string $status
 * @property string $payment_status
 * @property Money $subtotal
 * @property Money $shipping_cost
 * @property Money $total
 * @property Money $advance_paid
 * @property int|null $shipping_zone_id
 * @property string $address
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use Auditable, HasFactory, SoftDeletes;

    public const STATUSES = [
        'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned',
    ];

    public const PAYMENT_STATUSES = ['unpaid', 'partial', 'paid'];

    /**
     * Allowed status transitions (forward flow + cancel/return rules).
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    protected $fillable = [
        'order_no', 'customer_id', 'status', 'payment_status',
        'subtotal', 'shipping_cost', 'total', 'advance_paid',
        'shipping_zone_id', 'address', 'customer_ip', 'user_agent', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class,
            'shipping_cost' => MoneyCast::class,
            'total' => MoneyCast::class,
            'advance_paid' => MoneyCast::class,
        ];
    }

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return BelongsTo<ShippingZone, $this> */
    public function shippingZone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
