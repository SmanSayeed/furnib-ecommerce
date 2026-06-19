<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #ea580c;">Thank you for your order!</h2>
    <p>Order <strong>{{ $order->order_no }}</strong> has been received.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 560px;">
        <thead>
            <tr style="background: #f3f4f6; text-align: left;">
                <th>Item</th><th>Qty</th><th style="text-align:right;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr style="border-bottom: 1px solid #e5e7eb;">
                    <td>{{ $item->title }}</td>
                    <td>{{ $item->qty }}</td>
                    <td style="text-align:right;">{{ $item->line_total->format() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p style="margin-top: 16px;">
        Subtotal: {{ $order->subtotal->format() }}<br>
        Shipping: {{ $order->shipping_cost->format() }}<br>
        <strong>Total: {{ $order->total->format() }}</strong>
    </p>

    <p>Delivery address:<br>{{ $order->address }}</p>
    <p style="color: #6b7280; font-size: 13px;">We will contact you to confirm delivery. Thank you for shopping with Furnib.</p>
</body>
</html>
