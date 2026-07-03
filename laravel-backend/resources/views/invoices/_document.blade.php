@php
    $tk = static fn ($money) => number_format($money->toDisplay(), 0) . 'Tk.';
    $payableMinor = max(0, $order->total->toMinor() - $order->advance_paid->toMinor());
    $courier = $order->shipment?->courier;
    $zone = $order->shippingZone?->name;
@endphp

<div class="inv">
    <div class="inv-title">INVOICE</div>

    <table class="head">
        <tr>
            <td class="head-left">
                <div class="ono">Order# {{ $order->order_no }}</div>
                @if (!empty($company['website']))
                    <div class="web">{{ $company['website'] }}</div>
                @endif
                <div class="barcode">{!! \App\Support\Barcode::html($order->order_no, 34, 1) !!}</div>
            </td>
            <td class="head-right">
                @if (!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $company['name'] }}" class="logo">
                @else
                    <div class="brand">{{ $company['name'] }}</div>
                @endif
                @if (!empty($company['address']))<div class="muted">{{ $company['address'] }}</div>@endif
                @if (!empty($company['phone']))<div class="muted">Phone: {{ $company['phone'] }}</div>@endif
                @if (!empty($company['email']))<div class="muted">Email: {{ $company['email'] }}</div>@endif
            </td>
        </tr>
    </table>

    <table class="info">
        <tr>
            <td class="info-col">
                <div class="info-h">Customer Information:</div>
                <div><strong>Name:</strong> {{ $order->customer?->name ?? '—' }}</div>
                <div><strong>Phone:</strong> {{ $order->customer?->mobile ?? '—' }}</div>
                <div><strong>Address:</strong> {{ $order->address }}</div>
                <div><strong>Payment method:</strong> {{ strtoupper($order->payment_status === 'paid' ? 'PAID' : 'COD') }}</div>
            </td>
            <td class="info-col">
                <div class="info-h">Shipping Information</div>
                <div><strong>Courier:</strong> {{ $courier ?: '—' }}</div>
                <div><strong>Zone:</strong> {{ $zone ?: '—' }}</div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="c-sl">SL</th>
                <th>Product Name</th>
                <th class="c-sku">SKU</th>
                <th class="c-attr">Attributes</th>
                <th class="c-qty">Quantity</th>
                <th class="c-price right">Price</th>
                <th class="c-total right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $i => $item)
                <tr>
                    <td class="c-sl">{{ $i + 1 }}</td>
                    <td>{{ $item->title }}</td>
                    <td class="c-sku">{{ $item->sku }}</td>
                    <td class="c-attr"></td>
                    <td class="c-qty">{{ $item->qty }}</td>
                    <td class="right">{{ $tk($item->price) }}</td>
                    <td class="right">{{ $tk($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="foot">
        <tr>
            <td class="foot-note">
                <div>Order Received By : Logistics &amp; Fulfillment</div>
                <div class="muted">NB: This invoice will be used as a Warranty Card from purchase date
                    {{ optional($order->created_at)->format('d/m/Y h:i:s A') }}</div>
            </td>
            <td class="foot-totals">
                <table class="totals">
                    <tr><td>Sub Total:</td><td class="right">{{ $tk($order->subtotal) }}</td></tr>
                    <tr><td>Delivery:</td><td class="right">{{ $tk($order->shipping_cost) }}</td></tr>
                    <tr><td>Discount:</td><td class="right">0Tk.</td></tr>
                    <tr><td>Advance:</td><td class="right">{{ $tk($order->advance_paid) }}</td></tr>
                    <tr class="payable">
                        <td>Payable:</td>
                        <td class="right">{{ number_format($payableMinor / 100, 0) }} Tk.</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>
