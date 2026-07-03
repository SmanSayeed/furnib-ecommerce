<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoices ({{ $orders->count() }})</title>
    @include('invoices._styles')
</head>
<body class="two-up">
    {{-- Two invoices per A4 page: each invoice sits in a half-page slot, with a
         dashed cut-line between the pair and a hard page break after the pair. --}}
    @foreach ($orders as $order)
        <div class="half">
            @include('invoices._document')
        </div>
        @unless ($loop->last)
            @if ($loop->iteration % 2 === 0)
                <div class="page-break"></div>
            @else
                <hr class="sep">
            @endif
        @endunless
    @endforeach
</body>
</html>
