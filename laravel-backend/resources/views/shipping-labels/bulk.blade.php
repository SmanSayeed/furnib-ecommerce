<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Shipping labels ({{ $orders->count() }})</title>
    @include('shipping-labels._styles')
</head>
<body>
    @foreach ($orders as $order)
        <div class="label-page">
            @include('shipping-labels._label')
        </div>
    @endforeach
</body>
</html>
