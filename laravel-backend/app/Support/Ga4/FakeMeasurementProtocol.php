<?php

declare(strict_types=1);

namespace App\Support\Ga4;

/**
 * In-memory GA4 Measurement Protocol for tests. Records each event without any
 * network call.
 */
final class FakeMeasurementProtocol implements MeasurementProtocol
{
    /** @var array<int, Ga4Event> */
    public array $events = [];

    public function send(Ga4Event $event): bool
    {
        $this->events[] = $event;

        return true;
    }

    /**
     * Convenience filter for assertions.
     *
     * @return array<int, Ga4Event>
     */
    public function ofType(string $name): array
    {
        return array_values(array_filter($this->events, static fn (Ga4Event $e): bool => $e->name === $name));
    }
}
