<?php

declare(strict_types=1);

namespace App\Support\Courier;

use App\Models\Courier;
use Closure;

/**
 * Resolves the API gateway for a courier from its driver. Drivers are registered
 * as factories (open for extension — RedX/Pathao register their own without
 * touching callers), so a new courier provider is a new registration, not an
 * edit here. A 'manual' courier has no API and resolves to null; callers treat
 * null as "record only, book by hand".
 */
final class CourierManager
{
    /** @var array<string, Closure(Courier): CourierGateway> */
    private array $factories = [];

    /**
     * Register (or override, e.g. in tests) the gateway factory for a driver.
     *
     * @param  Closure(Courier): CourierGateway  $factory
     */
    public function register(string $driver, Closure $factory): void
    {
        $this->factories[$driver] = $factory;
    }

    /**
     * The API gateway for this courier, or null when it has no API (manual, or an
     * unregistered driver).
     */
    public function driverFor(Courier $courier): ?CourierGateway
    {
        if (! $courier->isApi()) {
            return null;
        }

        $factory = $this->factories[$courier->driver] ?? null;

        return $factory === null ? null : $factory($courier);
    }

    /** Whether a courier can be booked via an API right now. */
    public function canBookViaApi(Courier $courier): bool
    {
        return $courier->isApi()
            && isset($this->factories[$courier->driver])
            && $courier->isConfigured();
    }
}
