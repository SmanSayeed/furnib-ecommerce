<?php

declare(strict_types=1);

namespace App\Support\Courier;

use App\Models\Shipment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * SteadFast courier integration. Credentials are injected per-courier (resolved by
 * Courier::credential(), which reads the encrypted config and falls back to the
 * legacy `steadfast` settings group) and sent only to SteadFast over HTTPS — never
 * returned to the client or logged.
 *
 * @see https://portal.packzy.com/api/v1
 */
final class SteadFastCourier implements CourierGateway, TestsConnection
{
    private const BASE_URL = 'https://portal.packzy.com/api/v1';

    private const NAME = 'SteadFast';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?string $secretKey,
    ) {}

    public function createConsignment(Shipment $shipment): array
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->post(self::BASE_URL.'/create_order', [
            'invoice' => $shipment->order->order_no,
            'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $shipment->recipient_phone,
            'recipient_address' => $shipment->recipient_address,
            'cod_amount' => number_format($shipment->cod_amount->toDisplay(), 2, '.', ''),
            'note' => (string) $shipment->note,
        ]));

        $consignment = $response->json('consignment');

        if (! is_array($consignment) || blank($consignment['consignment_id'] ?? null)) {
            // A 2xx with no consignment means SteadFast accepted the call but
            // refused the parcel (a duplicate invoice is the usual cause — order_no
            // must be unique per merchant, so a re-book after a partial failure
            // lands here). Surface their words, not ours.
            throw CourierException::http(self::NAME, $response->status(), $response->body());
        }

        return [
            'consignment_id' => (string) $consignment['consignment_id'],
            'tracking_code' => (string) ($consignment['tracking_code'] ?? ''),
            'status' => (string) ($consignment['status'] ?? 'pending'),
        ];
    }

    public function getStatus(string $trackingCode): string
    {
        $response = $this->send(
            fn (PendingRequest $http): Response => $http->get(self::BASE_URL.'/status_by_trackingcode/'.$trackingCode),
        );

        return (string) ($response->json('delivery_status') ?? 'pending');
    }

    /**
     * Read-only balance check — authenticates with the same Api-Key/Secret-Key
     * headers as booking, so a green result proves the keys are usable.
     */
    public function testConnection(): string
    {
        $response = $this->send(fn (PendingRequest $http): Response => $http->get(self::BASE_URL.'/get_balance'));

        $balance = $response->json('current_balance');

        return $balance === null
            ? 'SteadFast connected.'
            : 'SteadFast connected. Current balance: ৳'.number_format((float) $balance, 2);
    }

    /**
     * One place where every SteadFast call is made, so no call can forget the
     * timeout or the status check. A failure here always becomes a CourierException
     * carrying the provider's status and body — never a bare RuntimeException that
     * the controller turns into a 500.
     *
     * @param  callable(PendingRequest): Response  $call
     */
    private function send(callable $call): Response
    {
        $response = $this->attempt($call);

        if (! $response->successful()) {
            throw CourierException::http(self::NAME, $response->status(), $response->body());
        }

        return $response;
    }

    /** @param callable(PendingRequest): Response $call */
    private function attempt(callable $call): Response
    {
        try {
            return $call($this->client());
        } catch (ConnectionException $e) {
            // DNS, TLS, or a blocked egress from the container. Common on a fresh
            // VPS when the provider requires the server IP to be whitelisted.
            throw CourierException::unreachable(self::NAME, $e->getMessage());
        }
    }

    private function client(): PendingRequest
    {
        if (blank($this->apiKey) || blank($this->secretKey)) {
            throw CourierException::missingCredentials(self::NAME);
        }

        return Http::withHeaders([
            'Api-Key' => (string) $this->apiKey,
            'Secret-Key' => (string) $this->secretKey,
            'Content-Type' => 'application/json',
        ])
            // Without these a blocked port hangs until Traefik 504s the admin.
            ->connectTimeout(10)
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }
}
