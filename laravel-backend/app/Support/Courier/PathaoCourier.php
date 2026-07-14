<?php

declare(strict_types=1);

namespace App\Support\Courier;

use App\Models\Shipment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pathao courier integration (Pathao Merchant / Aladdin API). Credentials are
 * injected per-courier from the encrypted config and sent only to Pathao over
 * HTTPS. The OAuth access token is cached per courier (keyed by id) so we don't
 * re-issue a token on every call. Booking needs a city → zone → area cascade,
 * captured at booking time and read from the shipment's meta.
 *
 * @see https://merchant.pathao.com/
 */
final class PathaoCourier implements CascadesLocations, CourierGateway, TestsConnection
{
    private const NAME = 'Pathao';

    private const LIVE_URL = 'https://api-hermes.pathao.com';

    private const SANDBOX_URL = 'https://courier-api-sandbox.pathao.com';

    /** Normal delivery. */
    private const DELIVERY_TYPE = 48;

    /** Parcel (not a document). */
    private const ITEM_TYPE = 2;

    public function __construct(
        private readonly ?string $clientId,
        private readonly ?string $clientSecret,
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly ?string $storeId,
        private readonly bool $sandbox,
        private readonly string $cacheKey,
    ) {}

    public function createConsignment(Shipment $shipment): array
    {
        $meta = $shipment->meta ?? [];

        foreach (['recipient_city', 'recipient_zone', 'recipient_area'] as $key) {
            if (blank($meta[$key] ?? null)) {
                throw new RuntimeException('Pathao booking needs city, zone and area — the location was not fully selected.');
            }
        }

        if (blank($this->storeId)) {
            throw new RuntimeException('Pathao store is not configured.');
        }

        $response = $this->client()->post($this->baseUrl().'/aladdin/api/v1/orders', [
            'store_id' => (int) $this->storeId,
            'merchant_order_id' => $shipment->order->order_no,
            'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $shipment->recipient_phone,
            'recipient_address' => $shipment->recipient_address,
            'recipient_city' => (int) $meta['recipient_city'],
            'recipient_zone' => (int) $meta['recipient_zone'],
            'recipient_area' => (int) $meta['recipient_area'],
            'delivery_type' => self::DELIVERY_TYPE,
            'item_type' => self::ITEM_TYPE,
            'special_instruction' => (string) ($shipment->note ?? ''),
            'item_quantity' => (int) ($meta['item_quantity'] ?? 1),
            'item_weight' => (string) ($meta['item_weight'] ?? '0.5'),
            'amount_to_collect' => (int) $shipment->cod_amount->toDisplay(),
        ]);

        $consignmentId = $response->json('data.consignment_id');

        if (! $response->successful() || blank($consignmentId)) {
            throw CourierException::http(self::NAME, $response->status(), $response->body());
        }

        return [
            'consignment_id' => (string) $consignmentId,
            'tracking_code' => (string) $consignmentId,
            'status' => (string) ($response->json('data.order_status') ?? 'pending'),
        ];
    }

    public function getStatus(string $trackingCode): string
    {
        $response = $this->client()->get($this->baseUrl().'/aladdin/api/v1/orders/'.$trackingCode.'/info');

        return (string) ($response->json('data.order_status') ?? 'pending');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function cities(): array
    {
        return $this->mapList(
            $this->client()->get($this->baseUrl().'/aladdin/api/v1/city-list')->json('data.data'),
            'city_id',
            'city_name',
        );
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function zones(int $cityId): array
    {
        return $this->mapList(
            $this->client()->get($this->baseUrl().'/aladdin/api/v1/cities/'.$cityId.'/zone-list')->json('data.data'),
            'zone_id',
            'zone_name',
        );
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function areas(int $zoneId): array
    {
        return $this->mapList(
            $this->client()->get($this->baseUrl().'/aladdin/api/v1/zones/'.$zoneId.'/area-list')->json('data.data'),
            'area_id',
            'area_name',
        );
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function mapList(mixed $rows, string $idKey, string $nameKey): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $row) use ($idKey, $nameKey): ?array {
                if (! is_array($row) || blank($row[$idKey] ?? null)) {
                    return null;
                }

                return ['id' => (int) $row[$idKey], 'name' => (string) ($row[$nameKey] ?? '')];
            },
            $rows,
        )));
    }

    private function baseUrl(): string
    {
        return $this->sandbox ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /**
     * Issuing a token IS the credential test — it exercises client_id, client_secret,
     * username and password in one call. The cache is dropped first so a corrected
     * credential is actually tried, rather than the stale token being re-used.
     */
    public function testConnection(): string
    {
        Cache::forget($this->cacheKey);
        $this->accessToken();

        return 'Pathao connected. Access token issued.';
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(20)
            ->retry(2, 300, throw: false);
    }

    /**
     * A valid OAuth access token, cached per courier. Pathao tokens live ~5 days;
     * we cache a little under the reported lifetime and re-issue on expiry.
     */
    private function accessToken(): string
    {
        if (blank($this->clientId) || blank($this->clientSecret) || blank($this->username) || blank($this->password)) {
            throw CourierException::missingCredentials(self::NAME);
        }

        $cached = Cache::get($this->cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::acceptJson()->asJson()
                ->connectTimeout(10)
                ->timeout(20)
                ->post($this->baseUrl().'/aladdin/api/v1/issue-token', [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'username' => $this->username,
                    'password' => $this->password,
                    'grant_type' => 'password',
                ]);
        } catch (ConnectionException $e) {
            throw CourierException::unreachable(self::NAME, $e->getMessage());
        }

        $token = $response->json('access_token');
        $expiresIn = (int) ($response->json('expires_in') ?? 0);

        if (! $response->successful() || blank($token)) {
            throw CourierException::http(self::NAME, $response->status(), $response->body());
        }

        // Cache for the reported lifetime minus a 5-minute safety buffer (default
        // to one hour when the API omits expires_in).
        $ttl = $expiresIn > 600 ? $expiresIn - 300 : 3600;
        Cache::put($this->cacheKey, (string) $token, $ttl);

        return (string) $token;
    }
}
