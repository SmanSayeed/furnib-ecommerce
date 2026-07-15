<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\AppliesListFilters;
use App\Concerns\Auditable;
use App\Observers\OrderObserver;
use App\Support\Money;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_no
 * @property int $customer_id
 * @property string $status
 * @property string $pending_reason
 * @property string|null $pending_note
 * @property string $payment_status
 * @property Money $subtotal
 * @property Money $discount Order-level admin discount (paisa). 0 = none.
 * @property string|null $discount_note
 * @property int|null $discount_by
 * @property Money $shipping_cost
 * @property Money $total
 * @property Money $advance_amount
 * @property Money $advance_paid
 * @property int|null $shipping_zone_id
 * @property string $address
 * @property string|null $notes The CUSTOMER's checkout note — read-only for staff
 * @property string|null $admin_note Staff's own note. Any status; never wiped by a transition.
 * @property string|null $customer_ip
 * @property string|null $user_agent
 * @property string|null $fbp
 * @property string|null $fbc
 * @property string|null $ttp
 * @property string|null $ttclid
 * @property string|null $ga_client_id
 * @property Carbon|null $marketing_purchase_sent_at
 * @property Carbon|null $terms_accepted_at
 * @property string|null $terms_ip
 */
#[ObservedBy([OrderObserver::class])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use AppliesListFilters, Auditable, HasFactory, SoftDeletes;

    public const STATUSES = [
        'pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned',
    ];

    public const PAYMENT_STATUSES = ['unpaid', 'partial', 'paid'];

    /**
     * Why a pending order is still open. A paid order never auto-confirms — it
     * stays pending (usually `payment_pending` cleared → still needs a human)
     * until an admin confirms it. `other` pairs with a free-text pending_note.
     */
    public const PENDING_REASONS = [
        'new_order', 'call_waiting', 'payment_pending', 'need_expert_call', 'other',
    ];

    /**
     * Allowed status transitions (forward flow + cancel/return rules).
     * `confirmed → pending` lets an admin revert a mistaken confirm.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['pending', 'processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    protected $fillable = [
        'order_no', 'customer_id', 'status', 'pending_reason', 'pending_note', 'payment_status',
        'subtotal', 'discount', 'discount_note', 'discount_by',
        'shipping_cost', 'total', 'advance_amount', 'advance_paid',
        'shipping_zone_id', 'address', 'customer_ip', 'user_agent', 'notes', 'admin_note',
        'fbp', 'fbc', 'ttp', 'ttclid', 'ga_client_id', 'marketing_purchase_sent_at',
        'terms_accepted_at', 'terms_ip',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class,
            'discount' => MoneyCast::class,
            'shipping_cost' => MoneyCast::class,
            'total' => MoneyCast::class,
            'advance_amount' => MoneyCast::class,
            'advance_paid' => MoneyCast::class,
            'marketing_purchase_sent_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
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

    /** @return HasOne<Shipment, $this> */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }
}
