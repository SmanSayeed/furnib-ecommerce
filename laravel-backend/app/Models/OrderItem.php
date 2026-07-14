<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money;
use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property string $title
 * @property string|null $sku
 * @property Money $price Charged unit price (the effective/discounted one)
 * @property Money|null $original_price Regular unit price — only set when discounted
 * @property Money $discount_amount (original_price − price) × qty
 * @property int $qty
 * @property Money $line_total
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'title', 'sku',
        'price', 'original_price', 'discount_amount', 'qty', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'price' => MoneyCast::class,
            'original_price' => MoneyCast::class,
            'discount_amount' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'qty' => 'integer',
        ];
    }

    /** Did this line get a product discount at order time? */
    public function wasDiscounted(): bool
    {
        return $this->original_price instanceof Money;
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
