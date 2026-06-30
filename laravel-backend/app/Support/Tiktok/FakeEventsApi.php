<?php

declare(strict_types=1);

namespace App\Support\Tiktok;

/**
 * In-memory TikTok Events API for tests. Records each event without any network
 * call.
 */
final class FakeEventsApi implements EventsApi
{
    /** @var array<int, TiktokEvent> */
    public array $events = [];

    public function send(TiktokEvent $event): bool
    {
        $this->events[] = $event;

        return true;
    }

    /**
     * Convenience filter for assertions.
     *
     * @return array<int, TiktokEvent>
     */
    public function ofType(string $eventName): array
    {
        return array_values(array_filter($this->events, static fn (TiktokEvent $e): bool => $e->eventName === $eventName));
    }
}
