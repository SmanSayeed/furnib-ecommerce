<?php

declare(strict_types=1);

namespace App\Support;

use Picqer\Barcode\BarcodeGeneratorHTML;

/**
 * Renders a Code 128 barcode as self-contained HTML (nested coloured divs) that
 * DomPDF can draw without any image/remote asset. Used on invoices and courier
 * shipping labels so an order number is machine-scannable.
 */
final class Barcode
{
    public static function html(string $value, int $height = 40, int $widthFactor = 1): string
    {
        $generator = new BarcodeGeneratorHTML;

        // Code 128 encodes our alphanumeric order numbers (e.g. FNB-20260703-1234).
        // picqer requires an int width factor (bar-width multiplier).
        return $generator->getBarcode($value, $generator::TYPE_CODE_128, $widthFactor, $height);
    }
}
