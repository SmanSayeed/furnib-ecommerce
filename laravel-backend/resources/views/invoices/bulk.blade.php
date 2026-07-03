<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoices ({{ $orders->count() }})</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color: #1b1b18; font-size: 12px; margin: 0; }
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
        /* One invoice per A4 page. */
        .invoice-page { padding: 28px; page-break-after: always; }
        .invoice-page:last-child { page-break-after: auto; }
    </style>
</head>
<body>
    @foreach ($orders as $order)
        <div class="invoice-page">
            @include('invoices._document')
        </div>
    @endforeach
</body>
</html>
