<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoices ({{ $orders->count() }})</title>
    @include('invoices._styles')
</head>
<body>
    @foreach ($orders as $order)
        @include('invoices._document')
        @unless ($loop->last)
            <hr class="sep">
        @endunless
    @endforeach
</body>
</html>
