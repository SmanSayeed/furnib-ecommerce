<?php

declare(strict_types=1);

namespace App\Support\Courier;

use App\Models\Shipment;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SteadFast courier integration. API credentials are read from encrypted
 * settings at call time and sent only to SteadFast over HTTPS — never returned
 * to the client or logged.
 */
final class SteadFastCourier implements CourierGateway
{
    private const BASE_URL = 'https://portal.packzy.com/api/v1';

    public function __construct(private readonly SettingsService $settings) {}

    public function createConsignment(Shipment $shipment): array
    {
        $response = $this->client()->post(self::BASE_URL.'/create_order', [
            'invoice' => $shipment->order->order_no,
            'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $shipment->recipient_phone,
            'recipient_address' => $shipment->recipient_address,
            'cod_amount' => number_format($shipment->cod_amount->toDisplay(), 2, '.', ''),
            'note' => (string) $shipment->note,
        ]);

        $consignment = $response->json('consignment');

        if (! is_array($consignment) || empty($consignment['consignment_id'])) {
            throw new RuntimeException('Failed to create SteadFast consignment.');
        }

        return [
            'consignment_id' => (string) $consignment['consignment_id'],
            'tracking_code' => (string) ($consignment['tracking_code'] ?? ''),
            'status' => (string) ($consignment['status'] ?? 'pending'),
        ];
    }

    public function getStatus(string $trackingCode): string
    {
        $response = $this->client()->get(self::BASE_URL.'/status_by_trackingcode/'.$trackingCode);

        return (string) ($response->json('delivery_status') ?? 'pending');
    }

    private function client(): PendingRequest
    {
        $apiKey = $this->settings->get('steadfast', 'api_key');
        $secretKey = $this->settings->get('steadfast', 'secret_key');

        if (blank($apiKey) || blank($secretKey)) {
            throw new RuntimeException('SteadFast credentials are not configured.');
        }

        return Http::withHeaders([
            'Api-Key' => (string) $apiKey,
            'Secret-Key' => (string) $secretKey,
            'Content-Type' => 'application/json',
        ]);
    }
}
