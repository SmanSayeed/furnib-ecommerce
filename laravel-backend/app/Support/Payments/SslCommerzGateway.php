<?php

declare(strict_types=1);

namespace App\Support\Payments;

use App\Models\Order;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SSLCommerz hosted-checkout gateway. Store credentials are read from encrypted
 * settings at call time and sent only to SSLCommerz over HTTPS — never returned
 * to the client or logged. Payment acceptance always goes through
 * validatePayment() (the validation API), never the redirect alone.
 */
final class SslCommerzGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsService $settings) {}

    public function initSession(Order $order, Money $amount, string $tranId): string
    {
        [$storeId, $storePassword] = $this->credentials();

        $response = Http::asForm()->post($this->baseUrl().'/gwprocess/v4/api.php', [
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'total_amount' => number_format($amount->toDisplay(), 2, '.', ''),
            'currency' => 'BDT',
            'tran_id' => $tranId,
            'success_url' => route('api.payment.ssl.success'),
            'fail_url' => route('api.payment.ssl.fail'),
            'cancel_url' => route('api.payment.ssl.cancel'),
            'ipn_url' => route('api.payment.ssl.ipn'),
            'shipping_method' => 'Courier',
            'product_name' => 'Furnib order '.$order->order_no,
            'product_category' => 'Furniture',
            'product_profile' => 'physical-goods',
            'cus_name' => (string) ($order->customer->name ?? 'Customer'),
            'cus_phone' => (string) ($order->customer->mobile ?? ''),
            'cus_add1' => $order->address,
            'cus_city' => 'Dhaka',
            'cus_country' => 'Bangladesh',
        ]);

        $data = $response->json();

        if (($data['status'] ?? null) !== 'SUCCESS' || empty($data['GatewayPageURL'])) {
            throw new RuntimeException('Failed to create SSLCommerz session.');
        }

        return (string) $data['GatewayPageURL'];
    }

    public function validatePayment(string $valId): array
    {
        [$storeId, $storePassword] = $this->credentials();

        $response = Http::get($this->baseUrl().'/validator/api/validationserverAPI.php', [
            'val_id' => $valId,
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'format' => 'json',
        ]);

        $data = $response->json();

        return [
            'status' => (string) ($data['status'] ?? 'INVALID'),
            'tran_id' => (string) ($data['tran_id'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? 'BDT'),
            'val_id' => (string) ($data['val_id'] ?? $valId),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function credentials(): array
    {
        $storeId = $this->settings->get('sslcommerz', 'store_id');
        $storePassword = $this->settings->get('sslcommerz', 'store_passwd');

        if (blank($storeId) || blank($storePassword)) {
            throw new RuntimeException('SSLCommerz credentials are not configured.');
        }

        return [(string) $storeId, (string) $storePassword];
    }

    private function baseUrl(): string
    {
        return (bool) $this->settings->get('sslcommerz', 'sandbox', true)
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }
}
