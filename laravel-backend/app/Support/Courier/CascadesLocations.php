<?php

declare(strict_types=1);

namespace App\Support\Courier;

/**
 * A courier whose booking needs a city → zone → area cascade (e.g. Pathao).
 * Each level is fetched server-side from the provider and the chosen numeric
 * ids are stored on the shipment's meta at booking time.
 */
interface CascadesLocations
{
    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function cities(): array;

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function zones(int $cityId): array;

    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function areas(int $zoneId): array;
}
