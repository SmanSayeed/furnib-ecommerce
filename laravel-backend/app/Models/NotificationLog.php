<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit + idempotency record for a customer notification (SMS now, email later).
 * One row per order+event+channel; a `sent` row blocks a duplicate send. A later
 * provider delivery report (DLR) can advance it to delivered / undelivered.
 *
 * @property int $id
 * @property int|null $order_id
 * @property string $channel
 * @property string $event
 * @property string $recipient
 * @property string|null $message
 * @property string|null $provider
 * @property string|null $provider_message_id
 * @property string $status
 * @property string|null $status_code
 * @property Carbon|null $delivered_at
 * @property string|null $error
 */
class NotificationLog extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    // Set from a delivery report (DLR) after the message was accepted.
    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_UNDELIVERED = 'undelivered';

    protected $fillable = [
        'order_id', 'channel', 'event', 'recipient', 'message',
        'provider', 'provider_message_id', 'status', 'status_code', 'delivered_at', 'error',
    ];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
