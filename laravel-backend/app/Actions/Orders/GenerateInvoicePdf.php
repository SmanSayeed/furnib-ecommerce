<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Models\Order;
use App\Services\Settings\SettingsService;
use App\Storage\Contracts\StorageRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

/**
 * Renders an order's invoice to a PDF from the order's snapshot data, branded
 * with the site name and logo from settings.
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
        $branding = $this->settings->toArray('branding');

        // Prefer a dedicated invoice logo, fall back to the light header logo.
        $logoPath = ($branding['logo_invoice'] ?? null) ?: ($branding['logo_light'] ?? null);
        $logoUrl = is_string($logoPath) && $logoPath !== '' ? $this->resolveUrl($logoPath) : null;

        $pdf = Pdf::loadView('invoices.order', [
            'order' => $order,
            'siteName' => ($branding['site_name'] ?? null) ?: config('app.name'),
            'logoUrl' => $logoUrl,
        ]);

        // Only opened when we actually embed a logo. The single image src is our
        // own admin-uploaded branding URL (never user input), so there is no
        // SSRF surface — the invoice template contains no other external refs.
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
