<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_no }}</title>
    @include('invoices._styles')
</head>
<body>
    @include('invoices._document')
</body>
</html>
