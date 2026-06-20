<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Order;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_no' => $this->order_no,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'subtotal' => $this->money($this->subtotal),
            'shipping_cost' => $this->money($this->shipping_cost),
            'total' => $this->money($this->total),
            'advance_paid' => $this->money($this->advance_paid),
            'address' => $this->address,
            'invoice_url' => URL::temporarySignedRoute(
                'invoice.public',
                CarbonImmutable::now()->addDay(),
                ['order' => $this->id],
            ),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'title' => $item->title,
                'sku' => $item->sku,
                'price' => $this->money($item->price),
                'qty' => $item->qty,
                'line_total' => $this->money($item->line_total),
            ])->values()->all()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function money(Money $money): array
    {
        return [
            'minor' => $money->toMinor(),
            'display' => $money->toDisplay(),
            'formatted' => $money->format(),
        ];
    }
}
