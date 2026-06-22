<?php

declare(strict_types=1);

namespace App\Support\Capi;

/**
 * In-memory Conversions API for tests. Records each event (as the Meta payload)
 * without any network call.
 */
final class FakeConversionApi implements ConversionApi
{
    /** @var array<int, CapiEvent> */
    public array $events = [];

    public function send(CapiEvent $event): bool
    {
        $this->events[] = $event;

        return true;
    }

    /**
     * Convenience filter for assertions.
     *
     * @return array<int, CapiEvent>
     */
    public function ofType(string $eventName): array
    {
        return array_values(array_filter($this->events, static fn (CapiEvent $e): bool => $e->eventName === $eventName));
    }
}
