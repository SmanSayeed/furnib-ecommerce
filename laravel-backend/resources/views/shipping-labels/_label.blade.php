@php
    $advancePaidMinor = $order->advance_paid->toMinor();
    $codMinor = max(0, $order->total->toMinor() - $advancePaidMinor);
    $courier = $order->shipment?->courier;
@endphp

<div class="label">
    <table class="l-top">
        <tr>
            <td class="l-logo">
                @if (!empty($logoUrl))
                    <img src="{{ $logoUrl }}" alt="{{ $company['name'] }}">
                @else
                    <span class="brand">{{ $company['name'] }}</span>
                @endif
            </td>
            <td class="l-barcode">
                {!! \App\Support\Barcode::html($order->order_no, 46, 2) !!}
                <div class="num">{{ $order->order_no }}</div>
            </td>
        </tr>
    </table>

    <div class="l-addr">
        <div class="ono">Order #{{ $order->order_no }}</div>
        <div class="nm">{{ $order->customer?->name ?? '—' }}</div>
        <div class="ph">{{ $order->customer?->mobile }}</div>
        <div class="street">{{ $order->address }}</div>
    </div>

    <table class="prod">
        <thead>
            <tr>
                <th>Product</th>
                <th class="p-var">Variation</th>
                <th class="p-qty">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td class="p-name">{{ $item->title }}</td>
                    <td class="p-var"></td>
                    <td class="p-qty">{{ $item->qty }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="cod-row">
        <tr>
            <td class="cod">COD (collect): <strong>Tk {{ number_format($codMinor / 100, 0) }}</strong></td>
            <td class="courier">COURIER: <strong>{{ $courier ?: '—' }}</strong></td>
        </tr>
        @if ($advancePaidMinor > 0)
            <tr>
                <td class="paid" colspan="2">
                    Total Tk {{ number_format($order->total->toMinor() / 100, 0) }}
                    · Advance paid Tk {{ number_format($advancePaidMinor / 100, 0) }}
                    (collect only the COD balance above)
                </td>
            </tr>
        @endif
    </table>

    <div class="ret">
        <div class="h">Return Address</div>
        <div class="l">{{ $company['name'] }}@if (!empty($company['address'])) | {{ $company['address'] }}@endif</div>
        <div class="l">
            @if (!empty($company['phone']))Phone: {{ $company['phone'] }}@endif
            @if (!empty($company['email'])) | {{ $company['email'] }}@endif
        </div>
    </div>
</div>
