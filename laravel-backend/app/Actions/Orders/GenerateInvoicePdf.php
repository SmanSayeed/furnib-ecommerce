<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Services\Settings\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Renders an order's invoice to a PDF from the order's snapshot data, branded
 * with the site name from settings.
 */
final class GenerateInvoicePdf
{
    public function __construct(private readonly SettingsService $settings) {}

    public function handle(Order $order): DomPdf
    {
        $order->loadMissing(['items', 'customer', 'shippingZone']);
        $branding = $this->settings->toArray('branding');

        return Pdf::loadView('invoices.order', [
            'order' => $order,
            'siteName' => ($branding['site_name'] ?? null) ?: config('app.name'),
        ]);
    }
}
