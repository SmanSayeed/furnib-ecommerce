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

        $order->loadMissing(['customer', 'shippingZone']);

        $customerName = (string) ($order->customer->name ?? 'Customer');
        $city = (string) ($order->shippingZone->name ?? 'Dhaka');
        $itemCount = max(1, (int) $order->items()->sum('qty'));

        // Every field SSLCommerz v4 marks MANDATORY is sent. cus_email is
        // required but our COD orders have none, so we fall back to the store's
        // own contact inbox (a real address, so the gateway receipt never
        // bounces). shipping_method='YES' means the ship_* block is required —
        // we derive it from the order. value_a echoes the order_no back on every
        // callback, giving us a reliable handle even before validation.
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
            // Product (mandatory).
            'product_name' => 'Furnib order '.$order->order_no,
            'product_category' => 'Furniture',
            'product_profile' => 'physical-goods',
            // Customer (mandatory: name, email, phone).
            'cus_name' => $customerName,
            'cus_email' => $this->customerEmail($order),
            'cus_phone' => (string) ($order->customer->mobile ?? ''),
            'cus_add1' => $order->address,
            'cus_city' => $city,
            // cus_state/cus_postcode are marked mandatory by SSLCommerz v4. The
            // sandbox is lenient, but LIVE can reject or risk-flag a session
            // without them, so we always send a sensible value (zone as state,
            // a generic Dhaka postcode we don't collect at checkout).
            'cus_state' => $city,
            'cus_postcode' => '1200',
            'cus_country' => 'Bangladesh',
            // Shipping (mandatory when shipping_method !== 'NO').
            'shipping_method' => 'YES',
            'num_of_item' => $itemCount,
            'ship_name' => $customerName,
            'ship_add1' => $order->address,
            'ship_city' => $city,
            'ship_state' => $city,
            'ship_postcode' => '1200',
            'ship_country' => 'Bangladesh',
            // Echoed back verbatim on every callback/IPN.
            'value_a' => $order->order_no,
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
     * Query a transaction by OUR tran_id (SSLCommerz "Transaction Query" API).
     * Used by the reconciliation sweep to recover a payment whose browser
     * callback AND IPN were both lost, but where money may actually have moved.
     * Returns null when the gateway reports no transaction for this id yet.
     *
     * @return array<string, mixed>|null
     */
    public function queryTransaction(string $tranId): ?array
    {
        [$storeId, $storePassword] = $this->credentials();

        $response = Http::get($this->baseUrl().'/validator/api/merchantTransIDvalidationAPI.php', [
            'tran_id' => $tranId,
            'store_id' => $storeId,
            'store_passwd' => $storePassword,
            'v' => 1,
            'format' => 'json',
        ]);

        $data = $response->json();
        $elements = $data['element'] ?? null;

        if ((int) ($data['no_of_trans_found'] ?? 0) < 1 || ! is_array($elements) || $elements === []) {
            return null;
        }

        // Most recent element wins if the gateway returns several attempts.
        $element = $elements[0];

        return [
            'status' => (string) ($element['status'] ?? 'INVALID'),
            'tran_id' => (string) ($element['tran_id'] ?? $tranId),
            'amount' => (float) ($element['amount'] ?? $element['currency_amount'] ?? 0),
            'currency' => (string) ($element['currency_type'] ?? $element['currency'] ?? 'BDT'),
            'val_id' => (string) ($element['val_id'] ?? ''),
        ];
    }

    /**
     * Verify SSLCommerz' verify_sign hash: md5 of the alphabetically-sorted
     * verify_key fields plus md5(store_passwd), as key=value&… . Proves the POST
     * genuinely came from SSLCommerz. Absent signature → true (validatePayment
     * stays the authoritative gate); present-but-wrong → false.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyCallback(array $payload): bool
    {
        $sign = $payload['verify_sign'] ?? null;
        $keyList = $payload['verify_key'] ?? null;

        if (! is_string($sign) || $sign === '' || ! is_string($keyList) || $keyList === '') {
            return true;
        }

        [, $storePassword] = $this->credentials();

        $fields = [];
        foreach (explode(',', $keyList) as $key) {
            $fields[$key] = (string) ($payload[$key] ?? '');
        }
        $fields['store_passwd'] = md5($storePassword);
        ksort($fields);

        $pairs = [];
        foreach ($fields as $key => $value) {
            $pairs[] = $key.'='.$value;
        }

        return hash_equals(md5(implode('&', $pairs)), $sign);
    }

    /**
     * SSLCommerz requires a customer email. Our phone-first COD orders rarely
     * have one, so fall back to the store's own contact inbox (a real, owned
     * address) and finally to a safe no-reply on the app domain.
     */
    private function customerEmail(Order $order): string
    {
        $email = $order->customer->email ?? null;

        if (blank($email)) {
            $email = $this->settings->get('branding', 'contact_email');
        }

        if (blank($email)) {
            $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'furnib.com';
            $email = 'orders@'.$host;
        }

        return (string) $email;
    }

    /**
     * Credentials for the ACTIVE mode. Sandbox and live store credentials are
     * stored side by side (`sandbox_store_id`/`live_store_id`, …) so switching
     * the Sandbox/Live toggle never wipes the other environment's keys. Falls
     * back to the legacy single pair for installs predating the split.
     *
     * @return array{0: string, 1: string}
     */
    private function credentials(): array
    {
        $mode = $this->isSandbox() ? 'sandbox' : 'live';

        $storeId = $this->settings->get('sslcommerz', $mode.'_store_id')
            ?? $this->settings->get('sslcommerz', 'store_id');
        $storePassword = $this->settings->get('sslcommerz', $mode.'_store_passwd')
            ?? $this->settings->get('sslcommerz', 'store_passwd');

        if (blank($storeId) || blank($storePassword)) {
            throw new RuntimeException('SSLCommerz credentials are not configured.');
        }

        return [(string) $storeId, (string) $storePassword];
    }

    private function isSandbox(): bool
    {
        return (bool) $this->settings->get('sslcommerz', 'sandbox', true);
    }

    private function baseUrl(): string
    {
        return $this->isSandbox()
            ? 'https://sandbox.sslcommerz.com'
            : 'https://securepay.sslcommerz.com';
    }
}
