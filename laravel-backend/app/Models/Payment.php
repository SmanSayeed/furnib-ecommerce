<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Concerns\Auditable;
use App\Support\Money;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;

/**
 * A gateway payment attempt against an order. The plaintext gateway response is
 * encrypted at rest; the audit log records only non-sensitive fields.
 *
 * @property int $id
 * @property int $order_id
 * @property string $gateway
 * @property string|null $method bKash/Nagad/Rocket/bank/cash/other for manual entries
 * @property Money $amount
 * @property string $type
 * @property string $direction
 * @property string $tran_id
 * @property string|null $val_id
 * @property string $status
 * @property string|null $note
 * @property array<string, mixed>|null $raw_payload
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use Auditable, HasFactory;

    public const TYPE_FULL = 'full';

    public const TYPE_PARTIAL = 'partial';

    public const TYPE_SHIPPING = 'shipping';

    // An admin-recorded offline adjustment (cash/bank received, or a refund).
    public const TYPE_MANUAL = 'manual';

    public const TYPES = [self::TYPE_FULL, self::TYPE_PARTIAL, self::TYPE_SHIPPING, self::TYPE_MANUAL];

    // Ledger direction — credit adds to what's paid, debit reduces it.
    public const DIRECTION_CREDIT = 'credit';

    public const DIRECTION_DEBIT = 'debit';

    public const DIRECTIONS = [self::DIRECTION_CREDIT, self::DIRECTION_DEBIT];

    public const GATEWAY_MANUAL = 'manual';

    // The channel a manual payment came through. `other` pairs with a free-text note.
    public const METHOD_BKASH = 'bkash';

    public const METHOD_NAGAD = 'nagad';

    public const METHOD_ROCKET = 'rocket';

    public const METHOD_BANK = 'bank';

    public const METHOD_CASH = 'cash';

    public const METHOD_OTHER = 'other';

    public const METHODS = [
        self::METHOD_BKASH, self::METHOD_NAGAD, self::METHOD_ROCKET,
        self::METHOD_BANK, self::METHOD_CASH, self::METHOD_OTHER,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    // The customer backed out at the gateway — distinct from a genuine failure
    // (bank decline, timeout) so support can tell "changed their mind" apart from
    // "payment broke".
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SUCCESS,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'order_id', 'gateway', 'method', 'amount', 'type', 'direction', 'tran_id', 'val_id', 'status', 'note', 'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'raw_payload' => 'encrypted:array',
        ];
    }

    /**
     * Never log the encrypted gateway payload to the activity log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['order_id', 'gateway', 'method', 'amount', 'type', 'direction', 'tran_id', 'val_id', 'status', 'note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('Payment');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
