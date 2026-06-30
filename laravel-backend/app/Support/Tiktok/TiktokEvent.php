<?php

declare(strict_types=1);

namespace App\Support\Tiktok;

/**
 * A single TikTok Events API (v1.3) event. `event_id` is shared with the browser
 * TikTok Pixel so TikTok de-duplicates the server + browser copies of the same
 * action.
 */
final class TiktokEvent
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public readonly string $eventName,
        public readonly string $eventId,
        public readonly int $eventTime,
        public readonly ?string $eventSourceUrl,
        public readonly TiktokUserData $userData,
        public readonly array $properties = [],
    ) {}

    /**
     * The TikTok event object. Null/empty members are dropped.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'event' => $this->eventName,
            'event_id' => $this->eventId,
            'event_time' => $this->eventTime,
            'user' => $this->userData->toArray(),
            'page' => $this->eventSourceUrl !== null && $this->eventSourceUrl !== ''
                ? ['url' => $this->eventSourceUrl]
                : null,
            'properties' => $this->properties,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }
}
