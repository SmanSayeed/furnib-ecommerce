<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Orders\GenerateInvoicePdf;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function __construct(private readonly GenerateInvoicePdf $generator) {}

    public function show(Order $order): Response
    {
        $pdf = $this->generator->handle($order);

        return $pdf->download("invoice-{$order->order_no}.pdf");
    }
}
