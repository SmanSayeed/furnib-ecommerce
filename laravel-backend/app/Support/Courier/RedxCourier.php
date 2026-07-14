<?php

declare(strict_types=1);

namespace App\Support\Courier;

use App\Models\Shipment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * RedX courier integration (RedX Open API v1.0.0-beta). Credentials are injected
 * per-courier from the courier's encrypted config and sent only to RedX over
 * HTTPS. Booking needs a delivery-area choice (captured at booking time and read
 * from the shipment's meta); the pickup store is a fixed per-courier setting.
 *
 * @see https://redx.com.bd/developer-api/
 */
final class RedxCourier implements CourierGateway, ListsDeliveryAreas, TestsConnection
{
    private const NAME = 'RedX';

    private const LIVE_URL = 'https://openapi.redx.com.bd/v1.0.0-beta';

    private const SANDBOX_URL = 'https://sandbox.redx.com.bd/v1.0.0-beta';

    public function __construct(
        private readonly ?string $accessToken,
        private readonly ?string $pickupStoreId,
        private readonly bool $sandbox = false,
    ) {}

    public function createConsignment(Shipment $shipment): array
    {
        $meta = $shipment->meta ?? [];
        $areaId = $meta['delivery_area_id'] ?? null;
        $areaName = $meta['delivery_area'] ?? null;

        if (blank($areaId) || blank($areaName)) {
            throw new RuntimeException('RedX booking needs a delivery area — none was selected.');
        }

        if (blank($this->pickupStoreId)) {
            throw new RuntimeException('RedX pickup store is not configured.');
        }

        $response = $this->client()->post($this->baseUrl().'/parcel', [
            'customer_name' => $shipment->recipient_name,
            'customer_phone' => $shipment->recipient_phone,
            'delivery_area' => (string) $areaName,
            'delivery_area_id' => (int) $areaId,
            'customer_address' => $shipment->recipient_address,
            'merchant_invoice_id' => $shipment->order->order_no,
            // Whole taka — RedX collects an integer amount.
            'cash_collection_amount' => (string) $shipment->cod_amount->toDisplay(),
            'parcel_weight' => (int) ($meta['parcel_weight'] ?? 500),
            'value' => (int) $shipment->cod_amount->toDisplay(),
            'pickup_store_id' => (int) $this->pickupStoreId,
            'instruction' => (string) ($shipment->note ?? ''),
        ]);

        $trackingId = $response->json('tracking_id');

        if (! $response->successful() || blank($trackingId)) {
            throw CourierException::http(self::NAME, $response->status(), $response->body());
        }

        return [
            // RedX exposes a single tracking id; use it for both fields.
            'consignment_id' => (string) $trackingId,
            'tracking_code' => (string) $trackingId,
            'status' => 'pending',
        ];
    }

    public function getStatus(string $trackingCode): string
    {
        $response = $this->client()->get($this->baseUrl().'/parcel/track/'.$trackingCode);

        $events = $response->json('tracking');

        if (! is_array($events) || $events === []) {
            return 'pending';
        }

        // The most recent event carries the current status message.
        $latest = $events[0];

        return (string) ($latest['message_en'] ?? $latest['status'] ?? 'pending');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function areas(): array
    {
        $response = $this->client()->get($this->baseUrl().'/areas');

        $areas = $response->json('areas');

        if (! is_array($areas)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $area): ?array {
                if (! is_array($area) || blank($area['id'] ?? null)) {
                    return null;
                }

                return [
                    'id' => (int) $area['id'],
                    'name' => trim(((string) ($area['name'] ?? '')).' — '.((string) ($area['post_code'] ?? ''))),
                ];
            },
            $areas,
        )));
    }

    /**
     * Read-only area lookup — authenticates the same bearer token booking uses, so
     * a green result proves the token is live.
     */
    public function testConnection(): string
    {
        try {
            $response = $this->client()->get($this->baseUrl().'/areas');
        } catch (ConnectionException $e) {
            throw CourierException::unreachable(self::NAME, $e->getMessage());
        }

        if (! $response->successful()) {
            throw CourierException::http(self::NAME, $response->status(), $response->body());
        }

        $areas = $response->json('areas');
        $count = is_array($areas) ? count($areas) : 0;

        return "RedX connected. {$count} delivery areas available.";
    }

    private function baseUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_URL : self::LIVE_URL;
    }

    private function client(): PendingRequest
    {
        if (blank($this->accessToken)) {
            throw CourierException::missingCredentials(self::NAME);
        }

        // RedX expects the raw token here; we add the "Bearer " prefix ourselves.
        // A token pasted WITH the prefix would become "Bearer Bearer …" → 401, so
        // strip it defensively.
        $token = preg_replace('/^Bearer\s+/i', '', (string) $this->accessToken) ?? (string) $this->accessToken;

        return Http::withHeaders([
            'API-ACCESS-TOKEN' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ])
            ->connectTimeout(10)
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }
}
