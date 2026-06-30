<?php

declare(strict_types=1);

namespace App\Support\Ga4;

/**
 * A single GA4 Measurement Protocol event for one client. `clientId` ties the
 * server event to the user's GA4 session (captured from the browser `_ga`
 * cookie at checkout).
 */
final class Ga4Event
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly string $clientId,
        public readonly string $name,
        public readonly array $params = [],
    ) {}

    /**
     * The Measurement Protocol request body.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'events' => [[
                'name' => $this->name,
                'params' => $this->params,
            ]],
        ];
    }
}
