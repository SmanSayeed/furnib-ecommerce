<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslips ({{ $orders->count() }})</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        @page { margin: 8mm; }
        body { color: #1b1b18; font-size: 11px; margin: 0; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        /* Three slips per A4 (usable height ~281mm ÷ 3). */
        .slip {
            height: 90mm;
            box-sizing: border-box;
            border: 1px dashed #9ca3af;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 4mm;
            overflow: hidden;
        }
        .slip:last-child { margin-bottom: 0; }
        .slip-head { width: 100%; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .brand { font-size: 14px; font-weight: bold; color: #ea580c; }
        .ono { font-size: 12px; font-weight: bold; }
        .to { margin-top: 6px; }
        .to strong { font-size: 12px; }
        .items { margin-top: 4px; }
        .cod {
            margin-top: 6px;
            font-size: 13px;
            font-weight: bold;
            border-top: 1px solid #1b1b18;
            padding-top: 4px;
        }
        table.head-t { width: 100%; }
    </style>
</head>
<body>
    @php
        $tk = static fn ($minor) => 'Tk ' . number_format($minor / 100, 2);
    @endphp

    @foreach ($orders->chunk(3) as $page)
        <div class="page">
            @foreach ($page as $order)
                @php
                    $codMinor = max(0, $order->total->toMinor() - $order->advance_paid->toMinor());
                    $itemCount = $order->items->sum('qty');
                    $summary = $order->items
                        ->map(fn ($i) => $i->title . ' ×' . $i->qty)
                        ->take(3)
                        ->implode(', ');
                    if ($order->items->count() > 3) {
                        $summary .= ' …';
                    }
                @endphp
                <div class="slip">
                    <table class="head-t">
                        <tr>
                            <td>
                                @if (!empty($logoUrl))
                                    <img src="{{ $logoUrl }}" alt="{{ $siteName }}" style="max-height: 26px; max-width: 140px;">
                                @else
                                    <span class="brand">{{ $siteName }}</span>
                                @endif
                            </td>
                            <td class="right">
                                <span class="ono">{{ $order->order_no }}</span><br>
                                <span class="muted">{{ optional($order->created_at)->format('d M Y') }}</span>
                            </td>
                        </tr>
                    </table>

                    <div class="to">
                        <strong>{{ $order->customer?->name ?? '—' }}</strong>
                        &nbsp; {{ $order->customer?->mobile }}<br>
                        <span class="muted">{{ $order->address }}</span>
                        @if ($order->shippingZone)
                            <span class="muted"> · {{ $order->shippingZone->name }}</span>
                        @endif
                    </div>

                    <div class="items muted">
                        {{ $itemCount }} item{{ $itemCount === 1 ? '' : 's' }} — {{ $summary }}
                    </div>

                    <div class="cod">
                        <table class="head-t">
                            <tr>
                                <td>Collect (COD)</td>
                                <td class="right">{{ $tk($codMinor) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
