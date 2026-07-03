<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shipping label {{ $order->order_no }}</title>
    @include('shipping-labels._styles')
</head>
<body>
    <div style="padding: 18px;">
        @include('shipping-labels._label')
    </div>
</body>
</html>
