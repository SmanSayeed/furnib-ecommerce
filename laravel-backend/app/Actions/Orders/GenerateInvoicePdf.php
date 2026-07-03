<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;

/**
 * Renders an order's invoice to a PDF from the order's snapshot data, branded
 * with the site name and logo from settings. Also renders batched documents:
 * chained A4 invoices (one order per page) and courier payslips (three per A4).
 */
final class GenerateInvoicePdf
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly StorageRepository $storage,
    ) {}

    public function handle(Order $order): DomPdf
    {
        $order->loadMissing(['items', 'customer', 'shippingZone']);

        return $this->render('invoices.order', ['order' => $order]);
    }

    /**
     * One A4 invoice per order, chained into a single PDF (page break between).
     *
     * @param  Collection<int, Order>  $orders
     */
    public function bulkInvoices(Collection $orders): DomPdf
    {
        $orders->loadMissing(['items', 'customer', 'shippingZone']);

        return $this->render('invoices.bulk', ['orders' => $orders]);
    }

    /**
     * Compact courier payslips, three to an A4 page.
     *
     * @param  Collection<int, Order>  $orders
     */
    public function payslips(Collection $orders): DomPdf
    {
        $orders->loadMissing(['items', 'customer', 'shippingZone']);

        return $this->render('invoices.payslips', ['orders' => $orders]);
    }

    /**
     * Render a branded PDF view, enabling remote images only when a logo is
     * embedded (the sole external ref, an admin-uploaded URL — no SSRF surface).
     *
     * @param  array<string, mixed>  $data
     */
    private function render(string $view, array $data): DomPdf
    {
        $branding = $this->settings->toArray('branding');

        // Prefer a dedicated invoice logo, fall back to the light header logo.
        $logoPath = ($branding['logo_invoice'] ?? null) ?: ($branding['logo_light'] ?? null);
        $logoUrl = is_string($logoPath) && $logoPath !== '' ? $this->resolveUrl($logoPath) : null;

        $pdf = Pdf::loadView($view, array_merge($data, [
            'siteName' => ($branding['site_name'] ?? null) ?: config('app.name'),
            'logoUrl' => $logoUrl,
        ]));

        if ($logoUrl !== null) {
            $pdf->setOption('isRemoteEnabled', true);
        }

        return $pdf;
    }

    private function resolveUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->storage->url($path);
    }
}
