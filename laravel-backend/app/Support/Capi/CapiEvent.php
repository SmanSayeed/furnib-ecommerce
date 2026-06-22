<?php

declare(strict_types=1);

namespace App\Support\Capi;

/**
 * A single Meta Conversions API event. `event_id` is shared with the browser
 * Pixel so Meta de-duplicates the server + browser copies of the same action.
 */
final class CapiEvent
{
    /**
     * @param  array<string, mixed>  $customData
     */
    public function __construct(
        public readonly string $eventName,
        public readonly string $eventId,
        public readonly int $eventTime,
        public readonly string $actionSource,
        public readonly ?string $eventSourceUrl,
        public readonly CapiUserData $userData,
        public readonly array $customData = [],
    ) {}

    /**
     * The Meta event object. Null/empty members are dropped.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'event_name' => $this->eventName,
            'event_id' => $this->eventId,
            'event_time' => $this->eventTime,
            'action_source' => $this->actionSource,
            'event_source_url' => $this->eventSourceUrl,
            'user_data' => $this->userData->toArray(),
            'custom_data' => $this->customData,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }
}
