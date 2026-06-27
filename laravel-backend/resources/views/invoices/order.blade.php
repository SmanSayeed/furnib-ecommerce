<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_no }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1b1b18; font-size: 12px; margin: 0; padding: 28px; }
        h1 { font-size: 20px; margin: 0; color: #ea580c; }
        .muted { color: #6b7280; }
        .row { width: 100%; }
        .right { text-align: right; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th, table.items td { border-bottom: 1px solid #e5e7eb; padding: 8px 6px; text-align: left; }
        table.items th { font-size: 11px; text-transform: uppercase; color: #6b7280; }
        .totals { margin-top: 14px; width: 100%; }
        .totals td { padding: 4px 6px; }
        .grand { font-weight: bold; font-size: 14px; border-top: 2px solid #1b1b18; }
        .box { margin-top: 18px; }
    </style>
</head>
<body>
    @php
        $tk = static fn ($money) => 'Tk ' . number_format($money->toDisplay(), 2);
    @endphp

    <table class="row">
        <tr>
            <td>
                @if (!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height: 48px; max-width: 220px; margin-bottom: 6px;">
                @endif
                <h1>{{ $siteName }}</h1><div class="muted">Invoice</div>
            </td>
            <td class="right">
                <div><strong>{{ $order->order_no }}</strong></div>
                <div class="muted">{{ optional($order->created_at)->format('d M Y, h:i A') }}</div>
                <div class="muted">Status: {{ ucfirst($order->status) }} · {{ ucfirst($order->payment_status) }}</div>
            </td>
        </tr>
    </table>

    <div class="box">
        <strong>Bill to</strong><br>
        {{ $order->customer?->name ?? '—' }}<br>
        {{ $order->customer?->mobile }}<br>
        @if ($order->customer?->email){{ $order->customer->email }}<br>@endif
        <span class="muted">{{ $order->address }}</span>
        @if ($order->shippingZone)<br><span class="muted">Zone: {{ $order->shippingZone->name }}</span>@endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th>SKU</th>
                <th class="right">Price</th>
                <th class="right">Qty</th>
                <th class="right">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>{{ $item->title }}</td>
                    <td>{{ $item->sku }}</td>
                    <td class="right">{{ $tk($item->price) }}</td>
                    <td class="right">{{ $item->qty }}</td>
                    <td class="right">{{ $tk($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="right">Subtotal</td><td class="right" style="width:120px">{{ $tk($order->subtotal) }}</td></tr>
        <tr><td class="right">Shipping</td><td class="right">{{ $tk($order->shipping_cost) }}</td></tr>
        <tr class="grand"><td class="right">Total</td><td class="right">{{ $tk($order->total) }}</td></tr>
    </table>
</body>
</html>
