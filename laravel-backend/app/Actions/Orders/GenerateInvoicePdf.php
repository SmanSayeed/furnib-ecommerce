<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Database\Eloquent\Collection;

/**
 * Renders order documents to branded PDFs from the order's snapshot data: the
 * invoice (single + chained A4 batch) and the courier shipping label (single +
 * one-per-page batch). Company name, logo and contact details come from
 * settings so the templates stay tenant-agnostic.
 *
 * The item relation is loaded with its product so a variation/attribute can be
 * shown when available.
 */
final class GenerateInvoicePdf
{
    private const RELATIONS = ['items', 'customer', 'shippingZone', 'shipment'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly StorageRepository $storage,
    ) {}

    public function handle(Order $order): DomPdf
    {
        return $this->render('invoices.order', ['order' => $order->loadMissing(self::RELATIONS)]);
    }

    /**
     * One A4 invoice per order, chained into a single PDF (dashed separator).
     *
     * @param  Collection<int, Order>  $orders
     */
    public function bulkInvoices(Collection $orders): DomPdf
    {
        $orders->loadMissing(self::RELATIONS);

        return $this->render('invoices.bulk', ['orders' => $orders]);
    }

    public function shippingLabel(Order $order): DomPdf
    {
        return $this->render('shipping-labels.single', ['order' => $order->loadMissing(self::RELATIONS)]);
    }

    /**
     * Courier shipping labels, one order per page.
     *
     * @param  Collection<int, Order>  $orders
     */
    public function shippingLabels(Collection $orders): DomPdf
    {
        $orders->loadMissing(self::RELATIONS);

        return $this->render('shipping-labels.bulk', ['orders' => $orders]);
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

        $siteName = ($branding['site_name'] ?? null) ?: config('app.name');

        $pdf = Pdf::loadView($view, array_merge($data, [
            'siteName' => $siteName,
            'logoUrl' => $logoUrl,
            'company' => [
                'name' => $siteName,
                'website' => rtrim((string) config('app.frontend_url'), '/'),
                'address' => ($branding['contact_address'] ?? null) ?: ($branding['registered_address'] ?? null),
                'phone' => $branding['contact_phone'] ?? null,
                'email' => $branding['contact_email'] ?? null,
            ],
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
