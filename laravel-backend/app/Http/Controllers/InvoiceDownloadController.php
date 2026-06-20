<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Orders\GenerateInvoicePdf;
use App\Models\Order;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer-facing invoice download. The route is protected by Laravel's `signed`
 * middleware, so the link cannot be forged or enumerated — only a temporary
 * signed URL handed to the customer (e.g. on the success page) works.
 */
class InvoiceDownloadController extends Controller
{
    public function __construct(private readonly GenerateInvoicePdf $generator) {}

    public function show(Order $order): Response
    {
        return $this->generator->handle($order)->stream("invoice-{$order->order_no}.pdf");
    }
}
